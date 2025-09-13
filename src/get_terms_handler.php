<?php
require_once 'auth.php';
require_once 'db_connect.php';

// No need for require_login() here if it's an AJAX handler, 
// but let's keep it for security to ensure only logged-in users can access data.
require_login(); 

header('Content-Type: application/json');

const ITEMS_PER_PAGE = 10;

// ### FUNÇÃO DE PAGINAÇÃO COMPLETA ###
function render_pagination_links($current_page, $total_pages, $page_param_name, $container_id) {
    if ($total_pages <= 1) return '';

    // Note: The base URL doesn't matter as much since we'll use JS to handle clicks,
    // but we build it for consistency and potential non-JS fallbacks.
    $query_params = $_GET;
    $build_url = function($page) use ($query_params, $page_param_name) {
        $query_params[$page_param_name] = $page;
        return "?" . http_build_query($query_params);
    };

    $output = '';
    $range = 2;
    $potential_pages = [];
    for ($i = $current_page - $range; $i <= $current_page + $range; $i++) {
        if ($i > 0 && $i <= $total_pages) {
            $potential_pages[] = $i;
        }
    }

    if (!in_array(1, $potential_pages)) array_unshift($potential_pages, 1);
    if (!in_array($total_pages, $potential_pages)) $potential_pages[] = $total_pages;
    
    $potential_pages = array_unique($potential_pages);
    sort($potential_pages);

    $pages_to_render = [];
    $prev_page_num = 0;
    foreach ($potential_pages as $page_num) {
        if ($page_num > $prev_page_num + 1) {
            $pages_to_render[] = '...';
        }
        $pages_to_render[] = $page_num;
        $prev_page_num = $page_num;
    }

    if ($current_page > 1) {
        $output .= '<a href="'.$build_url(1).'" class="pagination-link" data-page="1" data-type="'.str_replace('page_', '', $page_param_name).'">Primeira</a>';
    }
    
    foreach ($pages_to_render as $page_item) {
        if ($page_item === '...') {
            $output .= '<span class="pagination-dots">...</span>';
        } else {
            $output .= ($page_item == $current_page) 
                ? '<span class="pagination-link current-page">'.$page_item.'</span>'
                : '<a href="'.$build_url($page_item).'" class="pagination-link" data-page="'.$page_item.'" data-type="'.str_replace('page_', '', $page_param_name).'">'.$page_item.'</a>';
        }
    }

    if ($current_page < $total_pages) {
        $output .= '<a href="'.$build_url($total_pages).'" class="pagination-link" data-page="'.$total_pages.'" data-type="'.str_replace('page_', '', $page_param_name).'">Última</a>';
    }
    
    return $output;
}

