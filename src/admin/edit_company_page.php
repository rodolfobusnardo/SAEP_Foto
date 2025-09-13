<?php
require_once '../auth.php';
require_once '../db_connect.php';

require_admin();

$page_title = "Adicionar Nova Empresa";
$company_id = null;
$company = [
    'id' => null,
    'name' => '',
    'cnpj' => '',
    'ie' => '',
    'responsible_name' => '',
    'phone' => '',
    'email' => '',
    'address_street' => '',
    'address_number' => '',
    'address_complement' => '',
    'address_neighborhood' => '',
    'address_city' => '',
    'address_state' => '',
    'address_cep' => '',
    'observations' => '',
    'status' => 'active'
];

if (isset($_GET['id'])) {
    $company_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($company_id) {
        $page_title = "Editar Empresa";
        $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
        if (!$stmt) {
            die("Erro na preparação da consulta SQL: " . $conn->error);
        }
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $company = $result->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Empresa não encontrada.";
            header("Location: manage_companies_page.php");
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "ID de empresa inválido.";
        header("Location: manage_companies_page.php");
        exit();
    }
}

$error_message = $_SESSION['edit_company_error_message'] ?? null;
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['edit_company_error_message'], $_SESSION['form_data']);

if (!empty($form_data)) {
    $company = array_merge($company, $form_data);
}


require_once '../templates/header.php';
?>

