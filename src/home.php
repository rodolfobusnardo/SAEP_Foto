<?php
// A sessão agora é iniciada pelo 'auth.php' (incluído no header), então a linha session_start() foi removida daqui.

require_once 'auth.php';
require_once 'db_connect.php';

require_login();

$pageTitle = "Itens Encontrados";

// --- Bloco de verificação de permissão (Segunda camada de segurança) ---
if (isset($_SESSION['role']) && $_SESSION['role'] === 'common') {
    // Redireciona para a página principal permitida para este usuário
    header('Location: register_item_page.php?error=' . urlencode('Acesso não permitido.'));
    exit(); // Encerra o script para garantir que o redirecionamento ocorra.
}
// --- FIM do bloco ---

// Busca categorias para os filtros
$filter_categories = [];
$sql_filter_cats = "SELECT id, name FROM categories ORDER BY name ASC";
$result_filter_cats = $conn->query($sql_filter_cats);
if ($result_filter_cats && $result_filter_cats->num_rows > 0) {
    while ($row_fc = $result_filter_cats->fetch_assoc()) {
        $filter_categories[] = $row_fc;
    }
}

// Busca locais para os filtros
$filter_locations = [];
$sql_filter_locs = "SELECT id, name FROM locations ORDER BY name ASC";
$result_filter_locs = $conn->query($sql_filter_locs);
if ($result_filter_locs && $result_filter_locs->num_rows > 0) {
    while ($row_fl = $result_filter_locs->fetch_assoc()) {
        $filter_locations[] = $row_fl;
    }
}

// Inclui o manipulador de itens para a carga inicial da página
require_once 'get_items_handler.php';

$current_user_is_admin = is_admin();

