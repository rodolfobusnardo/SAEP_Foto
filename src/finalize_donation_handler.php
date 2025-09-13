<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_terms.php');
    exit();
}

$term_id = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
$signature_data_base64 = $_POST['signature_data'] ?? '';

if (!$term_id || empty($signature_data_base64)) {
    header('Location: manage_terms.php?error=Dados insuficientes para finalizar a doação.');
    exit();
}

$conn->begin_transaction();

try {
    // 1. Processar a imagem da assinatura
    if (!preg_match('/^data:image\/png;base64,/', $signature_data_base64)) throw new Exception("Formato de dados da assinatura inválido.");
    
    $base64_data = base64_decode(preg_replace('/^data:image\/png;base64,/', '', $signature_data_base64));
    if ($base64_data === false) throw new Exception("Falha ao decodificar dados da assinatura.");

    $upload_dir = __DIR__ . '/uploads/signatures/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
    
    $signature_filename = 'sig_final_' . $term_id . '_' . time() . '.png';
    $signature_file_path = $upload_dir . $signature_filename;
    $signature_db_path = 'uploads/signatures/' . $signature_filename;

    if (!file_put_contents($signature_file_path, $base64_data)) throw new Exception("Falha ao salvar a imagem da assinatura.");

    // 2. Atualizar o termo para 'Doado'
    $stmt_term = $conn->prepare("UPDATE donation_terms SET status = 'Doado', signature_image_path = ? WHERE term_id = ? AND status = 'Aprovado'");
    $stmt_term->bind_param("si", $signature_db_path, $term_id);
    $stmt_term->execute();
    
    if ($stmt_term->affected_rows === 0) throw new Exception("O Termo não foi encontrado ou não está mais no status 'Aprovado'.");
    
    $stmt_term->close();

    // 3. Atualizar o status dos itens para 'Doado'
    $stmt_items_ids = $conn->prepare("SELECT item_id FROM donation_term_items WHERE term_id = ?");
    $stmt_items_ids->bind_param("i", $term_id);
    $stmt_items_ids->execute();
    $items_result = $stmt_items_ids->get_result();
    
    $item_ids_to_donate = [];
    while ($row = $items_result->fetch_assoc()) {
        $item_ids_to_donate[] = $row['item_id'];
    }
    $stmt_items_ids->close();

    if (!empty($item_ids_to_donate)) {
        $placeholders = implode(',', array_fill(0, count($item_ids_to_donate), '?'));
        $stmt_donate_items = $conn->prepare("UPDATE items SET status = 'Doado', status_changed_at = NOW() WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($item_ids_to_donate));
        $stmt_donate_items->bind_param($types, ...$item_ids_to_donate);
        $stmt_donate_items->execute();
        $stmt_donate_items->close();
    }
    
    $conn->commit();
    header('Location: manage_terms.php?success_message=' . urlencode("Doação do Termo #" . $term_id . " finalizada com sucesso!"));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    if (isset($signature_file_path) && file_exists($signature_file_path)) {
        unlink($signature_file_path);
    }
    error_log("Finalization Error: " . $e->getMessage());
    header('Location: view_donation_term_page.php?id=' . $term_id . '&error=' . urlencode($e->getMessage()));
    exit();
}
?>