<div class="container register-item-container">
    <h2><?php echo $page_title; ?></h2>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <strong>Erro:</strong><br>
            <?php echo nl2br(htmlspecialchars($error_message)); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo $company_id ? 'edit_company_handler.php' : 'add_company_handler.php'; ?>" method="POST" class="form-modern">
        <?php if ($company_id): ?>
            <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
        <?php endif; ?>

        <fieldset class="data-section-rounded">
            <legend>Informações da Empresa</legend>
            <div class="form-row">
                <div class="form-group_col_full">
                    <label for="name">Nome da Empresa/Instituição: <span class="required-asterisk">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" required maxlength="255">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group_col">
                    <label for="cnpj">CNPJ:</label>
                    <input type="text" id="cnpj" name="cnpj" class="form-control cnpj-mask" value="<?php echo htmlspecialchars($company['cnpj'] ?? ''); ?>" maxlength="20">
                </div>
                <div class="form-group_col">
                    <label for="ie">Inscrição Estadual (IE):</label>
                    <input type="text" id="ie" name="ie" class="form-control" value="<?php echo htmlspecialchars($company['ie'] ?? ''); ?>" maxlength="20">
                </div>
            </div>
             <div class="form-row">
                <div class="form-group_col">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>" maxlength="255">
                </div>
                <div class="form-group_col">
                    <label for="phone">Telefone:</label>
                    <input type="text" id="phone" name="phone" class="form-control phone-mask" value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>" maxlength="20">
                </div>
            </div>
            <div class="form-row">
                 <div class="form-group_col_full">
                    <label for="responsible_name">Nome do Responsável na Empresa:</label>
                    <input type="text" id="responsible_name" name="responsible_name" class="form-control" value="<?php echo htmlspecialchars($company['responsible_name'] ?? ''); ?>" maxlength="255">
                </div>
            </div>
        </fieldset>

        <fieldset class="data-section-rounded">
            <legend>Endereço</legend>
            <div class="form-row">
                <div class="form-group_col form-group_col-small">
                    <label for="address_cep">CEP:</label>
                    <input type="text" id="address_cep" name="address_cep" class="form-control cep-mask" value="<?php echo htmlspecialchars($company['address_cep'] ?? ''); ?>" maxlength="10">
                </div>
                <div class="form-group_col_button">
                     <button type="button" id="searchCepButton" class="button-secondary" style="margin-top:30px;">Buscar CEP</button>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group_col form-group_col-large">
                    <label for="address_street">Logradouro (Rua/Av.):</label>
                    <input type="text" id="address_street" name="address_street" class="form-control" value="<?php echo htmlspecialchars($company['address_street'] ?? ''); ?>" maxlength="255">
                </div>
                <div class="form-group_col form-group_col-small">
                    <label for="address_number">Número:</label>
                    <input type="text" id="address_number" name="address_number" class="form-control" value="<?php echo htmlspecialchars($company['address_number'] ?? ''); ?>" maxlength="50">
                </div>
            </div>
            <div class="form-row">
                 <div class="form-group_col">
                    <label for="address_complement">Complemento:</label>
                    <input type="text" id="address_complement" name="address_complement" class="form-control" value="<?php echo htmlspecialchars($company['address_complement'] ?? ''); ?>" maxlength="100">
                </div>
                <div class="form-group_col">
                    <label for="address_neighborhood">Bairro:</label>
                    <input type="text" id="address_neighborhood" name="address_neighborhood" class="form-control" value="<?php echo htmlspecialchars($company['address_neighborhood'] ?? ''); ?>" maxlength="100">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group_col">
                    <label for="address_city">Cidade:</label>
                    <input type="text" id="address_city" name="address_city" class="form-control" value="<?php echo htmlspecialchars($company['address_city'] ?? ''); ?>" maxlength="100">
                </div>
                <div class="form-group_col form-group_col-small">
                    <label for="address_state">Estado (UF):</label>
                    <input type="text" id="address_state" name="address_state" class="form-control estado-mask" value="<?php echo htmlspecialchars($company['address_state'] ?? ''); ?>" maxlength="50">
                </div>
            </div>
        </fieldset>

        <fieldset class="data-section-rounded">
            <legend>Outras Informações</legend>
             <div class="form-row">
                <div class="form-group_col_full">
                    <label for="observations">Observações:</label>
                    <textarea id="observations" name="observations" class="form-control" rows="3"><?php echo htmlspecialchars($company['observations'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group_col">
                    <label for="status">Status: <span class="required-asterisk">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?php echo (($company['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="inactive" <?php echo (($company['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inativa</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <div class="form-action-buttons-group" style="margin-top: 20px;">
            <a href="manage_companies_page.php" class="button-secondary">Cancelar</a>
            <button type="submit" class="button-primary">
                <?php echo $company_id ? 'Salvar Alterações' : 'Adicionar Empresa'; ?>
            </button>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Input Masking Logic (similar to generate_donation_term_page.php) ---
    const cnpjInput = document.getElementById('cnpj');
    if (cnpjInput) {
        cnpjInput.addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 14) v = v.slice(0, 14);
            if (v.length >= 3) v = v.replace(/^(\d{2})(\d)/, '$1.$2');
            if (v.length >= 7) v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            if (v.length >= 11) v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
            if (v.length >= 16) v = v.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = v;
        });
    }

    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 10) { // 11 digits (XX) XXXXX-XXXX
                v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 6) { // 10 digits (XX) XXXX-XXXX
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) { // (XX) XXXX
                v = v.replace(/^(\d{2})(\d*)$/, '($1) $2');
            } else if (v.length > 0) { // (X
                v = v.replace(/^(\d*)$/, '($1');
            }
            e.target.value = v;
        });
    }

    const cepInput = document.getElementById('address_cep');
    if (cepInput) {
        cepInput.addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 8) v = v.slice(0, 8);
            if (v.length >= 6) v = v.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = v;
        });
    }

    const estadoInput = document.getElementById('address_state');
    if (estadoInput) {
        estadoInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 2);
        });
    }

    // --- ViaCEP Integration ---
    const searchCepButton = document.getElementById('searchCepButton');
    if (searchCepButton && cepInput) {
        searchCepButton.addEventListener('click', function() {
            const cep = cepInput.value.replace(/\D/g, '');
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.erro) {
                            alert('CEP não encontrado.');
                            return;
                        }
                        if (document.getElementById('address_street')) document.getElementById('address_street').value = data.logradouro || '';
                        if (document.getElementById('address_neighborhood')) document.getElementById('address_neighborhood').value = data.bairro || '';
                        if (document.getElementById('address_city')) document.getElementById('address_city').value = data.localidade || '';
                        if (document.getElementById('address_state')) document.getElementById('address_state').value = data.uf || '';
                        // Focus no número após preencher
                        if (document.getElementById('address_number')) document.getElementById('address_number').focus();
                    })
                    .catch(error => {
                        console.error('Erro ao buscar CEP:', error);
                        alert('Falha ao buscar CEP. Verifique sua conexão ou tente novamente.');
                    });
            } else {
                alert('Por favor, insira um CEP válido (8 dígitos).');
            }
        });
    }
});
</script>
<style>
    .required-asterisk {
        color: red;
        margin-left: 2px;
    }
    .form-group_col_button {
        display: flex;
        align-items: flex-end; /* Alinha o botão com a base dos inputs */
        margin-left: 10px;
    }
    /* Ajustes para garantir que o layout do formulário seja consistente com generate_donation_term_page.php */
    .form-modern fieldset.data-section-rounded {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        background-color: #fdfdfd;
    }
    .form-modern legend {
        font-size: 1.2em;
        font-weight: bold;
        color: #333;
        padding: 0 10px;
        margin-left: 10px; /* Leve ajuste para alinhar com o padding do fieldset */
        width: auto; /* Necessário para o padding funcionar corretamente */
    }
    .form-modern .form-row {
        display: flex;
        flex-wrap: wrap; /* Permite que os itens quebrem para a próxima linha em telas menores */
        justify-content: space-between;
        margin-bottom: 15px;
    }
    .form-modern .form-group_col,
    .form-modern .form-group_col_full,
    .form-modern .form-group_col-small,
    .form-modern .form-group_col-large {
        display: flex;
        flex-direction: column;
        margin-bottom: 10px; /* Espaçamento inferior para cada campo */
    }
    .form-modern .form-group_col_full {
        width: 100%;
    }
    .form-modern .form-group_col {
        flex-basis: calc(50% - 10px); /* Para dois campos por linha, com espaço entre eles */
    }
    .form-modern .form-group_col-small {
        flex-basis: calc(25% - 10px); /* Para campos menores */
    }
     .form-modern .form-group_col-large {
        flex-basis: calc(75% - 10px); /* Para campos maiores */
    }

    .form-modern label {
        margin-bottom: 5px;
        font-weight: bold;
        color: #555;
    }
    .form-modern .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 1em;
    }
    .form-modern .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    .form-modern textarea.form-control {
        min-height: 80px;
        resize: vertical;
    }
    .form-action-buttons-group {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    @media (max-width: 768px) {
        .form-modern .form-group_col,
        .form-modern .form-group_col-small,
        .form-modern .form-group_col-large {
            flex-basis: 100%; /* Campos ocupam largura total em telas pequenas */
        }
        .form-group_col_button {
             margin-left: 0;
             width: 100%;
        }
        .form-group_col_button button {
            width: 100%;
            margin-top: 10px;
        }
    }
</style>
<?php require_once '../templates/footer.php'; ?>