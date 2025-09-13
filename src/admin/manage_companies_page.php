<?php
require_once '../auth.php'; // Gerencia sessão e autenticação
require_once '../db_connect.php'; // Conexão com o banco de dados

require_admin(); // Garante que apenas administradores acessem

$page_title = "Gerenciar Empresas";

// Lógica para buscar empresas do banco de dados
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

$sql = "SELECT id, name, cnpj, phone, email, responsible_name, status FROM companies WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_term)) {
    $sql .= " AND (name LIKE ? OR cnpj LIKE ? OR responsible_name LIKE ? OR email LIKE ?)";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like, $search_like, $search_like);
    $types .= "ssss";
}

if ($status_filter !== 'all') {
    $sql .= " AND status = ?";
    array_push($params, $status_filter);
    $types .= "s";
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($types)) {
    $stmt->bind_param($types, ...$params);
} elseif (!$stmt && $conn->error) {
    die("Erro na preparação da consulta SQL: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
$companies = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }
}
$stmt->close();

// Mensagens de feedback (sucesso/erro)
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

require_once '../templates/header.php';
?>

<div class="container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </header>

    <div class="action-button-container">
        <a href="edit_company_page.php" class="button-primary">
            <i class="fa-solid fa-plus"></i>
            <strong>Adicionar Nova Empresa</strong>
        </a>
    </div>

    <?php
    // --- BLOCO PARA DISPARAR MENSAGENS DA SESSÃO COMO TOASTS ---
    if ($success_message) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($success_message) . "', 'success'); });</script>";
    }
    if ($error_message) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($error_message) . "', 'error'); });</script>";
    }
    ?>

    <div class="form-filters">
        <form action="manage_companies_page.php" method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Buscar por:</label>
                    <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nome, CNPJ, Responsável, Email">
                </div>

                <div class="filter-group">
                    <label for="status">Status da empresa:</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Ativas</option>
                        <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inativas</option>
                        <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>Todas</option>
                    </select>
                </div>
            </div>

            <div class="filter-buttons">
                <a href="manage_companies_page.php" class="button-filter-clear"><i class="fa-solid fa-broom"></i> Limpar Filtros</a>
                <button type="submit" class="button-filter"><i class="fa-solid fa-check"></i> Aplicar Filtros</button>
            </div>
        </form>
    </div>

    <?php
    if (empty($companies) && empty($search_term) && $status_filter === 'all'):
    ?>
        <p>Nenhuma empresa cadastrada. <a href="edit_company_page.php">Adicionar nova empresa</a>.</p>
    <?php elseif (empty($companies)): ?>
        <p>Nenhuma empresa encontrada com os filtros aplicados.</p>
    <?php else: ?>
        <table class="admin-table">
            <colgroup>
                <col style="width: 20%;"> <col style="width: 15%;"> <col style="width: 12%;"> <col style="width: 25%;"> <col style="width: 12%;"> <col style="width: 6%;">  <col style="width: 10%;"> </colgroup>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CNPJ</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Responsável</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($company['name']); ?></td>
                        <td><?php echo htmlspecialchars($company['cnpj'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($company['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($company['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($company['responsible_name'] ?? 'N/A'); ?></td>
                        <td>
                            <div>
                                <span class="status-badge status-<?php echo htmlspecialchars(strtolower($company['status'])); ?>">
                                    <?php echo htmlspecialchars($company['status'] === 'active' ? 'Ativa' : 'Inativa'); ?>
                                </span>
                            </div>
                        </td>
                        <td class="actions-cell">
                            <div class="actions-wrapper">
                                <a href="edit_company_page.php?id=<?php echo $company['id']; ?>" class="button-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <form action="delete_company_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta empresa? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                    <button type="submit" class="button-delete">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once '../templates/footer.php'; ?>

<style>
/* Container geral do título e botão de adicionar */
.page-title-container h1 {
    text-align: center;
    color: #007bff;
    margin-bottom: 20px;
    font-size: 2em;
}

.action-button-container {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

/* ✅ CORREÇÃO: Adiciona espaço entre o ícone e o texto no botão principal */
.action-button-container .button-primary i {
    margin-right: 8px;
}

/* Badge de Status (Ativa/Inativa) */
.status-badge {
    padding: 0.3em 0.6em;
    border-radius: 0.25em;
    font-size: 0.85em;
    font-weight: bold;
    color: #fff;
    text-transform: capitalize;
}
.status-badge.status-active {
    background-color: #28a745;
}
.status-badge.status-inactive {
    background-color: #6c757d;
}

/* Centraliza o cabeçalho da coluna Status */
.admin-table th:nth-child(6) {
    text-align: center;
}

/* Centraliza a badge de status na célula usando flexbox */
.admin-table td:nth-child(6) > div {
    display: flex;
    justify-content: center;
    align-items: center;
}
.admin-table td:nth-child(6) {
    vertical-align: middle; 
}


.admin-table .actions-wrapper {
    display: flex;
    flex-direction: column; 
    justify-content: center;
    align-items: center;
    gap: 5px; 
}

.admin-table .actions-wrapper > a,
.admin-table .actions-wrapper > form {
    margin: 0;
    width: 100%; 
}

/* Regra final para que os botões se pareçam com a tag de status */
.admin-table .actions-wrapper .button-edit,
.admin-table .actions-wrapper .button-delete {
    padding: 0 0.7em !important;
    font-size: 0.8em !important;
    font-weight: bold !important;
    height: 28px !important;
    min-width: auto !important;
    border-radius: 0.25em !important;
    color: white !important;
    background-color: #007bff !important;
    border-color: transparent !important;
    text-decoration: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease-in-out;
    width: 100%; 
}

.admin-table .actions-wrapper .button-delete {
    background-color: #dc3545 !important;
}

/* Hover dos botões */
.admin-table .actions-wrapper .button-edit:hover {
    background-color: #0056b3 !important;
}
.admin-table .actions-wrapper .button-delete:hover {
    background-color: #c82333 !important;
}

/* Ícone dentro dos botões */
.admin-table .actions-wrapper .button-edit i,
.admin-table .actions-wrapper .button-delete i {
    margin-right: 5px;
}

/* === ESTILOS PARA O FORMULÁRIO DE FILTROS === */
.form-filters {
    background-color: #fdfdfd;
    padding: 25px;
    border: 1px solid #e9e9e9;
    border-radius: 8px;
    margin-bottom: 25px;
}
.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}
.filter-group {
    flex: 1;
    min-width: 220px;
}
.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    font-size: 0.9em;
    color: #555;
}
.filter-group .form-control {
    width: 100%;
    height: 40px;
    padding: 0 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
}
.filter-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 10px;
}
.button-filter, .button-filter-clear {
    min-width: 150px;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid transparent;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    font-size: 0.9em;
    text-align: center;
    display: inline-flex;
    justify-content: center;
    align-items: center;
}
.button-filter {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}
.button-filter:hover {
    background-color: #0056b3;
}
.button-filter-clear {
    background-color: #6c757d;
    color: white;
    border-color: #6c757d;
}
.button-filter-clear:hover {
    background-color: #5a6268;
}

.filter-buttons i {
    margin-right: 8px;
}

/* Novo estilo para o hover das linhas da tabela */
.admin-table tbody tr:hover {
    background-color: #FFFEB3; /* Amarelo FFFEB3 */
    cursor: pointer; /* Indica que a linha é interativa */
}
</style>