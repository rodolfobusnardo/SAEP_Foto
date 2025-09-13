<?php
// File: src/get_dashboard_data.php
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_connect.php';

start_secure_session();

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado.']);
    exit;
}

// Helper function to build query conditions
function build_query_conditions_finalized(
    $date_filter_start_param,
    $date_filter_end_param,
    $base_clauses_items_param,
    $base_params_items_param,
    $base_types_items_param,
    $options_param = []
) {
    $date_column_opt = $options_param['date_column'] ?? null;
    $fixed_status_opt = $options_param['fixed_status'] ?? null;
    $ignore_general_status_opt = $options_param['ignore_general_status_filter'] ?? false;
    $ignore_general_date_opt = $options_param['ignore_general_date_filter'] ?? false;
    $custom_conditions_opt = $options_param['custom_conditions'] ?? [];

    $clauses_arr = [];
    $params_arr = [];
    $types_str = "";

    if ($date_column_opt && $ignore_general_date_opt) {
        if ($date_filter_start_param) {
            $clauses_arr[] = $date_column_opt . " >= ?";
            $params_arr[] = $date_filter_start_param;
            $types_str .= "s";
        }
        if ($date_filter_end_param) {
            $clauses_arr[] = $date_column_opt . " <= ?";
            $params_arr[] = $date_filter_end_param;
            $types_str .= "s";
        }
    }

    for ($i = 0; $i < count($base_clauses_items_param); $i++) {
        $base_clause_item = $base_clauses_items_param[$i];
        $base_param_item = $base_params_items_param[$i];
        $base_type_char = substr($base_types_items_param, $i, 1);

        $is_base_status_clause_item = (strpos($base_clause_item, "i.status = ?") !== false);
        $is_base_found_date_clause_item = (strpos($base_clause_item, "i.found_date") !== false);

        if ($is_base_status_clause_item && $ignore_general_status_opt) {
            continue;
        }

        if ($is_base_found_date_clause_item && $date_column_opt && $ignore_general_date_opt) {
            continue;
        }

        $clauses_arr[] = $base_clause_item;
        $params_arr[] = $base_param_item;
        $types_str .= $base_type_char;
    }

    if ($fixed_status_opt) {
        $can_add_fixed_status_flag = true;

        if ($ignore_general_status_opt) {
            $temp_clauses_arr_status = [];
            $temp_params_arr_status = [];
            $temp_types_str_status = "";
            $original_clauses_count = count($clauses_arr);
            for ($k = 0; $k < $original_clauses_count; $k++) {
                if (strpos($clauses_arr[$k], "i.status = ?") === false) {
                    $temp_clauses_arr_status[] = $clauses_arr[$k];
                    $temp_params_arr_status[] = $params_arr[$k];
                    $temp_types_str_status .= substr($types_str, $k, 1);
                }
            }
            $clauses_arr = $temp_clauses_arr_status;
            $params_arr = $temp_params_arr_status;
            $types_str = $temp_types_str_status;
        } else {
            foreach ($clauses_arr as $idx => $existing_clause) {
                if (strpos($existing_clause, "i.status = ?") !== false) {
                    if ($params_arr[$idx] == $fixed_status_opt) {
                        $can_add_fixed_status_flag = false;
                    } else {
                        $can_add_fixed_status_flag = false;
                        error_log("build_query_conditions_finalized: Conflict. General status '{$params_arr[$idx]}' kept, but different fixed_status '{$fixed_status_opt}' requested.");
                    }
                    break;
                }
            }
        }

        if ($can_add_fixed_status_flag) {
            $is_already_identically_present = false;
            foreach ($clauses_arr as $idx => $existing_clause) {
                if (strpos($existing_clause, "i.status = ?") !== false && $params_arr[$idx] == $fixed_status_opt) {
                    $is_already_identically_present = true;
                    break;
                }
            }
            if (!$is_already_identically_present) {
                $clauses_arr[] = "i.status = ?";
                $params_arr[] = $fixed_status_opt;
                $types_str .= "s";
            }
        }
    }

    foreach ($custom_conditions_opt as $custom_clause_item) {
        if (!in_array($custom_clause_item, $clauses_arr)) {
            $clauses_arr[] = $custom_clause_item;
        }
    }

    $where_sql_str = "";
    if (!empty($clauses_arr)) {
        $where_sql_str = "WHERE " . implode(" AND ", $clauses_arr);
    }
    return ['sql' => $where_sql_str, 'params' => $params_arr, 'types' => $types_str, 'clauses' => $clauses_arr];
}

