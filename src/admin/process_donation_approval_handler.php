<?php
require_once '../auth.php'; // For session and access control
require_once '../db_connect.php'; // For database connection

start_secure_session();
require_admin('../home.php'); // Redirect non-admins to home.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['term_id'])) {
        $term_id = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
        $admin_user_id = $_SESSION['user_id'] ?? null;
        $admin_identifier = $admin_user_id ? "[Admin ID: {$admin_user_id}]" : "[Admin ID: UNKNOWN]";

        if ($term_id === false || $term_id === null) {
            $_SESSION['error_message'] = "ID do Termo de Doação inválido.";
            header("Location: ../home.php?message_type=error&message=" . urlencode($_SESSION['error_message']));
            exit();
        }

        if ($admin_user_id === null) {
            $_SESSION['error_message'] = "Não foi possível identificar o administrador. Sessão inválida.";
            error_log("Critical: Admin user_id not found in session during term approval for term_id: " . $term_id . ". Action attempted by unknown admin.");
            header("Location: ../home.php?message_type=error&message=" . urlencode($_SESSION['error_message']));
            exit();
        } else {
            // Fetch admin details for logging
            $stmt_admin_details = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
            if ($stmt_admin_details) {
                $stmt_admin_details->bind_param("i", $admin_user_id);
                $stmt_admin_details->execute();
                $result_admin_details = $stmt_admin_details->get_result();
                if ($admin_details = $result_admin_details->fetch_assoc()) {
                    $admin_display_log = !empty(trim($admin_details['full_name'] ?? '')) ? $admin_details['full_name'] : $admin_details['username'];
                    $admin_identifier = "[Admin: {$admin_display_log} (ID: {$admin_user_id})]";
                }
                $stmt_admin_details->close();
            }
        }

        $conn->begin_transaction();

        try {
            $action = $_POST['action'] ?? 'approve'; // Default to 'approve' if no action is specified

            if ($action === 'decline') {
                // Logic for declining a donation term
                $reproval_reason = trim($_POST['reproval_reason'] ?? '');
                if (empty($reproval_reason)) {
                    throw new Exception("O motivo da reprovação é obrigatório. Ação por " . $admin_identifier);
                }

                // Update donation_terms table
                $stmt_term = $conn->prepare(
                    "UPDATE donation_terms SET status = 'Reprovado', reproval_reason = ?, reproved_at = NOW(), reproved_by_user_id = ? WHERE term_id = ? AND status = 'Aguardando Aprovação'"
                );
                if (!$stmt_term) throw new Exception("Erro ao preparar atualização do termo para reprovação: " . $conn->error . " por " . $admin_identifier);
                
                $stmt_term->bind_param("sii", $reproval_reason, $admin_user_id, $term_id);
                $stmt_term->execute();

                if ($stmt_term->affected_rows === 0) {
                    throw new Exception("Nenhum termo de doação foi atualizado para reprovação. Verifique se o ID (" . $term_id . ") é válido e o status era 'Aguardando Aprovação'. Ação por " . $admin_identifier);
                }
                $stmt_term->close();

                // Get all item_ids associated with this term_id from donation_term_items
                $stmt_get_items = $conn->prepare("SELECT item_id FROM donation_term_items WHERE term_id = ?");
                if (!$stmt_get_items) throw new Exception("Erro ao preparar busca de itens do termo para reprovação: " . $conn->error . " por " . $admin_identifier);
                
                $stmt_get_items->bind_param("i", $term_id);
                $stmt_get_items->execute();
                $result_items = $stmt_get_items->get_result();
                
                $item_ids_to_revert = [];
                while ($row = $result_items->fetch_assoc()) {
                    $item_ids_to_revert[] = $row['item_id'];
                }
                $stmt_get_items->close();

                // Update the status of related items back to 'Pendente' in the 'items' table
                if (!empty($item_ids_to_revert)) {
                    $placeholders = implode(',', array_fill(0, count($item_ids_to_revert), '?'));
                    // Itens associados a um termo 'Aguardando Aprovação' também estão com status 'Aguardando Aprovação'
                    $sql_update_items = "UPDATE items SET status = 'Pendente' WHERE id IN ($placeholders) AND status = 'Aguardando Aprovação'";
                    
                    $stmt_update_items = $conn->prepare($sql_update_items);
                    if (!$stmt_update_items) throw new Exception("Erro ao preparar reversão de status dos itens: " . $conn->error . " por " . $admin_identifier);

                    $types = str_repeat('i', count($item_ids_to_revert));
                    $stmt_update_items->bind_param($types, ...$item_ids_to_revert);
                    $stmt_update_items->execute();
                    $stmt_update_items->close();
                }

                $conn->commit();
                $_SESSION['success_message'] = "Termo de Doação ID: " . htmlspecialchars($term_id) . " reprovado com sucesso. Itens relacionados revertidos para 'Pendente'.";
                header("Location: ../home.php?message_type=success&message=" . urlencode($_SESSION['success_message']));
                exit();

            } else { // Default action: 'approve'
                // 1. Update the donation_terms status to 'Doado'
                // Also set approved_at and approved_by_user_id
                $stmt_term = $conn->prepare(
                    "UPDATE donation_terms SET status = 'Doado', approved_at = NOW(), approved_by_user_id = ? WHERE term_id = ? AND status = 'Aguardando Aprovação'"
                );
                if (!$stmt_term) throw new Exception("Erro ao preparar atualização do termo para aprovação: " . $conn->error . " por " . $admin_identifier);
                
                $stmt_term->bind_param("ii", $admin_user_id, $term_id);
                $stmt_term->execute();

                if ($stmt_term->affected_rows === 0) {
                    throw new Exception("Nenhum termo de doação foi atualizado para aprovação. Verifique se o ID (" . $term_id . ") é válido e o status era 'Aguardando Aprovação'. Ação por " . $admin_identifier);
                }
                $stmt_term->close();

                // 2. Get all item_ids associated with this term_id from donation_term_items
                $stmt_get_items = $conn->prepare("SELECT item_id FROM donation_term_items WHERE term_id = ?");
                if (!$stmt_get_items) throw new Exception("Erro ao preparar busca de itens do termo para aprovação: " . $conn->error . " por " . $admin_identifier);

                $stmt_get_items->bind_param("i", $term_id);
                $stmt_get_items->execute();
                $result_items = $stmt_get_items->get_result();
                
                $item_ids_to_update = [];
                while ($row = $result_items->fetch_assoc()) {
                    $item_ids_to_update[] = $row['item_id'];
                }
                $stmt_get_items->close();

                // 3. Update the status of related items to 'Doado' in the 'items' table
                if (!empty($item_ids_to_update)) {
                    $placeholders = implode(',', array_fill(0, count($item_ids_to_update), '?'));
                    // Itens associados a um termo 'Aguardando Aprovação' também estão com status 'Aguardando Aprovação'
                    $sql_update_items = "UPDATE items SET status = 'Doado' WHERE id IN ($placeholders) AND status = 'Aguardando Aprovação'";
                    
                    $stmt_update_items = $conn->prepare($sql_update_items);
                    if (!$stmt_update_items) throw new Exception("Erro ao preparar atualização dos itens para doado: " . $conn->error . " por " . $admin_identifier);

                    $types = str_repeat('i', count($item_ids_to_update));
                    $stmt_update_items->bind_param($types, ...$item_ids_to_update);
                    $stmt_update_items->execute();
                    
                    $stmt_update_items->close();
                }

                $conn->commit();
                $_SESSION['success_message'] = "Termo de Doação ID: " . htmlspecialchars($term_id) . " aprovado com sucesso. Itens relacionados marcados como 'Doado'.";
                header("Location: ../home.php?message_type=success&message=" . urlencode($_SESSION['success_message']));
                exit();
            }

        } catch (mysqli_sql_exception $db_exception) {
            $conn->rollback();
            $error_detail_for_log = "Database error during term processing for term_id " . $term_id . " by " . $admin_identifier . ": " . $db_exception->getMessage();
            error_log($error_detail_for_log);
            // Para depuração, adiciona a mensagem da exceção à mensagem da sessão.
            // REMOVER ou alterar para uma mensagem genérica em PRODUÇÃO.
            $_SESSION['error_message'] = "Erro no banco de dados ao processar termo de doação. Detalhe: " . htmlspecialchars($db_exception->getMessage());
            header("Location: ../home.php?message_type=error&message=" . urlencode($_SESSION['error_message'])); 
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_detail_for_log = "General error during term processing for term_id " . $term_id . " by " . $admin_identifier . ": " . $e->getMessage();
            error_log($error_detail_for_log);
            // Para depuração, adiciona a mensagem da exceção à mensagem da sessão.
            // REMOVER ou alterar para uma mensagem genérica em PRODUÇÃO.
            $_SESSION['error_message'] = "Erro ao processar termo de doação: " . htmlspecialchars($e->getMessage());
            header("Location: ../home.php?message_type=error&message=" . urlencode($_SESSION['error_message']));
            exit();
        } finally {
            // Ensure statements are closed if they were prepared
            if (isset($stmt_term) && $stmt_term instanceof mysqli_stmt) $stmt_term->close();
            if (isset($stmt_get_items) && $stmt_get_items instanceof mysqli_stmt) $stmt_get_items->close();
            if (isset($stmt_update_items) && $stmt_update_items instanceof mysqli_stmt) $stmt_update_items->close();
            if (isset($conn) && $conn instanceof mysqli) $conn->close();
        }
    } else {
        $_SESSION['error_message'] = "ID do Termo de Doação não fornecido.";
        header("Location: ../home.php?message_type=error&message=" . urlencode($_SESSION['error_message']));
        exit();
    }
} else {
    $_SESSION['error_message'] = "Método de requisição inválido.";
    header("Location: ../home.php?message_type=error&message=" . urlencode($_SESSION['error_message']));
    exit();
}
?>