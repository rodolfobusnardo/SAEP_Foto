<?php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin('../index.php'); // Ensure only admins can delete items

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$item_id) {
        // If there's a specific admin page for items, redirect there. Otherwise, home or index.
        header('Location: /home.php?error=invaliditemid_delete'); // Assuming an admin item management page
        exit();
    }

    $current_db_status = null;
    $sql_get_status = "SELECT status FROM items WHERE id = ?";
    $stmt_get_status = $conn->prepare($sql_get_status);

    if ($stmt_get_status) {
        $stmt_get_status->bind_param("i", $item_id);
        if ($stmt_get_status->execute()) {
            $result_status = $stmt_get_status->get_result();
            if ($row_status = $result_status->fetch_assoc()) {
                $current_db_status = $row_status['status'];
            } else { // Item not found
                header('Location: /home.php?error=itemnotfound_delete'); // Use existing error key
                exit();
            }
        } else {
            error_log("SQL Execute Error (get_item_status for delete): " . $stmt_get_status->error);
            header('Location: /home.php?error=dberror_delete_fetchstatus');
            exit();
        }
        $stmt_get_status->close();
    } else {
        error_log("SQL Prepare Error (get_item_status for delete): " . $conn->error);
        header('Location: /home.php?error=dberror_delete_fetchstatus');
        exit();
    }

    // Optional: Check if item exists before attempting delete, though DB will handle it gracefully.
    // $sql_check = "SELECT id FROM items WHERE id = ?";
    // ...

    if (in_array($current_db_status, ['Devolvido', 'Doado'])) {
        // Item is currently Devolvido or Doado, prevent deletion.
        // Store a more specific message in session if needed, or rely on GET param.
        // For example, set $_SESSION['home_page_error'] = "Não é possível excluir itens com status '" . htmlspecialchars($current_db_status) . "'.";
        header('Location: /home.php?error=delete_not_allowed_for_status&item_status=' . htmlspecialchars($current_db_status));
        exit();
    }

    $sql = "DELETE FROM items WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("SQL Prepare Error (delete_item): " . $conn->error);
        header('Location: /home.php?error=sqlerror_deleteitem');
        exit();
    }

    $stmt->bind_param("i", $item_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            header('Location: /home.php?message=itemdeleted');
        } else {
            // No rows affected means ID not found
            header('Location: /home.php?error=itemnotfound_delete');
        }
    } else {
        error_log("SQL Execute Error (delete_item): " . $stmt->error);
        header('Location: /home.php?error=itemdeletefailed');
    }
    $stmt->close();

} else {
    // Not a POST request
    header('Location: /home.php?error=invalidrequest_deleteitem');
    exit();
}

$conn->close();
?>
