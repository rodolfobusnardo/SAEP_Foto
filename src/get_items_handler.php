<?php
// This script is intended to be included by other PHP files (e.g., home.php, admin/manage_items.php)
// It populates the $items array. Session start and login checks should be handled by the calling script.

require_once 'db_connect.php'; // Ensures $conn is available

// --- Configuração da Paginação ---
const ITEMS_PER_PAGE = 15; // Máximo de 15 registros por página

// Obter o número da página atual da URL, padrão para 1 se não estiver definido
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($current_page === false || $current_page < 1) {
    $current_page = 1;
}

$offset = ($current_page - 1) * ITEMS_PER_PAGE;

// Initialize filters
$filter_category_id = filter_input(INPUT_GET, 'filter_category_id', FILTER_VALIDATE_INT);
$filter_location_id = filter_input(INPUT_GET, 'filter_location_id', FILTER_VALIDATE_INT);
$filter_found_date_start = trim($_GET['filter_found_date_start'] ?? '');
$filter_found_date_end = trim($_GET['filter_found_date_end'] ?? '');

$filter_days_waiting_min = null;
$filter_days_waiting_max = null;

// Parse filter_days_waiting sent from the form (e.g., "0-7", "60-9999")
if (isset($_GET['filter_days_waiting']) && !empty($_GET['filter_days_waiting'])) {
    $days_waiting_range = explode('-', $_GET['filter_days_waiting']);
    if (count($days_waiting_range) == 2) {
        $filter_days_waiting_min = (int)$days_waiting_range[0];
        $filter_days_waiting_max = (int)$days_waiting_range[1];
    }
}


$filter_status = trim($_GET['filter_status'] ?? '');
$filter_item_name = trim($_GET['filter_item_name'] ?? '');
$filter_barcode = trim($_GET['filter_barcode'] ?? ''); // NOVO: Captura o filtro de código de barras

$items = [];
$sql_conditions = [];
$sql_params_types = "";
$sql_params_values = [];

// Base SQL query for fetching items
$sql_base_select = "SELECT
                         i.id, i.name, i.status, i.found_date, i.description, i.registered_at, i.barcode, i.image_path,
                         c.name AS category_name, c.code AS category_code,
                         l.name AS location_name,
                         u.username AS registered_by_username,
                         u.full_name AS registered_by_full_name,
                         dd.id AS devolution_document_id,
                         dn.term_id AS donation_document_id,
                         DATEDIFF(CURDATE(), i.found_date) AS calculated_days_waiting
                       FROM items i
                       JOIN categories c ON i.category_id = c.id
                       JOIN locations l ON i.location_id = l.id
                       LEFT JOIN users u ON i.user_id = u.id
                       LEFT JOIN devolution_documents dd ON i.id = dd.item_id
                       LEFT JOIN donation_term_items dti ON i.id = dti.item_id
                       LEFT JOIN donation_terms dn ON dti.term_id = dn.term_id";


// Apply filters
if ($filter_category_id) {
    $sql_conditions[] = "i.category_id = ?";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_category_id;
}
if ($filter_location_id) {
    $sql_conditions[] = "i.location_id = ?";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_location_id;
}
if (!empty($filter_found_date_start) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_found_date_start)) {
    $sql_conditions[] = "i.found_date >= ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_found_date_start;
}
if (!empty($filter_found_date_end) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_found_date_end)) {
    $sql_conditions[] = "i.found_date <= ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_found_date_end;
}
if ($filter_days_waiting_min !== null) {
    $sql_conditions[] = "DATEDIFF(CURDATE(), i.found_date) >= ?";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_days_waiting_min;
}
if ($filter_days_waiting_max !== null) {
    $sql_conditions[] = "DATEDIFF(CURDATE(), i.found_date) <= ?";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_days_waiting_max;
}

// ### CORREÇÃO DEFINITIVA ###
// A validação in_array foi removida. A segurança é mantida pelo uso de prepared statements (bind_param)
// e pela definição do tipo ENUM da coluna no banco de dados.
if (!empty($filter_status)) {
    $sql_conditions[] = "i.status = ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_status;
}

