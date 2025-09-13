<?php
// File: src/admin/manage_categories.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin('../index.php');

$pageTitle = "Gerenciar Categorias de Itens";

// Fetch all categories for the list
$categories = [];
$sql_categories = "SELECT id, name, code FROM categories ORDER BY name ASC";
$result_categories = $conn->query($sql_categories);
if ($result_categories && $result_categories->num_rows > 0) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
} elseif ($result_categories === false) {
    error_log("SQL Error (fetch_categories): " . $conn->error);
    $_SESSION['page_error_message'] = "Erro ao carregar lista de categorias.";
}

require_once '../templates/header.php';
?>

<style>
/* ================================================================== */
/* NOVOS ESTILOS PARA CORREÇÃO DO LAYOUT                     */
/* ================================================================== */

/* --- Formulário de Adicionar Categoria --- */
.form-add-category-inline {
    display: flex;
    flex-wrap: wrap; 
    align-items: flex-end; 
    gap: 15px; 
    margin-bottom: 25px;
}
.form-add-category-inline > div {
    flex: 1 1 200px; 
}
.form-add-category-inline input[type="text"] {
    width: 100%;
    height: 38px;
    box-sizing: border-box;
}
.form-add-category-inline .form-button-group {
    flex-grow: 0;
    flex-shrink: 0;
}
.form-add-category-inline .form-button-group button {
    height: 38px;
    white-space: nowrap;
}
.form-add-category-inline button i {
    margin-right: 6px;
}


/* --- Estrutura Base da Tabela --- */
.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    table-layout: fixed; /* Essencial para larguras de coluna previsíveis */
}
.admin-table th, .admin-table td {
    padding: 12px 15px;
    border: 1px solid #dee2e6;
    text-align: center; /* ALTERAÇÃO: Centraliza o texto nas células */
    vertical-align: middle;
    word-wrap: break-word; /* Garante que textos longos quebrem a linha */
}
.admin-table thead th {
    background-color: #007bff;
    color: white;
    font-weight: bold;
}
.admin-table tbody tr:nth-of-type(even) {
    background-color: #f8f9fa;
}
.admin-table tbody tr:hover {
    background-color: #F0F0F0; /* ALTERAÇÃO: Nova cor de hover */
    cursor: default;
}


/* --- Largura das Colunas da Tabela --- */
.admin-table th:nth-of-type(1), .admin-table td:nth-of-type(1) { width: 10%; } /* ID */
.admin-table th:nth-of-type(2), .admin-table td:nth-of-type(2) { width: 50%; } /* Nome (espaço principal) */
.admin-table th:nth-of-type(3), .admin-table td:nth-of-type(3) { width: 15%; } /* Código */
.admin-table th:nth-of-type(4), .admin-table td:nth-of-type(4) { width: 25%; } /* Ações */


