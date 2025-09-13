<?php
// File: src/admin/manage_users.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
// CORREÇÃO FINAL: Alterado para require_super_admin() para restringir o acesso apenas a este perfil.
require_super_admin();

$page_title = "Gerenciamento de Usuários";

// Mapeamento de 'roles' para nomes amigáveis para o filtro
$roles = [
    'common' => 'Comum',
    'admin' => 'Admin',
    'admin-aprovador' => 'Admin Aprovador',
    'superAdmin' => 'SuperAdmin'
];

// ==================================================================
// LÓGICA DE PAGINAÇÃO E FILTRO
// ==================================================================
const ITEMS_PER_PAGE = 10;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($current_page - 1) * ITEMS_PER_PAGE;

$filter_role = filter_input(INPUT_GET, 'filter_role', FILTER_SANITIZE_SPECIAL_CHARS);
$filter_search = trim(filter_input(INPUT_GET, 'filter_search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

$users = [];
$total_users = 0;
$base_sql = "FROM users";
$conditions = [];
$params = [];
$types = "";

if (!empty($filter_role) && array_key_exists($filter_role, $roles)) {
    $conditions[] = "role = ?";
    $params[] = $filter_role;
    $types .= "s";
}

if (!empty($filter_search)) {
    $search_term = "%" . $filter_search . "%";
    $conditions[] = "(username LIKE ? OR full_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

$sql_count = "SELECT COUNT(id) as total " . $base_sql . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    if (!empty($types)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_users = (int)$stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();
}

$total_pages = $total_users > 0 ? ceil($total_users / ITEMS_PER_PAGE) : 1;
if ($current_page > $total_pages) { $current_page = $total_pages; }
if ($total_users > 0 && $offset >= $total_users) { $offset = max(0, $total_users - ITEMS_PER_PAGE); }

if ($total_users > 0) {
    $sql_users = "SELECT id, username, full_name, role " . $base_sql . $where_clause . " ORDER BY username ASC LIMIT ? OFFSET ?";
    $types .= "ii";
    $params_with_pagination = array_merge($params, [ITEMS_PER_PAGE, $offset]);
    $stmt_users = $conn->prepare($sql_users);
    if ($stmt_users) {
        $stmt_users->bind_param($types, ...$params_with_pagination);
        $stmt_users->execute();
        $result_users = $stmt_users->get_result();
        $users = $result_users->fetch_all(MYSQLI_ASSOC);
        $stmt_users->close();
    }
}

function render_pagination_links($current_page, $total_pages) {
    if ($total_pages <= 1) return;
    $query_params = $_GET;
    $range = 2; $pages_to_render = []; $potential_pages = [1, $total_pages];
    for ($i = $current_page - $range; $i <= $current_page + $range; $i++) { if ($i > 1 && $i < $total_pages) $potential_pages[] = $i; }
    sort($potential_pages); $potential_pages = array_unique($potential_pages);
    $prev_page = 0;
    foreach ($potential_pages as $p) { if ($p > $prev_page + 1) $pages_to_render[] = '...'; $pages_to_render[] = $p; $prev_page = $p; }
    $build_url = function($page) use ($query_params) { $query_params['page'] = $page; return htmlspecialchars("manage_users.php?" . http_build_query($query_params)); };
    if ($current_page > 1) echo '<a href="'.$build_url(1).'" class="pagination-link">Primeira</a>'; else echo '<span class="pagination-link disabled">Primeira</span>';
    foreach ($pages_to_render as $item) { if ($item === '...') echo '<span class="pagination-dots">...</span>'; elseif ($item == $current_page) echo '<span class="pagination-link current-page">'.$item.'</span>'; else echo '<a href="'.$build_url($item).'" class="pagination-link">'.$item.'</a>'; }
    if ($current_page < $total_pages) echo '<a href="'.$build_url($total_pages).'" class="pagination-link">Última</a>'; else echo '<span class="pagination-link disabled">Última</span>';
}

require_once '../templates/header.php';
?>

<style>
    .admin-section-box { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px 24px; margin-bottom: 30px; }
    .admin-section-box h3 { margin-top: 0; margin-bottom: 15px; text-align: left; font-weight: bold; color: #495057; }
    .form-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 15px; }
    .form-group { flex: 1; min-width: 180px; display: flex; flex-direction: column; }
    .form-group label { margin-bottom: 5px; font-size: 14px; color: #495057; }
    .form-group input, .form-group select { width: 100%; box-sizing: border-box; padding: 8px; height: 38px; border: 1px solid #ccc; border-radius: 5px; }
    .form-group button { height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .filter-buttons-group { display: flex; gap: 8px; flex: 0 1 auto; }
    .actions-cell { text-align: center; vertical-align: middle; }
    .actions-cell .actions-wrapper { display: flex !important; flex-direction: column !important; gap: 8px !important; align-items: center !important; justify-content: center; }
    .actions-cell .button-action { display: inline-flex; align-items: center; justify-content: center; width: 100px; max-width: 100%; gap: 5px; padding: 6px 12px; border-radius: 4px; border: none; color: white; font-weight: bold; font-size: 0.9em; text-decoration: none; cursor: pointer; }
    .button-action.edit { background-color: #007bff; }
    .button-action.delete { background-color: #dc3545; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; position: relative; }
    .modal-close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 20px; }
    .modal-close-button:hover, .modal-close-button:focus { color: black; text-decoration: none; cursor: pointer; }
    .modal-content h3 { text-align: left; }
    .modal-form .form-group { margin-bottom: 15px; }
    .modal-form .form-actions { display: flex; justify-content: center; gap: 15px; margin-top: 20px; }
    .modal-form .form-actions button { min-width: 140px; padding: 10px; }
</style>

<div class="container admin-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </header>

    <?php
    // --- BLOCO PARA DISPARAR MENSAGENS DA SESSÃO E GET COMO TOASTS ---
    if (isset($_GET['success'])) {
        $success_messages = [
            'useradded' => 'Novo usuário adicionado com sucesso!',
            'passwordreset' => 'Senha do usuário redefinida com sucesso!',
            'userupdated' => 'Usuário atualizado com sucesso!',
            'userdeleted' => 'Usuário excluído com sucesso!'
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($success_messages[$_GET['success']]) . "', 'success'); });</script>";
        }
    }
    ?>

    <div class="admin-section-box">
        <h3>Registrar Novo Usuário</h3>
        <form action="user_management_handler.php" method="POST">
            <input type="hidden" name="action" value="register_user">
            <div class="form-row">
                <div class="form-group">
                    <label for="username_reg">Usuário:</label>
                    <input type="text" id="username_reg" name="username" required>
                </div>
                <div class="form-group">
                    <label for="full_name_reg">Nome Completo:</label>
                    <input type="text" id="full_name_reg" name="full_name" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="role_reg">Função:</label>
                    <select id="role_reg" name="role" required>
                        <?php foreach($roles as $role_val => $role_name): ?>
                            <option value="<?= htmlspecialchars($role_val) ?>"><?= htmlspecialchars($role_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="button-primary"><i class="fa-solid fa-plus"></i> Registrar</button>
                </div>
            </div>
        </form>
    </div>
    
    <div class="admin-section-box">
        <h3>Filtros</h3>
        <form method="GET" action="manage_users.php">
             <div class="form-row">
                <div class="form-group">
                    <label for="filter_search">Buscar por Nome/Usuário:</label>
                    <input type="text" id="filter_search" name="filter_search" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Digite para buscar...">
                </div>
                <div class="form-group">
                    <label for="filter_role">Filtrar por Função:</label>
                    <select id="filter_role" name="filter_role">
                        <option value="">Todas as Funções</option>
                        <?php foreach($roles as $role_val => $role_name): ?>
                            <option value="<?= htmlspecialchars($role_val) ?>" <?= ($filter_role == $role_val) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-buttons-group">
                    <button type="submit" class="button-primary"><i class="fa-solid fa-check"></i> Filtrar</button>
                    <a href="manage_users.php" class="button-secondary"><i class="fa-solid fa-broom"></i> Limpar</a>
                </div>
            </div>
        </form>
    </div>

    <h3 style="text-align: center; margin-bottom: 20px;">Lista de Usuários</h3>

    <?php if (!empty($users)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th><th>Usuário</th><th>Nome Completo</th><th>Função</th><th class="actions-cell">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id']); ?></td>
                <td><?= htmlspecialchars($user['username']); ?></td>
                <td><?= htmlspecialchars(!empty($user['full_name']) ? $user['full_name'] : 'N/A'); ?></td>
                <td><?= htmlspecialchars($roles[$user['role']] ?? $user['role']); ?></td>
                <td class="actions-cell">
                    <div class="actions-wrapper">
                        <button class="button-action edit" 
                            data-userid="<?= $user['id'] ?>"
                            data-username="<?= htmlspecialchars($user['username']) ?>"
                            data-fullname="<?= htmlspecialchars($user['full_name']) ?>"
                            data-role="<?= $user['role'] ?>">
                            <i class="fa-solid fa-edit"></i> Editar
                        </button>
                        
                        <?php if ($user['username'] !== 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                            <form action="user_management_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja EXCLUIR este usuário? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="button-action delete"><i class="fa-solid fa-trash"></i> Excluir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="table-footer-container">
        <div class="item-count-info">
            <span>Exibindo <strong><?= count($users); ?></strong> de <strong><?= $total_users; ?></strong> usuários.</span>
        </div>
        <div class="pagination">
            <?php render_pagination_links($current_page, $total_pages); ?>
        </div>
    </div>

    <?php else: ?>
    <p style="text-align: center;">Nenhum usuário encontrado com os filtros atuais.</p>
    <?php endif; ?>
</div>

<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="modal-close-button">&times;</span>
        <h3>Editar Usuário: <strong id="modalUsername"></strong></h3>
        <form id="editUserForm" action="user_management_handler.php" method="POST" class="modal-form">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" id="modalUserId" name="user_id">

            <div class="form-group">
                <label for="modalUsernameInput">Usuário (Login):</label>
                <input type="text" id="modalUsernameInput" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="modalFullName">Nome Completo:</label>
                <input type="text" id="modalFullName" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="modalRole">Função:</label>
                <select id="modalRole" name="role" required>
                       <?php foreach($roles as $role_val => $role_name): ?>
                           <option value="<?= htmlspecialchars($role_val) ?>"><?= htmlspecialchars($role_name) ?></option>
                       <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="button-secondary modal-cancel-button">Cancelar</button>
                <button type="submit" class="button-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
const initialTotalItems = <?php echo (int)($total_users ?? 0); ?>;
const initial_php_items = <?php echo json_encode($users ?? []); ?>;
const current_user_is_admin = <?php echo json_encode($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superAdmin'); ?>;
const initialTotalPages = <?php echo (int)($total_pages ?? 1); ?>;
const initialCurrentPage = <?php echo (int)($current_page ?? 1); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editUserModal');
    const closeButton = modal.querySelector('.modal-close-button');
    const cancelButton = modal.querySelector('.modal-cancel-button');
    
    const closeModal = () => {
        modal.style.display = 'none';
    };

    closeButton.addEventListener('click', closeModal);
    cancelButton.addEventListener('click', closeModal);
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            closeModal();
        }
    });

    document.querySelectorAll('.button-action.edit').forEach(button => {
        button.addEventListener('click', function() {
            const userData = this.dataset;
            const modalUsernameInput = document.getElementById('modalUsernameInput');
            
            document.getElementById('modalUserId').value = userData.userid;
            document.getElementById('modalUsername').textContent = userData.username;
            modalUsernameInput.value = userData.username;
            document.getElementById('modalFullName').value = userData.fullname;
            document.getElementById('modalRole').value = userData.role;

            if (userData.username === 'admin') {
                modalUsernameInput.readOnly = true;
                modalUsernameInput.style.backgroundColor = '#e9ecef'; // Visual cue
            } else {
                modalUsernameInput.readOnly = false;
                modalUsernameInput.style.backgroundColor = '#fff';
            }
            
            const isAdminEditingSelf = '<?= $_SESSION['username'] ?>' === userData.username;
            const isTargetSuperAdmin = userData.role === 'superAdmin';
            
            document.getElementById('modalRole').disabled = (isTargetSuperAdmin && !isAdminEditingSelf);

            modal.style.display = 'block';
        });
    });
});
</script>

<?php
require_once '../templates/footer.php';
?>