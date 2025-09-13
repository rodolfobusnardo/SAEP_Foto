<?php
mb_internal_encoding('UTF-8'); // Set internal encoding for mb_string functions
header('Content-Type: application/json'); // Default content type for get_category responses
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
// For POST actions, require admin. For GET (like get_category), could be more lenient if needed,
// but generally, category details are admin-viewable/editable.
// Let's enforce admin for all actions in this handler for simplicity.
require_admin('../index.php', 'Acesso negado. Funcionalidade administrativa.');

$response = ['status' => 'error', 'message' => 'Ação inválida.'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $code = trim(strtoupper($_POST['code'] ?? '')); // Convert code to uppercase

        if (empty($name) || empty($code)) {
            header('Location: manage_categories.php?error=emptyfields_addcat');
            exit();
        }
        if (mb_strlen($name) > 255) {
            header('Location: manage_categories.php?error=catname_too_long');
            exit();
        }
        if (mb_strlen($name) < 3) {
            header('Location: manage_categories.php?error=catname_too_short');
            exit();
        }
        if (mb_strlen($code) > 10) { // Though code is typically ASCII, using mb_strlen for consistency
             header('Location: manage_categories.php?error=code_too_long');
             exit();
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $code)) { // Code is uppercased, so check against A-Z
            header('Location: manage_categories.php?error=code_invalid_format');
            exit();
        }
        // Check for uniqueness of name and code
        $sql_check = "SELECT id FROM categories WHERE name = ? OR code = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $name, $code);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            header('Location: manage_categories.php?error=cat_exists');
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        $sql = "INSERT INTO categories (name, code) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $name, $code);
        if ($stmt->execute()) {
            header('Location: manage_categories.php?success=cat_added');
        } else {
            error_log("SQL Error (add_category): " . $stmt->error);
            header('Location: manage_categories.php?error=add_cat_failed');
        }
        $stmt->close();
        exit();

    } elseif ($action == 'edit_category') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $code = trim(strtoupper($_POST['code'] ?? ''));

        if (!$id || empty($name) || empty($code)) {
            header('Location: manage_categories.php?error=emptyfields_editcat&id=' . $id);
            exit();
        }
        if (mb_strlen($name) > 255) {
            header('Location: manage_categories.php?error=catname_too_long_edit&id=' . $id);
            exit();
        }
        if (mb_strlen($name) < 3) {
            header('Location: manage_categories.php?error=catname_too_short_edit&id=' . $id);
            exit();
        }
        if (mb_strlen($code) > 10) { // Though code is typically ASCII, using mb_strlen for consistency
             header('Location: manage_categories.php?error=code_too_long_edit&id=' . $id);
             exit();
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $code)) { // Code is uppercased
            header('Location: manage_categories.php?error=code_invalid_format_edit&id=' . $id);
            exit();
        }

        // Check for uniqueness of name and code (excluding current category ID)
        $sql_check = "SELECT id FROM categories WHERE (name = ? OR code = ?) AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ssi", $name, $code, $id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            header('Location: manage_categories.php?error=cat_exists_edit&id=' . $id);
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        $sql = "UPDATE categories SET name = ?, code = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $code, $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header('Location: manage_categories.php?success=cat_updated');
            } else {
                 // No rows affected could mean data was same or ID not found
                header('Location: manage_categories.php?message=cat_nochange&id=' . $id);
            }
        } else {
            error_log("SQL Error (edit_category): " . $stmt->error);
            header('Location: manage_categories.php?error=edit_cat_failed&id=' . $id);
        }
        $stmt->close();
        exit();
    }
    // For POST actions that are not add/edit, but expect JSON response
    // This part is reached if an action is POSTed but not add/edit and expects JSON
    // However, the current structure redirects for add/edit.
    // If 'get_category' were a POST action (not typical for GET), it would be here.
    // For now, we assume 'get_category' will be a GET request.

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_category') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        $response['message'] = 'ID da categoria inválido.';
        echo json_encode($response);
        exit();
    }

    $sql = "SELECT id, name, code FROM categories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($category = $result->fetch_assoc()) {
        $response['status'] = 'success';
        $response['data'] = $category;
        $response['message'] = 'Categoria encontrada.';
    } else {
        $response['message'] = 'Categoria não encontrada.';
    }
    $stmt->close();
    echo json_encode($response);
    exit();

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete_category') {
    // Although this is a GET request for simplicity in the link,
    // destructive actions should ideally be POST with CSRF protection.
    // For this context, we'll proceed with GET but acknowledge this.
    require_admin('../index.php', 'Acesso negado. Funcionalidade administrativa.'); // Ensure admin for delete

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        header('Location: manage_categories.php?error=invalid_id_delete');
        exit();
    }

    // Check if the category is in use by any item
    $sql_check_usage = "SELECT COUNT(*) as count FROM items WHERE category_id = ?";
    $stmt_check_usage = $conn->prepare($sql_check_usage);
    $stmt_check_usage->bind_param("i", $id);
    $stmt_check_usage->execute();
    $result_usage = $stmt_check_usage->get_result();
    $usage_count = $result_usage->fetch_assoc()['count'];
    $stmt_check_usage->close();

    if ($usage_count > 0) {
        header('Location: manage_categories.php?error=cat_in_use&id=' . $id);
        exit();
    }

    // Proceed with deletion
    $sql_delete = "DELETE FROM categories WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            header('Location: manage_categories.php?success=cat_deleted');
        } else {
            // ID not found, though ideally, this check might be redundant
            // if UI only shows valid IDs.
            header('Location: manage_categories.php?error=cat_not_found_delete&id=' . $id);
        }
    } else {
        error_log("SQL Error (delete_category): " . $stmt_delete->error);
        header('Location: manage_categories.php?error=delete_cat_failed&id=' . $id);
    }
    $stmt_delete->close();
    exit();

} else {
    // Invalid request method or no action specified for POST, or unknown GET action
    // For POST, redirect to manage_categories. For GET, output JSON error.
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        header('Location: manage_categories.php?error=invalid_action');
        exit();
    }
    // For GET requests that are not 'get_category'
    $response['message'] = 'Ação GET inválida ou não especificada.';
    echo json_encode($response);
    exit();
}

$conn->close();
?>
