<?php
require_once 'auth.php';
require_once 'db_connect.php'; // Provides $conn

start_secure_session();
require_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Retrieve Data ---
    $item_ids_raw = $_POST['item_ids'] ?? [];
    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_address = trim($_POST['owner_address'] ?? '');
    $owner_phone = trim($_POST['owner_phone'] ?? '');
    $owner_credential_number = trim($_POST['owner_credential_number'] ?? '');
    $signature_data_url = $_POST['signature_data_url'] ?? '';
    // Use the server-generated timestamp for consistency, but one from form is also available
    $devolution_timestamp_str = $_POST['devolution_timestamp_value'] ?? date("Y-m-d H:i:s");

    $returned_by_user_id = $_SESSION['user_id'];
    $item_ids = [];

    // --- Validate Item IDs ---
    if (!is_array($item_ids_raw) || empty($item_ids_raw)) {
        $_SESSION['devolution_form_error'] = 'Nenhum ID de item recebido.';
        header('Location: devolve_item_page.php?ids=' . implode(',', array_map('htmlspecialchars', $item_ids_raw))); // Try to pass IDs back
        exit();
    }
    foreach ($item_ids_raw as $id_r) {
        $id_v = filter_var($id_r, FILTER_VALIDATE_INT);
        if ($id_v) $item_ids[] = $id_v;
    }
    if (empty($item_ids)) {
        $_SESSION['devolution_form_error'] = 'IDs de item inválidos fornecidos.';
        // Attempt to reconstruct original query string for devolve_item_page if possible
        $original_ids_param = !empty($item_ids_raw) ? implode(',', array_map('htmlspecialchars', $item_ids_raw)) : '';
        header('Location: devolve_item_page.php?ids=' . $original_ids_param);
        exit();
    }
    $ids_for_query_str = implode(',', $item_ids); // For redirecting back if needed

    // --- Validate Required Fields ---
    if (empty($owner_name)) {
        $_SESSION['devolution_form_error'] = 'O nome do proprietário é obrigatório.';
        header('Location: devolve_item_page.php?ids=' . $ids_for_query_str);
        exit();
    }
    if (empty($signature_data_url) || strpos($signature_data_url, 'data:image/png;base64,') !== 0) {
        $_SESSION['devolution_form_error'] = 'Assinatura inválida ou não fornecida.';
        header('Location: devolve_item_page.php?ids=' . $ids_for_query_str);
        exit();
    }

    // --- Process Signature ---
    $signature_image_path = '';
    $upload_dir = 'uploads/signatures/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) { // Create recursive, set permissions
            error_log("Confirm Devolution: Failed to create signature upload directory: " . $upload_dir);
            $_SESSION['devolution_form_error'] = 'Erro no servidor: não foi possível preparar o local para salvar a assinatura.';
            header('Location: devolve_item_page.php?ids=' . $ids_for_query_str);
            exit();
        }
    }

    $signature_data = str_replace('data:image/png;base64,', '', $signature_data_url);
    $signature_data = base64_decode($signature_data);
    if ($signature_data === false) {
        $_SESSION['devolution_form_error'] = 'Erro ao decodificar dados da assinatura.';
        header('Location: devolve_item_page.php?ids=' . $ids_for_query_str);
        exit();
    }
    $signature_filename = uniqid('sig_', true) . '.png';
    $signature_image_path = $upload_dir . $signature_filename;

    if (!file_put_contents($signature_image_path, $signature_data)) {
        error_log("Confirm Devolution: Failed to save signature file to: " . $signature_image_path);
        $_SESSION['devolution_form_error'] = 'Erro ao salvar a imagem da assinatura.';
        header('Location: devolve_item_page.php?ids=' . $ids_for_query_str);
        exit();
    }

    // --- Database Operations (Transaction Recommended) ---
    $conn->begin_transaction();
    $all_successful = true;
    $processed_item_ids = [];

    try {
        // 1. Update item statuses
        $sql_update_item = "UPDATE items SET status = 'Devolvido', status_changed_at = NOW() WHERE id = ? AND status = 'Pendente'";
        $stmt_update = $conn->prepare($sql_update_item);
        if (!$stmt_update) throw new Exception("Prepare failed (update items): " . $conn->error);

        foreach ($item_ids as $item_id) {
            $stmt_update->bind_param("i", $item_id);
            if (!$stmt_update->execute() || $stmt_update->affected_rows === 0) {
                // If affected_rows is 0, item was not 'Pendente' or did not exist.
                // This check ensures we only create devolution docs for items successfully marked as 'Devolvido' now.
                throw new Exception("Falha ao atualizar status do item ID " . $item_id . " para 'Devolvido' ou item não estava 'Pendente'.");
            }
            $processed_item_ids[] = $item_id; // Add to list of successfully status-updated items
        }
        $stmt_update->close();

        // 2. Insert into devolution_documents for each processed item
        $sql_insert_doc = "INSERT INTO devolution_documents
                              (item_id, returned_by_user_id, devolution_timestamp, owner_name, owner_address, owner_phone, owner_credential_number, signature_image_path)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert_doc);
        if (!$stmt_insert) throw new Exception("Prepare failed (insert_doc): " . $conn->error);

        // Use the server-generated timestamp for all documents in this transaction for consistency
        $devolution_timestamp_db_format = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $devolution_timestamp_str)));


        foreach ($processed_item_ids as $item_id_processed) {
            $stmt_insert->bind_param("iissssss",
                $item_id_processed,
                $returned_by_user_id,
                $devolution_timestamp_db_format, // Use consistent timestamp
                $owner_name,
                $owner_address,
                $owner_phone,
                $owner_credential_number,
                $signature_image_path
            );
            if (!$stmt_insert->execute()) {
                throw new Exception("Falha ao registrar documento de devolução para o item ID " . $item_id_processed . ": " . $stmt_insert->error);
            }
        }
        $stmt_insert->close();

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        $all_successful = false;
        error_log("Confirm Devolution Transaction Error: " . $e->getMessage());
        
        if (!empty($signature_image_path) && file_exists($signature_image_path)) {
            //unlink($signature_image_path); // Be cautious with auto-delete
        }
        $_SESSION['devolution_form_error'] = 'Erro ao processar a devolução: ' . $e->getMessage();
        header('Location: devolve_item_page.php?ids=' . $ids_for_query_str);
        exit();
    }

    // ### CORREÇÃO AQUI: Bloco de sucesso modificado para criar a mensagem personalizada ###
    if ($all_successful) {
        // Constrói a lista de IDs para a mensagem
        $id_list_str = implode(', ', $processed_item_ids);
        
        // Define o texto para singular ou plural
        $item_text = (count($processed_item_ids) > 1) ? 'Itens de IDs' : 'Item de ID';
        
        // Cria a mensagem final
        $success_message = "{$item_text} {$id_list_str} foram devolvidos com sucesso.";

        // Redireciona para a home.php usando o sistema de mensagens que já existe nela
        header('Location: home.php?message_type=success&message=' . urlencode($success_message));
        exit();
    }
    
    // Fallback if something unexpected happens, though exceptions should be caught.
    $_SESSION['devolution_form_error'] = 'Ocorreu um erro inesperado durante a devolução.';
    header('Location: devolve_item_page.php?ids=' . $ids_for_query_str);
    exit();

} else {
    // Not a POST request
    $_SESSION['home_page_message'] = 'Acesso inválido à página de confirmação.';
    header('Location: home.php?error=invalidaccess_confirmdev');
    exit();
}
?>