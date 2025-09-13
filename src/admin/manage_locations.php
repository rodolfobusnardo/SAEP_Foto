<?php
// File: src/admin/manage_locations.php
require_once '../auth.php';
require_once '../db_connect.php';
header('Content-Type: text/html; charset=utf-8');
start_secure_session();
require_admin('../index.php');

$pageTitle = "Gerenciar Locais de Achados";

// Fetch all locations for the list
$locations = [];
$sql_locations = "SELECT id, name FROM locations ORDER BY name ASC";
$result_locations = $conn->query($sql_locations);
if ($result_locations && $result_locations->num_rows > 0) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
} elseif ($result_locations === false) {
    error_log("SQL Error (fetch_locations): " . $conn->error);
    $_SESSION['page_error_message'] = "Erro ao carregar lista de locais.";
}

require_once '../templates/header.php';
?>

<style>
/* ================================================================== */
/* CSS REESTRUTURADO PARA CORREÇÃO DO LAYOUT                  */
/* ================================================================== */

/* --- Caixas de Seção --- */
.admin-section-box {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px; 
}
.admin-section-box h3 {
    margin-top: 0;
    margin-bottom: 20px;
    text-align: center;
}

/* --- Formulário de Adicionar Local --- */
.form-add-location {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end; 
    gap: 15px; 
    justify-content: center;
}
.form-add-location .form-group {
    flex-grow: 1;
    max-width: 450px; /* Limita a largura do campo de input */
}
.form-add-location .form-group label {
    display: block;
    margin-bottom: 5px;
    text-align: left;
}
.form-add-location input[type="text"] {
    width: 100%;
    height: 38px;
    box-sizing: border-box;
}
.form-add-location button {
    height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: bold;
}
.form-add-location button i {
    margin-right: 5px;
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
    text-align: center; /* Centraliza todo o texto */
    vertical-align: middle;
    word-wrap: break-word;
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
    background-color: #F0F0F0; /* Cor cinza claro para hover */
    cursor: default;
}

/* --- Largura das Colunas da Tabela --- */
.admin-table col.col-id { width: 15%; }
.admin-table col.col-name { width: 55%; }
.admin-table col.col-actions { width: 30%; }

/* --- Botões de Ação na Tabela --- */
.actions-cell .actions-wrapper {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: center; /* Centraliza os botões na célula */
}
.button-edit, .button-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px 12px;
    font-size: 0.9em;
    font-weight: bold;
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
    // --- BLOCO PARA DISPARAR MENSAGENS DA SESSÃO E GET COMO TOASTS ---
    if (isset($_SESSION['success_message'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($_SESSION['success_message']) . "', 'success'); });</script>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($_SESSION['error_message']) . "', 'error'); });</script>";
        unset($_SESSION['error_message']);
    }
    if (isset($_GET['success'])) {
        $success_messages = [
            'loc_added' => 'Novo local adicionado com sucesso!',
            'loc_updated' => 'Local atualizado com sucesso!',
            'loc_deleted' => 'Local excluído com sucesso!',
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($success_messages[$_GET['success']]) . "', 'success'); });</script>";
        }
    }
    if (isset($_GET['error'])) {
        $error_messages = [
            'emptyfields_addloc' => 'Nome é obrigatório para adicionar local.',
            'loc_exists' => 'Um local com este Nome já existe.',
            'add_loc_failed' => 'Falha ao adicionar novo local.',
            'emptyfields_editloc' => 'Nome é obrigatório para editar local.',
            'loc_exists_edit' => 'Outro local com este Nome já existe.',
            'edit_loc_failed' => 'Falha ao atualizar local.',
            'invalid_action' => 'Ação inválida especificada.',
            'invalid_id_delete' => 'ID inválido para exclusão.',
            'loc_in_use' => 'Este local está em uso por um ou mais itens e não pode ser excluído.',
            'loc_not_found_delete' => 'Local não encontrado para exclusão.',
            'delete_loc_failed' => 'Falha ao excluir local.',
        ];
        $error_key = $_GET['error'];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido.';
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($display_message) . "', 'error'); });</script>";
    }
    if (isset($_GET['message']) && $_GET['message'] == 'loc_nochange') {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('Nenhuma alteração detectada no local.', 'info'); });</script>";
    }
    ?>

    <div id="addLocationSection" class="admin-section-box">
        <h3>Adicionar Novo Local</h3>
        <form action="location_handler.php" method="POST" class="form-admin form-add-location">
            <input type="hidden" name="action" value="add_location">
            <div class="form-group">
                <label for="name_add_loc">Nome do Local</label>
                <input type="text" id="name_add_loc" name="name" required>
            </div>
            <div class="form-button-group">
                <button type="submit" class="button-primary">
                    <i class="fa-solid fa-plus"></i>
                    Adicionar Local
                </button>
            </div>
        </form>
    </div>

    <div id="editLocationSection" style="display:none;" class="admin-section-box"> 
        <h3>Editar Local</h3>
        <form action="location_handler.php" method="POST" class="form-admin">
            <input type="hidden" name="action" value="edit_location">
            <input type="hidden" id="edit_location_id" name="id">
            <div>
                <label for="name_edit_loc">Nome do Local:</label>
                <input type="text" id="name_edit_loc" name="name" required>
            </div>
            <div class="form-action-buttons-group" style="text-align:center; margin-top:15px;">
                <button type="button" class="button-secondary" onclick="hideEditForm('editLocationSection', 'addLocationSection')">Cancelar</button>
                <button type="submit" class="button-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>

    <h3>Lista de Locais</h3>
    <?php if (!empty($locations)): ?>
    <table class="admin-table">
        <colgroup>
            <col class="col-id">
            <col class="col-name">
            <col class="col-actions">
        </colgroup>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($locations as $location): ?>
            <tr>
                <td><?php echo htmlspecialchars($location['id']); ?></td>
                <td><?php echo htmlspecialchars($location['name']); ?></td>
                <td class="actions-cell">
                    <div class="actions-wrapper">
                        <button type="button" class="button-edit" onclick="populateEditLocationForm(<?php echo htmlspecialchars($location['id']); ?>)"><i class="fa-solid fa-edit"></i> Editar</button>
                        <button type="button" class="button-delete" onclick="confirmDeleteLocation(<?php echo htmlspecialchars($location['id']); ?>)"><i class="fa-solid fa-trash"></i> Excluir</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Nenhum local encontrado.</p>
    <?php endif; ?>
</div>

<script>
function populateEditLocationForm(locationId) {
    fetch(`location_handler.php?action=get_location&id=${locationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('edit_location_id').value = data.data.id;
            document.getElementById('name_edit_loc').value = data.data.name;
            document.getElementById('editLocationSection').style.display = 'block';
            document.getElementById('addLocationSection').style.display = 'none'; 
            window.scrollTo(0, document.getElementById('editLocationSection').offsetTop - 20); 
        } else {
            alert('Erro ao buscar dados do local: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Ocorreu um erro de comunicação ao buscar dados do local.');
    });
}

function hideEditForm(formToHideId, formToShowId) {
    document.getElementById(formToHideId).style.display = 'none';
    if (formToShowId) {
        document.getElementById(formToShowId).style.display = 'block';
    }
}

function confirmDeleteLocation(locationId) {
    if (confirm('Tem certeza que deseja excluir este local? Esta ação não pode ser desfeita.')) {
        window.location.href = `location_handler.php?action=delete_location&id=${locationId}`;
    }
}
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close();
}
require_once '../templates/footer.php';
?>