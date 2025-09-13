<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_admin(); // Apenas admins podem descartar itens

$item_ids_str = $_GET['ids'] ?? '';

if (empty($item_ids_str)) {
    header('Location: home.php?message_type=error&message=' . urlencode('Nenhum item selecionado para descarte.'));
    exit();
}

$item_ids = explode(',', $item_ids_str);
$item_ids = array_map('intval', $item_ids);
$item_ids = array_filter($item_ids, function($id) { return $id > 0; });

if (empty($item_ids)) {
    header('Location: home.php?message_type=error&message=' . urlencode('Nenhum ID de item válido fornecido.'));
    exit();
}

$placeholders = implode(',', array_fill(0, count($item_ids), '?'));
$types = str_repeat('i', count($item_ids));

// Atualiza o status dos itens para 'Descartado'
// Apenas itens com status 'Pendente' podem ser descartados diretamente.
$sql = "UPDATE items SET status = 'Descartado', status_changed_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders) AND status = 'Pendente'";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param($types, ...$item_ids);
    $stmt->execute();

    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    $conn->close();

    if ($affected_rows > 0) {
        header('Location: home.php?message_type=success&message=' . urlencode($affected_rows . ' item(s) foram descartados com sucesso.'));
    } else {
        header('Location: home.php?message_type=error&message=' . urlencode('Nenhum item foi alterado. Eles podem já ter sido processados ou não estavam pendentes.'));
    }
} else {
    // Log do erro
    error_log("Erro ao preparar a query para descartar itens: " . $conn->error);
    header('Location: home.php?message_type=error&message=' . urlencode('Ocorreu um erro no servidor ao tentar descartar os itens.'));
}

exit();
?>