$date_start = $_GET['date_start'] ?? null;
$date_end = $_GET['date_end'] ?? null;
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$category_id = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
$location_id = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int)$_GET['location_id'] : null;

if (!$conn || $conn->connect_error) {
    error_log("Erro dashboard: Falha na conexão com o banco de dados: " . ($conn ? $conn->connect_error : "Objeto de conexão nulo"));
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.', 'details' => ($conn ? $conn->connect_error : "Objeto de conexão nulo")]);
    exit;
}

// ALTERAÇÃO: Adicionado campos para as novas taxas
$dashboard_data = [
    'filters_received' => [
        'date_start' => $date_start, 'date_end' => $date_end, 'status_filter' => $status_filter,
        'category_id' => $category_id, 'location_id' => $location_id
    ],
    'available_categories' => [], 'available_locations' => [], 'status_counts' => [],
    'top_locations' => [], 'registered_items_timeline' => [], 'devolved_items_timeline' => [],
    'donated_items_timeline' => [], 'donations_by_institution' => [], 
    'devolution_rate' => 0, // Taxa de Devolução
    'donation_rate' => 0,   // NOVA: Taxa de Doação
    'pending_rate' => 0,    // NOVA: Taxa de Pendência
    'discard_rate' => 0,    // Taxa de Descarte
    'top_pending_categories' => [], 'errors' => []
];

