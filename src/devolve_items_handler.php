<?php
require_once 'auth.php';
require_once 'db_connect.php';

start_secure_session();
require_login(); // User must be logged in to mark items as returned

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['item_ids']) && is_array($_POST['item_ids'])) {
        $item_ids_raw = $_POST['item_ids'];
        $item_ids_to_devolve = [];
        
        // ### MODIFICADO: Variáveis para coletar os IDs processados ###
        $processed_item_ids = [];
        $error_count = 0;

        // Validate and sanitize item IDs
        foreach ($item_ids_raw as $id_raw) {
            $id_sanitized = filter_var($id_raw, FILTER_VALIDATE_INT);
            if ($id_sanitized) {
                $item_ids_to_devolve[] = $id_sanitized;
            }
        }

        if (empty($item_ids_to_devolve)) {
            header('Location: home.php?error=noitemselected_devolve');
            exit();
        }

        // Prepare statement for updating item status
        // We only update items that are currently 'Pendente'
        $sql_update_status = "UPDATE items SET status = 'Devolvido', status_changed_at = NOW() WHERE id = ? AND status = 'Pendente'";
        $stmt_update = $conn->prepare($sql_update_status);

        if ($stmt_update) {
            foreach ($item_ids_to_devolve as $item_id) {
                $stmt_update->bind_param("i", $item_id);
                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows > 0) {
                        // ### MODIFICADO: Coleta o ID do item processado com sucesso ###
                        $processed_item_ids[] = $item_id;
                    } else {
                        // Item not found, not 'Pendente', or other issue
                        error_log("Devolve Handler: No rows affected for item ID " . $item_id . ". May not be 'Pendente' or does not exist.");
                        $error_count++;
                    }
                } else {
                    error_log("Devolve Handler: SQL Execute Error for item ID " . $item_id . ": " . $stmt_update->error);
                    $error_count++;
                }
            }
            $stmt_update->close();
            
            // ### CORREÇÃO AQUI: Lógica de redirecionamento atualizada para criar a mensagem personalizada ###
            if (!empty($processed_item_ids)) {
                $id_list_str = implode(', ', $processed_item_ids);
                $item_text = (count($processed_item_ids) > 1) ? 'Itens de IDs' : 'Item de ID';
                $success_message = "{$item_text} {$id_list_str} foram devolvidos com sucesso.";

                // Se houveram erros com outros itens, adiciona um aviso na mensagem
                if ($error_count > 0) {
                    $success_message .= " ({$error_count} item(ns) não puderam ser processados pois não estavam pendentes).";
                    header('Location: home.php?message_type=warning&message=' . urlencode($success_message));
                } else {
                    header('Location: home.php?message_type=success&message=' . urlencode($success_message));
                }

            } elseif ($error_count > 0) {
                header('Location: home.php?message_type=error&message=' . urlencode('Falha ao devolver os itens selecionados.'));
            } else { 
                header('Location: home.php?message_type=info&message=' . urlencode('Nenhum item precisou ser devolvido (já não estavam pendentes).'));
            }
            exit();

        } else {
            error_log("Devolve Handler: SQL Prepare Error: " . $conn->error);
            header('Location: home.php?message_type=error&message=' . urlencode('Erro interno ao preparar a operação.'));
            exit();
        }

    } else {
        // No item_ids provided or not an array
        header('Location: home.php?error=noitemids_devolve');
        exit();
    }
} else {
    // Not a POST request, redirect to home
    header('Location: home.php');
    exit();
}

$conn->close();
?>