// --- LÓGICA PARA TERMOS DE DEVOLUÇÃO ---
$current_page_dev = filter_input(INPUT_GET, 'page_dev', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset_dev = ($current_page_dev - 1) * ITEMS_PER_PAGE;
$filter_owner_name = $_GET['filter_owner_name'] ?? '';
$filter_item_name_dev = $_GET['filter_item_name_dev'] ?? '';

$base_sql_dev = "FROM devolution_documents dd 
                 LEFT JOIN users u ON dd.returned_by_user_id = u.id 
                 LEFT JOIN items i ON dd.item_id = i.id";
$conditions_dev = [];
$params_dev = [];
$types_dev = "";

if (!empty($filter_owner_name)) { $conditions_dev[] = "dd.owner_name LIKE ?"; $params_dev[] = "%" . $filter_owner_name . "%"; $types_dev .= "s"; }
if (!empty($filter_item_name_dev)) { $conditions_dev[] = "i.name LIKE ?"; $params_dev[] = "%" . $filter_item_name_dev . "%"; $types_dev .= "s"; }

$where_clause_dev = !empty($conditions_dev) ? " WHERE " . implode(" AND ", $conditions_dev) : "";
$sql_count_dev = "SELECT COUNT(dd.id) AS total " . $base_sql_dev . $where_clause_dev;
$stmt_count_dev = $conn->prepare($sql_count_dev);
if ($stmt_count_dev && !empty($types_dev)) { $stmt_count_dev->bind_param($types_dev, ...$params_dev); }
if($stmt_count_dev) {
    $stmt_count_dev->execute();
    $total_devolution_terms = (int)$stmt_count_dev->get_result()->fetch_assoc()['total'];
    $stmt_count_dev->close();
} else { $total_devolution_terms = 0; }

$devolution_terms_list = [];
if ($total_devolution_terms > 0) {
    $sql_dev_terms = "SELECT dd.id, dd.devolution_timestamp, dd.owner_name, i.name AS item_name, u.full_name AS returned_by 
                      " . $base_sql_dev . $where_clause_dev . " 
                      ORDER BY dd.devolution_timestamp DESC LIMIT ? OFFSET ?";
    $stmt_dev_terms = $conn->prepare($sql_dev_terms);
    if ($stmt_dev_terms) {
        $types_full_dev = $types_dev . 'ii';
        $bind_params_dev = array_merge($params_dev, [ITEMS_PER_PAGE, $offset_dev]);
        $stmt_dev_terms->bind_param($types_full_dev, ...$bind_params_dev);
        $stmt_dev_terms->execute();
        $result_dev_terms = $stmt_dev_terms->get_result();
        while ($term = $result_dev_terms->fetch_assoc()) { $devolution_terms_list[] = $term; }
        $stmt_dev_terms->close();
    }
}
$total_pages_dev = $total_devolution_terms > 0 ? ceil($total_devolution_terms / ITEMS_PER_PAGE) : 1;

// --- RENDERIZAÇÃO HTML DE DEVOLUÇÃO ---
ob_start();
if (!empty($devolution_terms_list)): ?>
    <table class="admin-table" id="devolution_terms_table">
        <thead>
            <tr><th>ID</th><th>Data Devolução</th><th>Item Devolvido</th><th>Recebido por (Proprietário)</th><th>Entregue por (Usuário)</th><th>Ações</th></tr>
        </thead>
        <tbody>
            <?php foreach ($devolution_terms_list as $term): ?>
                <tr onclick="window.location.href='manage_devolutions.php?view_id=<?php echo htmlspecialchars($term['id']); ?>';" style="cursor: pointer;">
                    <td><?php echo htmlspecialchars($term['id']); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($term['devolution_timestamp']))); ?></td>
                    <td><?php echo htmlspecialchars($term['item_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($term['owner_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($term['returned_by'] ?? 'N/A'); ?></td>
                    <td class="actions-cell">
                        <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($term['id']); ?>" class="action-icon" data-tooltip="Ver Detalhes">
                            <i class="fa-solid fa-file-lines"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="info-message">Nenhum termo de devolução encontrado para os filtros selecionados.</p>
<?php endif;
$devolution_table_html = ob_get_clean();
$devolution_pagination_html = render_pagination_links($current_page_dev, $total_pages_dev, 'page_dev', 'devolution_pagination');


// --- LÓGICA PARA TERMOS DE DOAÇÃO ---
$current_page_don = filter_input(INPUT_GET, 'page_don', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset_don = ($current_page_don - 1) * ITEMS_PER_PAGE;
$filter_status = $_GET['filter_status'] ?? '';
$filter_company = $_GET['filter_company'] ?? '';
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';

$base_sql_don = "FROM donation_terms dt 
                 LEFT JOIN users u ON dt.user_id = u.id 
                 LEFT JOIN companies c ON dt.company_id = c.id";
$conditions_don = [];
$params_don = [];
$types_don = "";

if (!empty($filter_status)) { $conditions_don[] = "dt.status = ?"; $params_don[] = $filter_status; $types_don .= "s"; }
if (!empty($filter_company)) { $conditions_don[] = "dt.company_id = ?"; $params_don[] = $filter_company; $types_don .= "i"; }
if (!empty($filter_start_date)) { $conditions_don[] = "DATE(dt.created_at) >= ?"; $params_don[] = $filter_start_date; $types_don .= "s"; }
if (!empty($filter_end_date)) { $conditions_don[] = "DATE(dt.created_at) <= ?"; $params_don[] = $filter_end_date; $types_don .= "s"; }

$where_clause_don = !empty($conditions_don) ? " WHERE " . implode(" AND ", $conditions_don) : "";
$sql_count_don = "SELECT COUNT(dt.term_id) AS total " . $base_sql_don . $where_clause_don;
$stmt_count_don = $conn->prepare($sql_count_don);
if ($stmt_count_don && !empty($types_don)) { $stmt_count_don->bind_param($types_don, ...$params_don); }
if($stmt_count_don) {
    $stmt_count_don->execute();
    $total_donation_terms = (int)$stmt_count_don->get_result()->fetch_assoc()['total'];
    $stmt_count_don->close();
} else { $total_donation_terms = 0; }

$donation_terms_list = [];
if ($total_donation_terms > 0) {
    $sql_don_terms = "SELECT dt.term_id, dt.created_at, dt.status, u.full_name AS registered_by, c.name AS company_name 
                      " . $base_sql_don . $where_clause_don . " 
                      ORDER BY dt.created_at DESC LIMIT ? OFFSET ?";
    $stmt_don_terms = $conn->prepare($sql_don_terms);
    if ($stmt_don_terms) {
        $types_full = $types_don . 'ii';
        $bind_params_don = array_merge($params_don, [ITEMS_PER_PAGE, $offset_don]);
        $stmt_don_terms->bind_param($types_full, ...$bind_params_don);
        $stmt_don_terms->execute();
        $result_don_terms = $stmt_don_terms->get_result();
        while ($term = $result_don_terms->fetch_assoc()) { $donation_terms_list[] = $term; }
        $stmt_don_terms->close();
    }
}
$total_pages_don = $total_donation_terms > 0 ? ceil($total_donation_terms / ITEMS_PER_PAGE) : 1;

// --- RENDERIZAÇÃO HTML DE DOAÇÃO ---
ob_start();
if (!empty($donation_terms_list)): ?>
    <table class="admin-table" id="donation_terms_table">
        <thead>
            <tr><th>ID</th><th>Data Criação</th><th>Instituição</th><th>Status</th><th>Registrado Por</th><th>Ações</th></tr>
        </thead>
        <tbody>
            <?php foreach ($donation_terms_list as $term): ?>
                <tr onclick="window.location.href='view_donation_term_page.php?id=<?php echo htmlspecialchars($term['term_id']); ?>';" style="cursor: pointer;">
                    <td><?php echo htmlspecialchars($term['term_id']); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($term['created_at']))); ?></td>
                    <td><?php echo htmlspecialchars($term['company_name'] ?? 'N/A'); ?></td>
                    <td>
                        <?php
                            $status_text = $term['status'] ?? 'desconhecido';
                            $status_map = ['Em aprovação' => 'em-aprovacao', 'Aprovado' => 'aprovado', 'Negado' => 'negado', 'Doado' => 'doado'];
                            $status_slug = $status_map[$status_text] ?? 'desconhecido';
                        ?>
                        <span class="item-status status-<?php echo $status_slug; ?>">
                            <?php echo htmlspecialchars($status_text); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($term['registered_by'] ?? 'N/A'); ?></td>
                    <td class="actions-cell">
                        <a href="view_donation_term_page.php?id=<?php echo htmlspecialchars($term['term_id']); ?>" class="action-icon" data-tooltip="Ver Detalhes">
                            <i class="fa-solid fa-file-lines"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="info-message">Nenhum termo de doação encontrado para os filtros selecionados.</p>
<?php endif;
$donation_table_html = ob_get_clean();
$donation_pagination_html = render_pagination_links($current_page_don, $total_pages_don, 'page_don', 'donation_pagination');


// --- RESPOSTA JSON ---
echo json_encode([
    'devolution_table' => $devolution_table_html,
    'devolution_pagination' => $devolution_pagination_html,
    'donation_table' => $donation_table_html,
    'donation_pagination' => $donation_pagination_html,
]);

$conn->close();
?>
