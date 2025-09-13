<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_connect.php';

require_admin('/index.php?error=unauthorized_donation_submit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['home_page_error_message'] = 'Acesso inválido ao handler de doação.';
    header('Location: /home.php?message_type=error&message=' . urlencode($_SESSION['home_page_error_message']));
    exit();
}

$item_ids_str_for_donation = trim($_POST['item_ids_for_donation'] ?? '');
$conn->begin_transaction();

try {
    // --- 1. Retrieve and Sanitize Form Data ---
    $responsible_donation_from_session = $_SESSION['full_name'] ?? $_SESSION['username'];
    $user_id_from_session = $_SESSION['user_id'] ?? null;
    $donation_date = trim($_POST['donation_date'] ?? '');
    $donation_time = trim($_POST['donation_time'] ?? '');
    $company_id = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
    
    // --- 2. Validate Inputs ---
    $errors = [];
    if (empty($user_id_from_session)) $errors[] = "Sessão de usuário inválida. Faça login novamente.";
    if (empty($responsible_donation_from_session)) $errors[] = "Nome do responsável (usuário logado) não encontrado.";
    if (empty($donation_date)) $errors[] = "Data da doação é obrigatória.";
    if (empty($donation_time)) $errors[] = "Hora da doação é obrigatória.";
    if (empty($company_id) || $company_id === false) $errors[] = "Empresa/Instituição recebedora é obrigatória.";
    if (empty($item_ids_str_for_donation)) $errors[] = "Nenhum item ID fornecido para doação.";

    if (!empty($donation_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $donation_date)) $errors[] = "Formato de data inválido.";
    if (!empty($donation_time) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $donation_time)) $errors[] = "Formato de hora inválido.";

    $item_ids_array = [];
    if (!empty($item_ids_str_for_donation)) {
        $raw_ids = explode(',', $item_ids_str_for_donation);
        foreach ($raw_ids as $id_str) {
            $id_int = filter_var(trim($id_str), FILTER_VALIDATE_INT);
            if ($id_int !== false && $id_int > 0) {
                $item_ids_array[] = $id_int;
            }
        }
        if (empty($item_ids_array)) $errors[] = "Os IDs dos itens fornecidos são inválidos ou estão vazios.";
    }

    if (!empty($errors)) {
        throw new Exception(implode("<br>", $errors));
    }

    // --- 3. Database Operations ---
    // A. Verify Item Status
    $placeholders_check = implode(',', array_fill(0, count($item_ids_array), '?'));
    $sql_check_items = "SELECT id, status FROM items WHERE id IN ($placeholders_check)";
    $stmt_check_items = $conn->prepare($sql_check_items);
    if (!$stmt_check_items) throw new Exception("Erro ao preparar verificação de itens: " . $conn->error);

    $types_check = str_repeat('i', count($item_ids_array));
    $stmt_check_items->bind_param($types_check, ...$item_ids_array);
    $stmt_check_items->execute();
    $result_check_items = $stmt_check_items->get_result();
    $found_items_map = [];
    while ($item_row = $result_check_items->fetch_assoc()) {
        $found_items_map[$item_row['id']] = $item_row['status'];
    }
    $stmt_check_items->close();

    $problematic_items_info = [];
    foreach ($item_ids_array as $req_id) {
        if (!isset($found_items_map[$req_id])) {
            $problematic_items_info[] = "ID " . htmlspecialchars($req_id) . " (não encontrado)";
        } elseif ($found_items_map[$req_id] !== 'Pendente') {
            $problematic_items_info[] = "ID " . htmlspecialchars($req_id) . " (status: " . htmlspecialchars($found_items_map[$req_id]) . ")";
        }
    }

    if (!empty($problematic_items_info)) {
        throw new Exception("Alguns itens não podem ser doados: " . implode(', ', $problematic_items_info) . ". Apenas itens com status 'Pendente' são permitidos.");
    }

    // B. Insert into donation_terms
    $sql_insert_term = "INSERT INTO donation_terms (
        user_id, responsible_donation, donation_date, donation_time, company_id, status
    ) VALUES (?, ?, ?, ?, ?, 'Em aprovação')";

    $stmt_insert_term = $conn->prepare($sql_insert_term);
    if (!$stmt_insert_term) throw new Exception("Erro ao preparar inserção do termo: " . $conn->error);

    $stmt_insert_term->bind_param("isssi",
        $user_id_from_session,
        $responsible_donation_from_session,
        $donation_date,
        $donation_time,
        $company_id
    );
    if (!$stmt_insert_term->execute()) throw new Exception("Erro ao salvar termo de doação: " . $stmt_insert_term->error);
    $term_id = $conn->insert_id; // Pega o ID do termo recém-criado
    $stmt_insert_term->close();

    // C. Insert into donation_term_items
    $sql_insert_term_item = "INSERT INTO donation_term_items (term_id, item_id) VALUES (?, ?)";
    $stmt_insert_term_item = $conn->prepare($sql_insert_term_item);
    if (!$stmt_insert_term_item) throw new Exception("Erro ao preparar inserção dos itens do termo: " . $conn->error);
    foreach ($item_ids_array as $item_id) {
        $stmt_insert_term_item->bind_param("ii", $term_id, $item_id);
        if (!$stmt_insert_term_item->execute()) throw new Exception("Erro ao associar item ID " . htmlspecialchars($item_id) . " ao termo: " . $stmt_insert_term_item->error);
    }
    $stmt_insert_term_item->close();

    // D. Update items status
    $placeholders_update = implode(',', array_fill(0, count($item_ids_array), '?'));
    $sql_update_items = "UPDATE items SET status = 'Em Aprovação' WHERE id IN ($placeholders_update)";
    $stmt_update_items = $conn->prepare($sql_update_items);
    if (!$stmt_update_items) throw new Exception("Erro ao preparar atualização dos itens: " . $conn->error);

    $types_update = str_repeat('i', count($item_ids_array));
    $stmt_update_items->bind_param($types_update, ...$item_ids_array);
    if (!$stmt_update_items->execute()) throw new Exception("Erro ao atualizar status dos itens: " . $stmt_update_items->error);
    $stmt_update_items->close();

    $conn->commit();
    
    // ### CORREÇÃO AQUI: Bloco de redirecionamento alterado ###
    // Mensagem de sucesso personalizada conforme solicitado.
    $success_message = "Termo de Doação enviado para Aprovação com sucesso.";

    // Redireciona para a home.php com a mensagem de sucesso no formato que ela espera.
    // PS: A sua home.php exibe a mensagem em um retângulo verde, não cinza, usando a classe "success-message".
    header('Location: /home.php?message_type=success&message=' . urlencode($success_message));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Donation Submission Error: " . $e->getMessage());
    $_SESSION['generate_donation_page_error_message'] = $e->getMessage();
    $redirect_url = 'generate_donation_term_page.php';
    if (!empty($item_ids_str_for_donation)) {
        $redirect_url .= '?item_ids=' . urlencode($item_ids_str_for_donation);
    }
    header('Location: ' . $redirect_url);
    exit();
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>