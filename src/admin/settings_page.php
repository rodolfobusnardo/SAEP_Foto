<?php
// Bloco único de PHP para toda a lógica antes do HTML
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_connect.php';

// 1. Inicia a sessão e verifica a permissão
start_secure_session();
require_super_admin();

// Array com os estados brasileiros
$brazilian_states = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];
asort($brazilian_states);

// 2. Lógica da página (buscar dados do banco)
function get_all_settings($conn) {
    $stmt = $conn->prepare("SELECT unidade_nome, cnpj, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado, endereco_cep, declaration_donation_text, declaration_devolution_text FROM settings WHERE config_id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [
        'unidade_nome' => '', 'cnpj' => '', 'endereco_rua' => '',
        'endereco_numero' => '', 'endereco_bairro' => '', 'endereco_cidade' => '',
        'endereco_estado' => '', 'endereco_cep' => '',
        'declaration_donation_text' => 'Declaro, para os devidos fins, que o(s) item(ns) descrito(s) neste termo foi(ram) doado(s) voluntariamente ao setor de Achados e Perdidos - Sesc [NOME_UNIDADE], e a instituição [NOME_INSTITUICAO] reconhece o recebimento.',
        'declaration_devolution_text' => 'Declaro, para os devidos fins, que reconheço o item descrito neste termo como de minha propriedade e que o mesmo me foi devolvido pelo setor de Achados e Perdidos - Sesc [NOME_UNIDADE], após conferência e identificação.'
    ];
}

$current_settings = get_all_settings($conn);

if (!empty($current_settings['cnpj'])) {
    $v = preg_replace('/\D/', '', $current_settings['cnpj']);
    if (strlen($v) == 14) {
        $v = substr($v, 0, 2) . '.' . substr($v, 2, 3) . '.' . substr($v, 5, 3) . '/' . substr($v, 8, 4) . '-' . substr($v, 12, 2);
        $current_settings['cnpj'] = $v;
    }
}
if (!empty($current_settings['endereco_cep'])) {
    $v = preg_replace('/\D/', '', $current_settings['endereco_cep']);
    if (strlen($v) == 8) {
        $v = substr($v, 0, 5) . '-' . substr($v, 5, 3);
        $current_settings['endereco_cep'] = $v;
    }
}

