<?php
require_once '../auth.php';
require_once '../db_connect.php'; // Provides $conn

start_secure_session(); // Ensure session is started for potential error messages
require_super_admin('../home.php?error=auth');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings_page.php?error=invalid_request');
    exit();
}

// Retrieve and trim all expected fields
$unidade_nome = trim($_POST['unidade_nome'] ?? '');
$raw_cnpj = trim($_POST['cnpj'] ?? '');
$endereco_rua = trim($_POST['endereco_rua'] ?? '');
$raw_endereco_numero = trim($_POST['endereco_numero'] ?? ''); // Mask ensures digits
$endereco_bairro = trim($_POST['endereco_bairro'] ?? '');
$endereco_cidade = trim($_POST['endereco_cidade'] ?? '');
$raw_endereco_estado = trim($_POST['endereco_estado'] ?? ''); // Mask ensures 2 uppercase letters
$raw_endereco_cep = trim($_POST['endereco_cep'] ?? '');

// NOVOS CAMPOS PARA AS DECLARAÇÕES
$declaration_donation_text = trim($_POST['declaration_donation_text'] ?? '');
$declaration_devolution_text = trim($_POST['declaration_devolution_text'] ?? '');


// --- Validation and Cleaning ---

// CNPJ: Remove mask, validate length 14. Now mandatory.
$cleaned_cnpj = preg_replace('/\D/', '', $raw_cnpj);
if (empty($cleaned_cnpj)) {
    $_SESSION['settings_error_message'] = 'CNPJ é obrigatório.';
    header('Location: settings_page.php?error=validation&field=cnpj');
    exit();
}
if (strlen($cleaned_cnpj) !== 14) {
    $_SESSION['settings_error_message'] = 'CNPJ inválido. Deve conter 14 dígitos.';
    header('Location: settings_page.php?error=validation&field=cnpj');
    exit();
}
// No longer allowing $cleaned_cnpj = null if empty, as it's required.

// CEP: Remove mask, validate length 8
$cleaned_endereco_cep = preg_replace('/\D/', '', $raw_endereco_cep);
if (!empty($raw_endereco_cep) && strlen($cleaned_endereco_cep) !== 8) {
    $_SESSION['settings_error_message'] = 'CEP inválido. Deve conter 8 dígitos.';
    header('Location: settings_page.php?error=validation&field=cep');
    exit();
}
if (empty($raw_endereco_cep)) $cleaned_endereco_cep = null; // Store as NULL if submitted empty

// Número (Address Number): Mask ensures digits. Validate if it's numeric. Maxlength 10.
$cleaned_endereco_numero = $raw_endereco_numero; // Already digits from JS mask
if (!empty($cleaned_endereco_numero) && !is_numeric($cleaned_endereco_numero)) {
    // This case should ideally not be reached if JS mask works, but server validation is good.
    $_SESSION['settings_error_message'] = 'Número do endereço inválido. Deve conter apenas dígitos.';
    header('Location: settings_page.php?error=validation&field=numero');
    exit();
}
if (strlen($cleaned_endereco_numero) > 10) { // Check against maxlength
    $_SESSION['settings_error_message'] = 'Número do endereço muito longo. Máximo de 10 dígitos.';
    header('Location: settings_page.php?error=validation&field=numero');
    exit();
}
if (empty($raw_endereco_numero)) $cleaned_endereco_numero = null;


// Estado (State): Mask ensures 2 uppercase letters. Validate. Maxlength 2.
$cleaned_endereco_estado = $raw_endereco_estado; // JS mask should ensure format
if (!empty($cleaned_endereco_estado) && !preg_match('/^[A-Z]{2}$/', $cleaned_endereco_estado)) {
    // This case should ideally not be reached if JS mask works.
    $_SESSION['settings_error_message'] = 'Estado inválido. Deve ser a sigla com 2 letras maiúsculas (ex: SP).';
    header('Location: settings_page.php?error=validation&field=estado');
    exit();
}
if (empty($raw_endereco_estado)) $cleaned_endereco_estado = null;


// --- Database Operation ---

// Check if settings row exists (fallback, ideally init script handles this)
$check_stmt = $conn->prepare("SELECT config_id FROM settings WHERE config_id = 1");
if ($check_stmt) {
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows === 0) {
        error_log("Settings row with config_id=1 not found. Attempting to insert.");
        // Ajustado para incluir as novas colunas
        $insert_stmt = $conn->prepare("INSERT INTO settings (config_id, unidade_nome, cnpj, endereco_rua, endereco_numero, endereco_bairro, endereco_cidade, endereco_estado, endereco_cep, declaration_donation_text, declaration_devolution_text) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($insert_stmt) {
            $insert_stmt->bind_param( // 10 string parameters for the 10 data fields
                "ssssssssss", // Adicionado 'ss' para as duas novas declarações
                $unidade_nome, $cleaned_cnpj, $endereco_rua, $cleaned_endereco_numero,
                $endereco_bairro, $endereco_cidade, $cleaned_endereco_estado, $cleaned_endereco_cep,
                $declaration_donation_text, $declaration_devolution_text // Novos parâmetros
            );
            if (!$insert_stmt->execute()) {
                error_log("Failed to insert new settings row: " . $insert_stmt->error);
                $_SESSION['settings_error_message'] = 'Erro crítico ao inicializar configurações.';
                header('Location: settings_page.php?error=dberror_insert_init');
                exit();
            }
            $insert_stmt->close();
        } else {
            error_log("Failed to prepare insert statement for settings: " . $conn->error);
            $_SESSION['settings_error_message'] = 'Erro crítico de banco de dados (prepare insert).';
            header('Location: settings_page.php?error=dberror_prepare_insert');
            exit();
        }
    }
    $check_stmt->close();
} else {
    error_log("Failed to prepare check statement for settings: " . $conn->error);
    $_SESSION['settings_error_message'] = 'Erro crítico de banco de dados (prepare check).';
    header('Location: settings_page.php?error=dberror_prepare_check');
    exit();
}

// Ajustado para incluir as novas colunas
$sql = "UPDATE settings SET
            unidade_nome = ?,
            cnpj = ?,
            endereco_rua = ?,
            endereco_numero = ?,
            endereco_bairro = ?,
            endereco_cidade = ?,
            endereco_estado = ?,
            endereco_cep = ?,
            declaration_donation_text = ?,     -- Nova coluna
            declaration_devolution_text = ?    -- Nova coluna
        WHERE config_id = 1";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("SQL Prepare Error (settings_handler update): " . $conn->error);
    $_SESSION['settings_error_message'] = 'Erro de banco de dados ao preparar atualização.';
    header('Location: settings_page.php?error=dberror_prepare_update');
    exit();
}

// Bind parameters (10 parameters: ssssssssss)
$stmt->bind_param(
    "ssssssssss", // Ajustado para 10 's'
    $unidade_nome,
    $cleaned_cnpj,
    $endereco_rua,
    $cleaned_endereco_numero,
    $endereco_bairro,
    $endereco_cidade,
    $cleaned_endereco_estado,
    $cleaned_endereco_cep,
    $declaration_donation_text, // Novo parâmetro
    $declaration_devolution_text // Novo parâmetro
);

if ($stmt->execute()) {
    // Success: Clear any previous error message from session
    unset($_SESSION['settings_error_message']);
    header('Location: settings_page.php?success=true');
} else {
    error_log("SQL Execute Error (settings_handler update): " . $stmt->error);
    $_SESSION['settings_error_message'] = 'Erro de banco de dados ao salvar configurações.';
    header('Location: settings_page.php?error=dberror_execute_update');
}

$stmt->close();
$conn->close();
exit();
?>