// Carregar filtros disponíveis (categorias e locais)
$stmt_cats = $conn->prepare("SELECT id, name FROM categories ORDER BY name ASC");
if ($stmt_cats) {
    $stmt_cats->execute();
    $result_cats = $stmt_cats->get_result();
    while ($row = $result_cats->fetch_assoc()) $dashboard_data['available_categories'][] = $row;
    $stmt_cats->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar categorias."; }

$stmt_locs = $conn->prepare("SELECT id, name FROM locations ORDER BY name ASC");
if ($stmt_locs) {
    $stmt_locs->execute();
    $result_locs = $stmt_locs->get_result();
    while ($row = $result_locs->fetch_assoc()) $dashboard_data['available_locations'][] = $row;
    $stmt_locs->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar locais."; }

// Montar cláusulas WHERE base para a maioria das consultas
$base_where_clauses_items = [];
$base_params_items = [];
$base_types_items = "";

if ($date_start) { $base_where_clauses_items[] = "i.found_date >= ?"; $base_params_items[] = $date_start; $base_types_items .= "s"; }
if ($date_end) { $base_where_clauses_items[] = "i.found_date <= ?"; $base_params_items[] = $date_end; $base_types_items .= "s"; }
if ($status_filter) { $base_where_clauses_items[] = "i.status = ?"; $base_params_items[] = $status_filter; $base_types_items .= "s"; }
if ($category_id) { $base_where_clauses_items[] = "i.category_id = ?"; $base_params_items[] = $category_id; $base_types_items .= "i"; }
if ($location_id) { $base_where_clauses_items[] = "i.location_id = ?"; $base_params_items[] = $location_id; $base_types_items .= "i"; }

// Gráfico de Itens por Status
$where_sql_general_items = !empty($base_where_clauses_items) ? "WHERE " . implode(" AND ", $base_where_clauses_items) : "";
$sql_status_counts = "SELECT i.status, COUNT(*) as total FROM items i {$where_sql_general_items} GROUP BY i.status ORDER BY i.status";
$stmt_status = $conn->prepare($sql_status_counts);
if ($stmt_status) {
    if (!empty($base_params_items)) $stmt_status->bind_param($base_types_items, ...$base_params_items);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();
    while ($row = $result_status->fetch_assoc()) $dashboard_data['status_counts'][] = $row;
    $stmt_status->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar dados de status."; }

// NOVA LÓGICA PARA CÁLCULO DAS TAXAS
// 1. Obter o total de itens para o período (base de cálculo), ignorando o filtro de status.
$total_items_conditions_parts = build_query_conditions_finalized($date_start, $date_end, $base_where_clauses_items, $base_params_items, $base_types_items, ['ignore_general_status_filter' => true]);
$sql_total_items = "SELECT COUNT(i.id) as total FROM items i {$total_items_conditions_parts['sql']}";
$stmt_total = $conn->prepare($sql_total_items);
$total_items_geral = 0;
if ($stmt_total) {
    if(!empty($total_items_conditions_parts['params'])) $stmt_total->bind_param($total_items_conditions_parts['types'], ...$total_items_conditions_parts['params']);
    $stmt_total->execute();
    $result = $stmt_total->get_result()->fetch_assoc();
    if($result) $total_items_geral = (int)$result['total'];
    $stmt_total->close();
} else { $dashboard_data['errors'][] = "Erro ao calcular total de itens para taxas."; }

// 2. Extrair contagens do resultado de 'status_counts' que já foi calculado.
$count_devolvido = 0;
$count_doado = 0;
$count_pendente = 0;
$count_descartado = 0;
foreach ($dashboard_data['status_counts'] as $status_count) {
    if ($status_count['status'] === 'Devolvido') $count_devolvido = (int)$status_count['total'];
    if ($status_count['status'] === 'Doado') $count_doado = (int)$status_count['total'];
    if ($status_count['status'] === 'Pendente') $count_pendente = (int)$status_count['total'];
    if ($status_count['status'] === 'Descartado') $count_descartado = (int)$status_count['total'];
}

// 3. Calcular e atribuir as taxas.
if ($total_items_geral > 0) {
    $dashboard_data['devolution_rate'] = round(($count_devolvido / $total_items_geral) * 100, 2);
    $dashboard_data['donation_rate'] = round(($count_doado / $total_items_geral) * 100, 2);
    $dashboard_data['pending_rate'] = round(($count_pendente / $total_items_geral) * 100, 2);
    $dashboard_data['discard_rate'] = round(($count_descartado / $total_items_geral) * 100, 2);
}
// FIM DA NOVA LÓGICA DAS TAXAS

// Gráfico de Top Locais
$sql_top_locations = "SELECT l.name as location_name, COUNT(i.id) as total_items FROM items i JOIN locations l ON i.location_id = l.id {$where_sql_general_items} GROUP BY l.id, l.name ORDER BY total_items DESC LIMIT 10";
$stmt_top_loc = $conn->prepare($sql_top_locations);
if ($stmt_top_loc) {
    if (!empty($base_params_items)) $stmt_top_loc->bind_param($base_types_items, ...$base_params_items);
    $stmt_top_loc->execute();
    $result_top_loc = $stmt_top_loc->get_result();
    while ($row = $result_top_loc->fetch_assoc()) $dashboard_data['top_locations'][] = $row;
    $stmt_top_loc->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar dados de top locais."; }

// Gráfico de Linha: Registrados vs Resolvidos
$reg_conditions = build_query_conditions_finalized($date_start, $date_end, $base_where_clauses_items, $base_params_items, $base_types_items, ['date_column' => 'i.registered_at', 'ignore_general_date_filter' => true]);
$sql_registered_timeline = "SELECT DATE(i.registered_at) as date, COUNT(i.id) as count FROM items i {$reg_conditions['sql']} GROUP BY DATE(i.registered_at) ORDER BY date ASC";
$stmt_reg_timeline = $conn->prepare($sql_registered_timeline);
if ($stmt_reg_timeline && (!empty($reg_conditions['params']) ? $stmt_reg_timeline->bind_param($reg_conditions['types'], ...$reg_conditions['params']) : true) && $stmt_reg_timeline->execute()) {
    $result = $stmt_reg_timeline->get_result();
    while ($row = $result->fetch_assoc()) $dashboard_data['registered_items_timeline'][] = $row;
    $stmt_reg_timeline->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar timeline de itens registrados: " . $conn->error; }

$dev_conditions = build_query_conditions_finalized($date_start, $date_end, $base_where_clauses_items, $base_params_items, $base_types_items, ['date_column' => 'dd.devolution_timestamp', 'ignore_general_date_filter' => true, 'ignore_general_status_filter' => true]);
$sql_devolved_timeline = "SELECT DATE(dd.devolution_timestamp) as date, COUNT(DISTINCT dd.item_id) as count FROM devolution_documents dd JOIN items i ON dd.item_id = i.id {$dev_conditions['sql']} GROUP BY DATE(dd.devolution_timestamp) ORDER BY date ASC";
$stmt_dev_timeline = $conn->prepare($sql_devolved_timeline);
if ($stmt_dev_timeline && (!empty($dev_conditions['params']) ? $stmt_dev_timeline->bind_param($dev_conditions['types'], ...$dev_conditions['params']) : true) && $stmt_dev_timeline->execute()) {
    $result = $stmt_dev_timeline->get_result();
    while ($row = $result->fetch_assoc()) $dashboard_data['devolved_items_timeline'][] = $row;
    $stmt_dev_timeline->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar timeline de itens devolvidos: " . $conn->error; }

$don_timeline_conditions = build_query_conditions_finalized($date_start, $date_end, $base_where_clauses_items, $base_params_items, $base_types_items, ['date_column' => 'dt.donation_date', 'ignore_general_date_filter' => true, 'ignore_general_status_filter' => true, 'custom_conditions' => ["dt.status = 'Doado'"]]);
$sql_donated_timeline = "SELECT DATE(dt.donation_date) as date, COUNT(dti.item_id) as count FROM donation_terms dt JOIN donation_term_items dti ON dt.term_id = dti.term_id JOIN items i ON dti.item_id = i.id {$don_timeline_conditions['sql']} GROUP BY DATE(dt.donation_date) ORDER BY date ASC";
$stmt_don_timeline = $conn->prepare($sql_donated_timeline);
if ($stmt_don_timeline && (!empty($don_timeline_conditions['params']) ? $stmt_don_timeline->bind_param($don_timeline_conditions['types'], ...$don_timeline_conditions['params']) : true) && $stmt_don_timeline->execute()) {
    $result = $stmt_don_timeline->get_result();
    while ($row = $result->fetch_assoc()) $dashboard_data['donated_items_timeline'][] = $row;
    $stmt_don_timeline->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar timeline de itens doados: " . $conn->error; }

// Gráfico de Doações por Instituição
// CORREÇÃO: A query foi alterada para buscar os dados da instituição na tabela 'companies'.
$sql_donations_institution = "
    SELECT 
        c.name AS institution_name, 
        c.cnpj AS institution_cnpj, 
        COUNT(dti.item_id) AS total_items 
    FROM donation_terms dt 
    JOIN donation_term_items dti ON dt.term_id = dti.term_id 
    JOIN items i ON dti.item_id = i.id 
    LEFT JOIN companies c ON dt.company_id = c.id 
    {$don_timeline_conditions['sql']} 
    GROUP BY c.id, c.name, c.cnpj 
    ORDER BY total_items DESC 
    LIMIT 10";
$stmt_don_inst = $conn->prepare($sql_donations_institution);
if ($stmt_don_inst && (!empty($don_timeline_conditions['params']) ? $stmt_don_inst->bind_param($don_timeline_conditions['types'], ...$don_timeline_conditions['params']) : true) && $stmt_don_inst->execute()) {
    $result = $stmt_don_inst->get_result();
    while ($row = $result->fetch_assoc()) {
        $dashboard_data['donations_by_institution'][] = $row;
    }
    $stmt_don_inst->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar doações por instituição: " . $conn->error; }


// REMOÇÃO: Bloco de cálculo antigo da taxa de devolução foi removido.
// REMOÇÃO: Bloco de cálculo do tempo médio de resolução foi removido.

// Gráfico de Top Categorias Pendentes
$pending_cats_conditions = build_query_conditions_finalized($date_start, $date_end, $base_where_clauses_items, $base_params_items, $base_types_items, ['fixed_status' => 'Pendente', 'ignore_general_status_filter' => true, 'date_column' => 'i.found_date', 'ignore_general_date_filter' => false]);
$sql_top_pending_cats = "SELECT c.name as category_name, COUNT(i.id) as total_pending FROM items i JOIN categories c ON i.category_id = c.id {$pending_cats_conditions['sql']} GROUP BY c.id, c.name ORDER BY total_pending DESC LIMIT 5";
$stmt_top_pending_cats = $conn->prepare($sql_top_pending_cats);
if ($stmt_top_pending_cats && (!empty($pending_cats_conditions['params']) ? $stmt_top_pending_cats->bind_param($pending_cats_conditions['types'], ...$pending_cats_conditions['params']) : true) && $stmt_top_pending_cats->execute()) {
    $result = $stmt_top_pending_cats->get_result();
    while ($row = $result->fetch_assoc()) $dashboard_data['top_pending_categories'][] = $row;
    $stmt_top_pending_cats->close();
} else { $dashboard_data['errors'][] = "Erro ao carregar top categorias pendentes: " . $conn->error; }

error_log('Dashboard Data (get_dashboard_data.php): ' . json_encode($dashboard_data));
echo json_encode($dashboard_data);

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>