// 3. Header é chamado para começar a "desenhar" a página
$pageTitle = "Configurações do Sistema";
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container admin-container">
    <header class="admin-header">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>
    <?php
    // Bloco de Toasts
    if (isset($_GET['success'])) {
        $success_message = '';
        if ($_GET['success'] === 'true') {
            $success_message = 'Configurações salvas com sucesso!';
        } elseif ($_GET['success'] === 'restored' && isset($_SESSION['settings_success_message'])) {
            $success_message = $_SESSION['settings_success_message'];
            unset($_SESSION['settings_success_message']);
        } elseif ($_GET['success'] === 'restored') {
            $success_message = 'Banco de dados restaurado com sucesso!';
        }
        if ($success_message) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($success_message) . "', 'success'); });</script>";
        }
    }
    if (isset($_GET['error'])) {
        $error_message = 'Ocorreu um erro inesperado.';
        if (!empty($_SESSION['settings_error_message'])) {
            $error_message = $_SESSION['settings_error_message'];
            unset($_SESSION['settings_error_message']);
        }
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($error_message) . "', 'error'); });</script>";
    }
    ?>

    <form action="settings_handler.php" method="POST" class="form-admin form-modern">
        <h3>Dados da Unidade</h3>
        <div class="form-row">
            <div class="form-group_col flex-nome">
                <label for="unidade_nome">Nome da Unidade:</label>
                <input type="text" id="unidade_nome" name="unidade_nome" class="form-control" value="<?php echo htmlspecialchars($current_settings['unidade_nome'] ?? ''); ?>" maxlength="255">
            </div>
            <div class="form-group_col flex-cnpj">
                <label for="cnpj">CNPJ:</label>
                <input type="text" id="cnpj" name="cnpj" class="form-control" value="<?php echo htmlspecialchars($current_settings['cnpj'] ?? ''); ?>" maxlength="18" required>
            </div>
        </div>
        <h3>Endereço Completo</h3>
        <div class="form-row">
            <div class="form-group_col flex-cep">
                <label for="endereco_cep">CEP:</label>
                <div class="cep-group">
                    <input type="text" id="endereco_cep" name="endereco_cep" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_cep'] ?? ''); ?>" maxlength="9">
                    <button type="button" id="search_cep_button" class="button-secondary"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                </div>
                <span id="cep_status"></span>
            </div>
        </div>
        <div class="form-row">
             <div class="form-group_col flex-rua">
                <label for="endereco_rua">Rua:</label>
                <input type="text" id="endereco_rua" name="endereco_rua" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_rua'] ?? ''); ?>" maxlength="255">
            </div>
            <div class="form-group_col flex-numero">
                <label for="endereco_numero">Número:</label>
                <input type="text" id="endereco_numero" name="endereco_numero" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_numero'] ?? ''); ?>" maxlength="10">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group_col flex-bairro">
                <label for="endereco_bairro">Bairro:</label>
                <input type="text" id="endereco_bairro" name="endereco_bairro" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_bairro'] ?? ''); ?>" maxlength="100">
            </div>
            <div class="form-group_col flex-cidade">
                <label for="endereco_cidade">Cidade:</label>
                <input type="text" id="endereco_cidade" name="endereco_cidade" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_cidade'] ?? ''); ?>" maxlength="100">
            </div>
            <div class="form-group_col flex-estado">
                <label for="endereco_estado">Estado:</label>
                <select id="endereco_estado" name="endereco_estado" class="form-control">
                    <option value="">Selecione...</option>
                    <?php foreach ($brazilian_states as $uf => $state_name): ?>
                        <option value="<?php echo $uf; ?>" <?php if (($current_settings['endereco_estado'] ?? '') === $uf) echo 'selected'; ?>><?php echo htmlspecialchars($state_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <h3>Textos de Declaração dos Termos</h3>
        <div class="form-row">
            <div class="form-group_col" style="flex-basis: 100%;">
                <label for="declaration_donation_text">Texto da Declaração de Doação:</label>
                <textarea id="declaration_donation_text" name="declaration_donation_text" class="declaration-textarea"><?php echo htmlspecialchars($current_settings['declaration_donation_text'] ?? ''); ?></textarea>
                <p class="declaration-info">Use <code>[NOME_UNIDADE]</code> e <code>[NOME_INSTITUICAO]</code> para substituições.</p>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group_col" style="flex-basis: 100%;">
                <label for="declaration_devolution_text">Texto da Declaração de Devolução:</label>
                <textarea id="declaration_devolution_text" name="declaration_devolution_text" class="declaration-textarea"><?php echo htmlspecialchars($current_settings['declaration_devolution_text'] ?? ''); ?></textarea>
                <p class="declaration-info">Use <code>[NOME_UNIDADE]</code> para substituição.</p>
            </div>
        </div>
        <div class="form-action-buttons-group">
            <button type="submit" class="button-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar Configurações</button>
        </div>
    </form>

    <div class="backup-restore-card">
        <h3>Backup e Restauração do Banco de Dados</h3>
        <div class="backup-restore-content">
            <div class="backup-section">
                <div class="card-content">
                    <h4>Fazer Backup</h4>
                    <p>Gere e baixe um arquivo de backup (.sql) completo do banco de dados.</p>
                </div>
                <form action="backup_handler.php" method="POST" class="backup-form">
                    <button type="submit" class="button-primary" style="width:100%;"><i class="fa-solid fa-download"></i> Gerar Backup</button>
                </form>
            </div>
            <div class="restore-section">
                <div class="card-content">
                    <h4>Restaurar Backup</h4>
                    <p>Selecione um arquivo (.sql) para restaurar.<br><strong>Atenção:</strong> Esta ação substituirá todos os dados atuais.</p>
                </div>
                <form action="restore_handler.php" method="POST" enctype="multipart/form-data">
                    <div class="restore-actions">
                        <div style="text-align: center;">
                            <input type="file" name="backup_file" id="backup_file" accept=".sql" required class="visually-hidden">
                            <label for="backup_file" class="button-secondary"><i class="fa-solid fa-folder-open"></i> Escolher Arquivo</label>
                            <div id="file-chosen-text">Nenhum arquivo escolhido</div>
                        </div>
                        <button type="submit" id="restore_button" class="button-danger" onclick="return confirm('Tem certeza que deseja restaurar o backup? Todos os dados atuais serão perdidos e esta ação é irreversível!');"><i class="fa-solid fa-upload"></i> Restaurar Backup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Scripts de máscara e busca de CEP (sem alterações)
document.getElementById('cnpj').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 14) v = v.slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d)/, '$1-$2');
    e.target.value = v;
});
document.getElementById('endereco_cep').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 8) v = v.slice(0, 8);
    v = v.replace(/^(\d{5})(\d)/, '$1-$2');
    e.target.value = v;
});
document.getElementById('endereco_numero').addEventListener('input', function (e) { e.target.value = e.target.value.replace(/\D/g, ''); });

const cepInput = document.getElementById('endereco_cep');
const searchButton = document.getElementById('search_cep_button');
const statusSpan = document.getElementById('cep_status');
const ruaInput = document.getElementById('endereco_rua');
const bairroInput = document.getElementById('endereco_bairro');
const cidadeInput = document.getElementById('endereco_cidade');
const estadoInput = document.getElementById('endereco_estado');
const numeroInput = document.getElementById('endereco_numero');
const searchCep = async () => {
    const cep = cepInput.value.replace(/\D/g, '');
    if (cep.length !== 8) { statusSpan.textContent = 'CEP inválido.'; return; }
    statusSpan.textContent = 'Buscando...';
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();
        if (data.erro) {
            statusSpan.textContent = 'CEP não encontrado.';
            ruaInput.value = ''; bairroInput.value = ''; cidadeInput.value = ''; estadoInput.value = '';
        } else {
            statusSpan.textContent = 'Endereço encontrado!';
            ruaInput.value = data.logouro;
            bairroInput.value = data.bairro;
            cidadeInput.value = data.localidade;
            estadoInput.value = data.uf;
            numeroInput.value = ''; numeroInput.focus();
        }
    } catch (error) {
        statusSpan.textContent = 'Erro na busca. Tente novamente.';
        console.error("Erro ao buscar CEP:", error);
    }
};
cepInput.addEventListener('blur', searchCep);
searchButton.addEventListener('click', searchCep);

// Script para exibir o nome do arquivo e habilitar/desabilitar o botão de restaurar
const backupFileInput = document.getElementById('backup_file');
const fileChosenText = document.getElementById('file-chosen-text');
const restoreButton = document.getElementById('restore_button');

// Inicia o botão de restauração como desabilitado
restoreButton.disabled = true;

backupFileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        fileChosenText.textContent = this.files[0].name;
        restoreButton.disabled = false; // Habilita o botão se um arquivo for escolhido
    } else {
        fileChosenText.textContent = 'Nenhum arquivo escolhido';
        restoreButton.disabled = true; // Desabilita o botão se nenhum arquivo for escolhido
    }
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>