if (!empty($filter_item_name)) {
    $name_words = explode(' ', $filter_item_name);
    $name_words = array_filter($name_words); 
    if (!empty($name_words)) {
        foreach ($name_words as $word) {
            $sql_conditions[] = "i.name LIKE ?";
            $sql_params_types .= "s";
            $sql_params_values[] = "%" . $word . "%";
        }
    }
}
if (!empty($filter_barcode)) {
    $sql_conditions[] = "i.barcode = ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_barcode;
}


$where_clause = "";
if (!empty($sql_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $sql_conditions);
}

// Bloco para buscar todos os IDs que correspondem ao filtro, sem paginação.
if (isset($_GET['get_all_ids']) && $_GET['get_all_ids'] === 'true') {
    $sql_all_ids = "SELECT i.id FROM items i" . $where_clause;
    $stmt_all_ids = $conn->prepare($sql_all_ids);

    if ($stmt_all_ids) {
        if (!empty($sql_params_types)) {
            $stmt_all_ids->bind_param($sql_params_types, ...$sql_params_values);
        }
        $stmt_all_ids->execute();
        $result_all_ids = $stmt_all_ids->get_result();
        
        $all_item_ids = [];
        if ($result_all_ids) {
            while ($row = $result_all_ids->fetch_assoc()) {
                $all_item_ids[] = $row['id'];
            }
        }
        $stmt_all_ids->close();

        header('Content-Type: application/json');
        echo json_encode(['all_ids' => $all_item_ids]);
        exit(); // Termina o script após enviar os IDs
    } else {
        http_response_code(500);
        error_log("SQL Prepare Error (get_items_handler - get_all_ids): " . $conn->error);
        echo json_encode(['error' => 'Failed to prepare statement for fetching all IDs.']);
        exit();
    }
}


// --- Consulta para Contar o Total de Itens ---
$sql_count = "SELECT COUNT(i.id) AS total_items FROM items i" . $where_clause;

$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    error_log("SQL Prepare Error (get_items_handler - count): " . $conn->error . " Query: " . $sql_count);
    $total_items = 0;
} else {
    if (!empty($sql_params_types)) { 
        $stmt_count->bind_param($sql_params_types, ...$sql_params_values);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_items = $result_count->fetch_assoc()['total_items'] ?? 0;
    $stmt_count->close();
}

$total_pages = $total_items > 0 ? ceil($total_items / ITEMS_PER_PAGE) : 1;

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * ITEMS_PER_PAGE;
}

// --- Consulta Principal para Obter os Itens com Paginação ---
$sql_query = $sql_base_select . $where_clause . " ORDER BY i.registered_at DESC LIMIT ? OFFSET ?";
$main_sql_params_types = $sql_params_types . "ii";
$main_sql_params_values = array_merge($sql_params_values, [ITEMS_PER_PAGE, $offset]);

$stmt = $conn->prepare($sql_query);

if ($stmt === false) {
    error_log("SQL Prepare Error (get_items_handler - main): " . $conn->error . " Query: " . $sql_query);
} else {
    if (!empty($sql_params_types)) {
        $stmt->bind_param($main_sql_params_types, ...$main_sql_params_values);
    } else {
         $stmt->bind_param("ii", ...[ITEMS_PER_PAGE, $offset]);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        error_log("SQL Execute Error (get_items_handler - main): " . $stmt->error);
    } else {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $row['days_waiting'] = $row['calculated_days_waiting'];
                $items[] = $row;
            }
        }
    }
    $stmt->close();
}

// Se o script for acessado via AJAX, retorna JSON e encerra.
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['items' => $items, 'total_pages' => (int)$total_pages, 'current_page' => (int)$current_page, 'total_items' => (int)$total_items]);
    exit();
}

// ### CORREÇÃO: Removida chave '}' extra que causava erro fatal ###
?>