/* --- Botões de Ação na Tabela --- */
.actions-cell .actions-wrapper {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: center; /* Garante que os botões fiquem centralizados na célula */
}
.button-edit, .button-delete {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    font-size: 0.9em;
    border: none;
    border-radius: 5px;
    color: white;
    cursor: pointer;
    transition: background-color 0.2s;
    text-decoration: none;
}
.button-edit { background-color: #007bff; }
.button-edit:hover { background-color: #0056b3; }
.button-delete { background-color: #dc3545; }
.button-delete:hover { background-color: #c82333; }

</style>

<div class="container admin-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>

    <?php
    // --- BLOCO PARA DISPARAR MENSAGENS DA SESSÃO COMO TOASTS ---
    if (isset($_SESSION['success_message'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($_SESSION['success_message']) . "', 'success'); });</script>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($_SESSION['error_message']) . "', 'error'); });</script>";
        unset($_SESSION['error_message']);
    }
    // Handling GET-based messages as well, for compatibility
    if (isset($_GET['success'])) {
        $success_messages = [
            'cat_added' => 'Nova categoria adicionada com sucesso!',
            'cat_updated' => 'Categoria atualizada com sucesso!',
            'cat_deleted' => 'Categoria excluída com sucesso!',
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($success_messages[$_GET['success']]) . "', 'success'); });</script>";
        }
    }
    if (isset($_GET['error'])) {
        $error_messages = [
            'emptyfields_addcat' => 'Nome e Código são obrigatórios para adicionar categoria.',
            'code_too_long' => 'O código da categoria não pode ter mais que 10 caracteres.',
            'cat_exists' => 'Uma categoria com este Nome ou Código já existe.',
            'add_cat_failed' => 'Falha ao adicionar nova categoria.',
            'emptyfields_editcat' => 'Nome e Código são obrigatórios para editar categoria.',
            'code_too_long_edit' => 'O código da categoria não pode ter mais que 10 caracteres (ao editar).',
            'cat_exists_edit' => 'Outra categoria com este Nome ou Código já existe.',
            'edit_cat_failed' => 'Falha ao atualizar categoria.',
            'invalid_action' => 'Ação inválida especificada.',
            'invalid_id_delete' => 'ID inválido para exclusão.',
            'cat_in_use' => 'Esta categoria está em uso e não pode ser excluída.',
            'cat_not_found_delete' => 'Categoria não encontrada para exclusão.',
            'delete_cat_failed' => 'Falha ao excluir categoria.',
        ];
        $error_key = $_GET['error'];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido.';
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($display_message) . "', 'error'); });</script>";
    }
    ?>

    <div id="addCategorySection">
        <h3>Adicionar Nova Categoria</h3>
        <form action="category_handler.php" method="POST" class="form-admin form-add-category-inline">
            <input type="hidden" name="action" value="add_category">
            <div>
                <label for="name_add">Nome da Categoria</label>
                <input type="text" id="name_add" name="name" required>
            </div>
            <div>
                <label for="code_add">Código</label>
                <input type="text" id="code_add" name="code" required maxlength="10" pattern="[A-Za-z0-9_]+" title="Use letras, números ou underscore." placeholder="Ex: ROP, ELE">
            </div>
            <div class="form-button-group">
                <button type="submit" class="button-primary"><i class="fa-solid fa-plus"></i> Adicionar Categoria</button>
            </div>
        </form>
    </div>

    <hr> 
    
    <div id="editCategorySection" style="display:none;"> 
        <h3>Editar Categoria</h3>
        <form action="category_handler.php" method="POST" class="form-admin">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" id="edit_category_id" name="id">
            <div>
                <label for="name_edit">Nome da Categoria:</label>
                <input type="text" id="name_edit" name="name" required>
            </div>
            <div>
                <label for="code_edit">Código (ex: ROP, ELE, max 10 chars):</label>
                <input type="text" id="code_edit" name="code" required maxlength="10" pattern="[A-Za-z0-9_]+" title="Use letras, números ou underscore.">
            </div>
            <div class="form-action-buttons-group">
                <button type="button" class="button-secondary" onclick="hideEditForm('editCategorySection')">Cancelar</button>
                <button type="submit" class="button-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>


    <h3>Lista de Categorias</h3>
    <?php if (!empty($categories)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Código</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
            <tr>
                <td><?php echo htmlspecialchars($category['id']); ?></td>
                <td><?php echo htmlspecialchars($category['name']); ?></td>
                <td><?php echo htmlspecialchars($category['code']); ?></td>
                <td class="actions-cell">
                    <div class="actions-wrapper">
                        <button type="button" class="button-edit" onclick="populateEditCategoryForm(<?php echo htmlspecialchars($category['id']); ?>)"><i class="fa-solid fa-edit"></i> Editar</button>
                        <button type="button" class="button-delete" onclick="confirmDeleteCategory(<?php echo htmlspecialchars($category['id']); ?>)"><i class="fa-solid fa-trash"></i> Excluir</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Nenhuma categoria encontrada.</p>
    <?php endif; ?>
</div>

<script>
function populateEditCategoryForm(categoryId) {
    fetch(`category_handler.php?action=get_category&id=${categoryId}`)
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('edit_category_id').value = data.data.id;
            document.getElementById('name_edit').value = data.data.name;
            document.getElementById('code_edit').value = data.data.code;
            document.getElementById('editCategorySection').style.display = 'block';
            document.getElementById('addCategorySection').style.display = 'none'; 
            window.scrollTo(0, document.getElementById('editCategorySection').offsetTop - 20); 
        } else {
            alert('Erro ao buscar dados da categoria: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Ocorreu um erro de comunicação ao buscar dados da categoria.');
    });
}

function hideEditForm(formId) {
    document.getElementById(formId).style.display = 'none';
    document.getElementById('addCategorySection').style.display = 'block'; 
}

function confirmDeleteCategory(categoryId) {
    if (confirm('Tem certeza que deseja excluir esta categoria? Esta ação não pode ser desfeita.')) {
        window.location.href = `category_handler.php?action=delete_category&id=${categoryId}`;
    }
}
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close(); // Usually closed by PHP or footer
}
require_once '../templates/footer.php';
?>