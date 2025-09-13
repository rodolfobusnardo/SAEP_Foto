<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

require_approver();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: approvals_page.php');
    exit();
}

$term_id = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$reproval_reason = trim($_POST['reproval_reason'] ?? '');
$approver_user_id = $_SESSION['user_id'];

if (!$term_id || !in_array($action, ['approve', 'deny'])) {
    header('Location: approvals_page.php?error=Ação inválida ou ID do termo ausente.');
    exit();
}

if ($action === 'deny' && empty($reproval_reason)) {
    header('Location: review_donation_term.php?id=' . $term_id . '&error=O motivo da negação é obrigatório.');
    exit();
}

$conn->begin_transaction();

try {
    // 1. Verificar se o termo ainda está 'Em aprovação'
    $stmt = $conn->prepare("SELECT status FROM donation_terms WHERE term_id = ? FOR UPDATE");
    $stmt->bind_param("i", $term_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $term = $result->fetch_assoc();

    if (!$term) {
        throw new Exception("Termo não encontrado.");
    }
    if ($term['status'] !== 'Em aprovação') {
        throw new Exception("Este termo não está mais aguardando aprovação. Ação cancelada.");
    }
    $stmt->close();
    
    // Pega os IDs dos itens associados ao termo para usar em ambas as ações
    $stmt_items = $conn->prepare("SELECT item_id FROM donation_term_items WHERE term_id = ?");
    $stmt_items->bind_param("i", $term_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $item_ids_to_update = [];
    while ($row = $items_result->fetch_assoc()) {
        $item_ids_to_update[] = $row['item_id'];
    }
    $stmt_items->close();

    $message = '';

    if ($action === 'approve') {
        // 2.A. Atualizar o status do termo para 'Aprovado'
        $stmt_approve = $conn->prepare("UPDATE donation_terms SET status = 'Aprovado', approved_by_user_id = ?, approved_at = NOW() WHERE term_id = ?");
        $stmt_approve->bind_param("ii", $approver_user_id, $term_id);
        $stmt_approve->execute();
        $stmt_approve->close();

        // 3.A. Mudar status dos itens para 'Aprovado'
        if (!empty($item_ids_to_update)) {
            $placeholders = implode(',', array_fill(0, count($item_ids_to_update), '?'));
            $stmt_approve_items = $conn->prepare("UPDATE items SET status = 'Aprovado' WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($item_ids_to_update));
            $stmt_approve_items->bind_param($types, ...$item_ids_to_update);
            $stmt_approve_items->execute();
            $stmt_approve_items->close();
        }
        
        $message = "Termo #" . $term_id . " APROVADO com sucesso!";

    } elseif ($action === 'deny') {
        // 2.B. Atualizar o status do termo para 'Negado'
        $stmt_deny = $conn->prepare("UPDATE donation_terms SET status = 'Negado', reproved_by_user_id = ?, reproved_at = NOW(), reproval_reason = ? WHERE term_id = ?");
        $stmt_deny->bind_param("isi", $approver_user_id, $reproval_reason, $term_id);
        $stmt_deny->execute();
        $stmt_deny->close();
        
        // 3.B. Liberar os itens, mudando o status de volta para 'Pendente'
        if (!empty($item_ids_to_update)) {
            $placeholders = implode(',', array_fill(0, count($item_ids_to_update), '?'));
            $stmt_release = $conn->prepare("UPDATE items SET status = 'Pendente' WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($item_ids_to_update));
            $stmt_release->bind_param($types, ...$item_ids_to_update);
            $stmt_release->execute();
            $stmt_release->close();
        }
        
        $message = "Termo #" . $term_id . " NEGADO com sucesso. Itens foram retornados ao estoque.";
    }

    $conn->commit();
    header('Location: approvals_page.php?message=' . urlencode($message));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Approval/Denial Error: " . $e->getMessage());
    // CORREÇÃO DE SINTAXE NA LINHA ABAIXO
    header('Location: approvals_page.php?error=' . urlencode('Ocorreu um erro ao processar a solicitação: ' . $e->getMessage()));
    exit();
}
?>