require_once 'templates/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlalHpgO7oR/7X7k9+4o0p4l+7g4+1z6l5r5j5O6p6u+2r5z4b5+9z5o3l5p5u5w5t5v5u5w==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<div class="container home-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>

    <?php
    if (isset($_GET['message_type']) && isset($_GET['message'])) {
        $message_type = htmlspecialchars($_GET['message_type']);
        $message = htmlspecialchars($_GET['message']);
        if ($message_type === 'success') {
            echo '<p class="success-message">' . $message . '</p>';
        } elseif ($message_type === 'error') {
            echo '<p class="error-message">' . $message . '</p>';
        }
    }
    ?>

    <form id="filterForm" class="form-filters">
        <div class="filter-group top-filters">
            <div>
                <label for="filter_item_name">Nome do Item (contém):</label>
                <input type="text" id="filter_item_name" name="filter_item_name" value="<?php echo htmlspecialchars($_GET['filter_item_name'] ?? ''); ?>" placeholder="Digite parte do nome...">
            </div>
            <div>
                <label for="filter_category_id">Categoria:</label>
                <select id="filter_category_id" name="filter_category_id">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($filter_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_GET['filter_category_id']) && $_GET['filter_category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status">Status do Item:</label>
                <select id="filter_status" name="filter_status">
                    <option value="" <?php echo (!isset($_GET['filter_status']) || $_GET['filter_status'] == '') ? 'selected' : ''; ?>>Todos os Status</option>
                    <option value="Pendente" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                    <option value="Devolvido" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Devolvido') ? 'selected' : ''; ?>>Devolvido</option>
                    <option value="Doado" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Doado') ? 'selected' : ''; ?>>Doado</option>
                    <option value="Descartado" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Descartado') ? 'selected' : ''; ?>>Descartado</option>
                    <option value="Em Aprovação" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Em Aprovação') ? 'selected' : ''; ?>>Em Aprovação</option>
                    <option value="Aprovado" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Aprovado') ? 'selected' : ''; ?>>Aprovado</option>
                </select>
            </div>
            <div>
                <label for="filter_barcode">Código de Barras:</label>
                <input type="text" id="filter_barcode" name="filter_barcode" value="<?php echo htmlspecialchars($_GET['filter_barcode'] ?? ''); ?>" placeholder="Digite o código de barras...">
            </div>
        </div>
        <div class="filter-group bottom-filters">
            <div>
                <label for="filter_location_id">Local:</label>
                <select id="filter_location_id" name="filter_location_id">
                    <option value="">Todos os Locais</option>
                    <?php foreach ($filter_locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location['id']); ?>" <?php echo (isset($_GET['filter_location_id']) && $_GET['filter_location_id'] == $location['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_days_waiting">Tempo Aguardando:</label>
                <select id="filter_days_waiting" name="filter_days_waiting">
                    <option value="">Qualquer</option>
                    <option value="0-7" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '0-7') ? 'selected' : ''; ?>>0-7 dias</option>
                    <option value="8-30" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '8-30') ? 'selected' : ''; ?>>8-30 dias</option>
                    <option value="31-9999" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '31-9999') ? 'selected' : ''; ?>>31+ dias</option>
                </select>
            </div>
            <div>
                <label for="filter_found_date_start">Achado de (data):</label>
                <input type="date" id="filter_found_date_start" name="filter_found_date_start" value="<?php echo htmlspecialchars($_GET['filter_found_date_start'] ?? ''); ?>">
            </div>
            <div>
                <label for="filter_found_date_end">Até (data):</label>
                <input type="date" id="filter_found_date_end" name="filter_found_date_end" value="<?php echo htmlspecialchars($_GET['filter_found_date_end'] ?? ''); ?>">
            </div>
        </div>
        <div class="filter-buttons">
            <button type="submit" class="button-filter"><i class="fas fa-check"></i><span>Aplicar Filtros</span></button>
            <button type="reset" id="clearFiltersButton" class="button-filter-clear"><i class="fas fa-broom"></i><span>Limpar Filtros</span></button>
        </div>
    </form>
    <hr>
    
    <div class="table-header-controls">
        <?php if ($current_user_is_admin): ?>
        <div class="action-bar">
            <?php
            $filter_keys = ['filter_item_name', 'filter_barcode', 'filter_category_id', 'filter_status', 'filter_location_id', 'filter_days_waiting', 'filter_found_date_start', 'filter_found_date_end'];
            $is_filter_active = false;
            foreach ($filter_keys as $key) {
                if (!empty($_GET[$key])) {
                    $is_filter_active = true;
                    break;
                }
            }
            $tooltip_attr = '';
            $checkbox_attrs = '';
            if (!$is_filter_active) {
                $tooltip_attr = 'data-tooltip="Para fazer uso desta funcionalidade, primeiro aplique algum filtro.<br>(Por exemplo: selecione e aplique o filtro \'Pendente\')."';
                $checkbox_attrs = 'disabled';
            }
            ?>
            
            <span class="tooltip-wrapper" <?php echo $tooltip_attr; ?>>
                <input type="checkbox" id="selectFilteredCheckbox" <?php echo $checkbox_attrs; ?>>
                <label for="selectFilteredCheckbox">Selecionar Itens Filtrados</label>
            </span>

            <button id="devolverButton" class="button-secondary" disabled>Devolver Selecionados</button>
            <button id="doarButton" class="button-secondary" disabled>Doar Selecionados</button>
            <button id="descartarButton" class="button-secondary" disabled>Descartar Selecionados</button>
            <button id="imprimirCodBarrasButton" class="button-secondary" disabled>Imprimir Cód. Barras</button>
        </div>
        <?php endif; ?>

        <div id="pagination-top" class="pagination"></div>
    </div>

    <div id="itemListContainer"></div>

    <div id="table-footer" class="table-footer-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
        <div id="item-count-container" class="item-count-info"></div>
        <div id="pagination-bottom" class="pagination"></div>
    </div>
</div>

<div id="itemDetailModal" class="modal" style="display: none;"></div>
<div id="global-tooltip" style="display: none;"></div>

<script>
// Passa as variáveis do PHP para o JS para a carga inicial
const initial_php_items = <?php echo json_encode($items); ?>;
const initialTotalItems = <?php echo (int)($total_items ?? 0); ?>;
const initialTotalPages = <?php echo (int)($total_pages ?? 1); ?>;
const initialCurrentPage = <?php echo (int)($current_page ?? 1); ?>;
const current_user_is_admin = <?php echo json_encode($current_user_is_admin); ?>;
</script>

<?php require_once 'templates/footer.php'; ?>