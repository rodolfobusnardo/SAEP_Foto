<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_login();

$page_title = "Gerenciar Termos";
const ITEMS_PER_PAGE = 10;

// ### FUNÇÃO DE PAGINAÇÃO COMPLETA ###
function render_pagination_links($current_page, $total_pages, $page_param_name) {
    if ($total_pages <= 1) return;

    $query_params = $_GET;
    $build_url = function($page) use ($query_params, $page_param_name) {
        $query_params[$page_param_name] = $page;
        return htmlspecialchars("manage_terms.php?" . http_build_query($query_params));
    };

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

    if ($current_page > 1) echo '<a href="'.$build_url(1).'" class="pagination-link" data-page="1">Primeira</a>';
    
    foreach ($pages_to_render as $page_item) {
        if ($page_item === '...') {
            echo '<span class="pagination-dots">...</span>';
        } else {
            echo ($page_item == $current_page) 
                ? '<span class="pagination-link current-page">'.$page_item.'</span>'
                : '<a href="'.$build_url($page_item).'" class="pagination-link" data-page="'.$page_item.'">'.$page_item.'</a>';
        }
    }

    if ($current_page < $total_pages) echo '<a href="'.$build_url($total_pages).'" class="pagination-link" data-page="'.$total_pages.'">Última</a>';
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


// --- LÓGICA PARA TERMOS DE DOAÇÃO ---
$companies = [];
$sql_companies = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name ASC";
$result_companies = $conn->query($sql_companies);
if ($result_companies) { while ($row = $result_companies->fetch_assoc()) { $companies[] = $row; } }
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

require_once 'templates/header.php';
?>

<style>
    .admin-container h3 { color: #007bff; margin-top: 20px; margin-bottom: 20px; }
    .form-filters { background-color: #f9f9f9; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #ddd; }
    .form-filters .filter-group { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
    .form-filters .filter-group > div { flex: 1 1 auto; min-width: 180px; }
    .filter-buttons { display: flex; gap: 10px; flex-grow: 0; flex-shrink: 0; }
    .form-control, .select2-container .select2-selection--single { height: 38px !important; box-sizing: border-box !important; }
    .select2-container .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
    .select2-container .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .admin-table { width: 100%; border-collapse: collapse; margin-top: 20px; /* ### CORREÇÃO: Adicionada margem inferior ### */ margin-bottom: 10px; table-layout: fixed; }
    .admin-table th, .admin-table td { border: 1px solid #ddd; padding: 8px 12px; text-align: center; vertical-align: middle; word-wrap: break-word; }
    .admin-table th { background-color: #007bff; color: #fff; font-weight: bold; }
    .admin-table tr:nth-child(even) { background-color: #f9f9f9; }
    .admin-table tr:hover { background-color: #f1f1f1; }
    #devolution_terms_table th:nth-child(1), #devolution_terms_table td:nth-child(1) { width: 5%; }
    #devolution_terms_table th:nth-child(2), #devolution_terms_table td:nth-child(2) { width: 15%; }
    #devolution_terms_table th:nth-child(3), #devolution_terms_table td:nth-child(3) { width: 25%; }
    #devolution_terms_table th:nth-child(4), #devolution_terms_table td:nth-child(4) { width: 25%; }
    #devolution_terms_table th:nth-child(5), #devolution_terms_table td:nth-child(5) { width: 20%; }
    #devolution_terms_table th:nth-child(6), #devolution_terms_table td:nth-child(6) { width: 10%; }
    #donation_terms_table th:nth-child(1), #donation_terms_table td:nth-child(1) { width: 5%; }
    #donation_terms_table th:nth-child(2), #donation_terms_table td:nth-child(2) { width: 15%; }
    #donation_terms_table th:nth-child(3), #donation_terms_table td:nth-child(3) { width: 30%; }
    #donation_terms_table th:nth-child(4), #donation_terms_table td:nth-child(4) { width: 15%; }
    #donation_terms_table th:nth-child(5), #donation_terms_table td:nth-child(5) { width: 25%; }
    #donation_terms_table th:nth-child(6), #donation_terms_table td:nth-child(6) { width: 10%; }
    .item-status { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 0.85em; font-weight: bold; text-align: center; line-height: 1.3; border: 1px solid transparent; white-space: nowrap; min-width: 110px; box-sizing: border-box; }
    .status-doado { background-color: #cce5ff; color: #004085; border-color: #b8daff; }
    .status-aprovado { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .status-negado { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .status-em-aprovacao { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    /* ### CORREÇÃO: Espaçamento da paginação aumentado ### */
    .pagination { display: flex; justify-content: flex-end; align-items: center; margin-top: 25px; gap: 8px; }
    .pagination-link { padding: 8px 14px; border: 1px solid #ddd; color: #007bff; text-decoration: none; border-radius: 4px; }
    .pagination-link.current-page { background-color: #007bff; color: white; border-color: #007bff; }
    .pagination-link.disabled { color: #6c757d; pointer-events: none; }
    .pagination-dots { padding: 8px 0; }
</style>

<div class="container admin-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </header>
    
    <h3>Termos de Devolução</h3>
    <form method="GET" action="manage_terms.php" class="form-filters" id="devolution-filters-form">
        <div class="filter-group">
            <div>
                <label for="filter_owner_name">Nome do Proprietário</label>
                <input type="text" id="filter_owner_name" placeholder="Buscar por nome..." name="filter_owner_name" value="<?php echo htmlspecialchars($filter_owner_name); ?>" class="form-control">
            </div>
            <div>
                <label for="filter_item_name_dev">Nome do Item</label>
                <input type="text" id="filter_item_name_dev" placeholder="Buscar por item..." name="filter_item_name_dev" value="<?php echo htmlspecialchars($filter_item_name_dev); ?>" class="form-control">
            </div>
            <div class="filter-buttons">
                <button type="submit" class="button-filter"><i class="fas fa-check"></i><span>Filtrar</span></button>
                <button type="button" id="clear-devolution-filters" class="button-filter-clear"><i class="fas fa-broom"></i><span>Limpar</span></button>
            </div>
        </div>
    </form>

    <div id="devolution-terms-content">
        <?php if (!empty($devolution_terms_list)): ?>
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
            <div class="pagination" id="devolution_pagination">
                <?php render_pagination_links($current_page_dev, $total_pages_dev, 'page_dev'); ?>
            </div>
        <?php else: ?>
            <p class="info-message">Nenhum termo de devolução encontrado para os filtros selecionados.</p>
        <?php endif; ?>
    </div>

    <hr style="margin: 40px 0;">

    <h3>Termos de Doação</h3>
    <form method="GET" action="manage_terms.php" class="form-filters" id="donation-filters-form">
        <div class="filter-group">
            <div>
                 <label for="filter_status">Status do Termo</label>
                <select id="filter_status" name="filter_status" class="form-control">
                    <option value="">Todos os Status</option>
                    <option value="Em aprovação" <?php if ($filter_status === 'Em aprovação') echo 'selected'; ?>>Em aprovação</option>
                    <option value="Aprovado" <?php if ($filter_status === 'Aprovado') echo 'selected'; ?>>Aprovado</option>
                    <option value="Negado" <?php if ($filter_status === 'Negado') echo 'selected'; ?>>Negado</option>
                    <option value="Doado" <?php if ($filter_status === 'Doado') echo 'selected'; ?>>Doado</option>
                </select>
            </div>
            <div>
                <label for="filter_company">Instituição</label>
                <select id="filter_company" name="filter_company" class="form-control">
                    <option value="">Todas as Instituições</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>" <?php if ($filter_company == $company['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($company['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_start_date">Data Inicial</label>
                <input type="date" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" class="form-control">
            </div>
            <div>
                <label for="filter_end_date">Data Final</label>
                <input type="date" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" class="form-control">
            </div>
             <div class="filter-buttons">
                <button type="submit" class="button-filter"><i class="fas fa-check"></i><span>Filtrar</span></button>
                <button type="button" id="clear-donation-filters" class="button-filter-clear"><i class="fas fa-broom"></i><span>Limpar</span></button>
            </div>
        </div>
    </form>

    <div id="donation-terms-content">
        <?php if (!empty($donation_terms_list)): ?>
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
            <div class="pagination" id="donation_pagination">
                <?php render_pagination_links($current_page_don, $total_pages_don, 'page_don'); ?>
            </div>
        <?php else: ?>
            <p class="info-message">Nenhum termo de doação encontrado para os filtros selecionados.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
<script>
$(document).ready(function() {
    // Initialize Select2
    $('#filter_company').select2({
        placeholder: "Todas as Instituições",
        allowClear: true
    });

    // --- AJAX for Terms Management ---

    const devolutionForm = $('#devolution-filters-form');
    const donationForm = $('#donation-filters-form');

    function fetchTerms(params) {
        // Show loading state
        $('#devolution-terms-content').html('<p class="info-message">Carregando...</p>');
        $('#donation-terms-content').html('<p class="info-message">Carregando...</p>');

        const newUrl = 'manage_terms.php?' + params.toString();
        history.pushState({path: newUrl}, '', newUrl);

        $.ajax({
            url: 'get_terms_handler.php',
            type: 'GET',
            data: params.toString(),
            dataType: 'json',
            success: function(response) {
                // Build and inject devolution content
                let devolutionHtml = response.devolution_table;
                if (response.devolution_pagination) {
                    devolutionHtml += `<div class="pagination" id="devolution_pagination">${response.devolution_pagination}</div>`;
                }
                $('#devolution-terms-content').html(devolutionHtml);

                // Build and inject donation content
                let donationHtml = response.donation_table;
                if (response.donation_pagination) {
                    donationHtml += `<div class="pagination" id="donation_pagination">${response.donation_pagination}</div>`;
                }
                $('#donation-terms-content').html(donationHtml);
            },
            error: function() {
                $('#devolution-terms-content').html('<p class="error-message">Ocorreu um erro ao carregar os termos de devolução.</p>');
                $('#donation-terms-content').html('<p class="error-message">Ocorreu um erro ao carregar os termos de doação.</p>');
            }
        });
    }

    // --- Utility Functions ---
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    const debouncedFetch = debounce(fetchTerms, 400);

    // --- Event Listeners ---

    devolutionForm.on('submit', function(e) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        new URLSearchParams($(this).serialize()).forEach((value, key) => params.set(key, value));
        params.set('page_dev', 1); // Reset page on new filter
        fetchTerms(params);
    });

    donationForm.on('submit', function(e) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        new URLSearchParams($(this).serialize()).forEach((value, key) => params.set(key, value));
        params.set('page_don', 1); // Reset page on new filter
        fetchTerms(params);
    });

    // Live search for text inputs
    $('#filter_owner_name, #filter_item_name_dev').on('keyup', function() {
        const params = new URLSearchParams(window.location.search);
        
        // Get data from both forms to build the correct query string
        const devParams = new URLSearchParams(devolutionForm.serialize());
        const donParams = new URLSearchParams(donationForm.serialize());

        devParams.forEach((value, key) => params.set(key, value));
        donParams.forEach((value, key) => params.set(key, value));

        params.set('page_dev', 1); // Reset page on live search
        params.set('page_don', 1); // Also reset donation page to avoid confusion

        debouncedFetch(params);
    });

    $('#clear-devolution-filters').on('click', function() {
        devolutionForm[0].reset();
        devolutionForm.trigger('submit');
    });

    $('#clear-donation-filters').on('click', function() {
        donationForm[0].reset();
        $('#filter_company').val(null).trigger('change'); // Also reset select2
        donationForm.trigger('submit');
    });

    // Pagination (using event delegation for dynamically loaded content)
    $(document).on('click', '#devolution_pagination .pagination-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (!page) return;

        const params = new URLSearchParams(window.location.search);
        params.set('page_dev', page);
        fetchTerms(params);
    });

    $(document).on('click', '#donation_pagination .pagination-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (!page) return;

        const params = new URLSearchParams(window.location.search);
        params.set('page_don', page);
        fetchTerms(params);
    });

    // --- Toast Notification Handler ---
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success_message');
    if (successMessage) {
        showToast(successMessage, 'success');
        // Remove the parameter from the URL so the toast doesn't reappear on refresh
        urlParams.delete('success_message');
        const newUrl = window.location.pathname + '?' + urlParams.toString();
        history.replaceState({}, '', newUrl);
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>