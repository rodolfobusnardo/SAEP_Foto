<?php
// 1. AUTENTICAÇÃO E AUTORIZAÇÃO
require_once '../auth.php';
require_once '../config.php';

start_secure_session();
require_super_admin();

// Define o URL de redirecionamento para sucesso ou falha
$redirect_url = 'settings_page.php';

// 2. VALIDAÇÃO DO REQUEST E DO UPLOAD
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['backup_file'])) {
    $_SESSION['settings_error_message'] = 'Requisição inválida.';
    header("Location: $redirect_url?error=invalid_request");
    exit();
}

$file = $_FILES['backup_file'];

// Verifica erros de upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite de tamanho do servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite de tamanho do formulário.',
        UPLOAD_ERR_PARTIAL    => 'O upload do arquivo foi feito parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo foi enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Faltando uma pasta temporária no servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco.',
        UPLOAD_ERR_EXTENSION  => 'Uma extensão do PHP interrompeu o upload do arquivo.',
    ];
    $error_message = $upload_errors[$file['error']] ?? 'Ocorreu um erro desconhecido no upload.';
    $_SESSION['settings_error_message'] = $error_message;
    header("Location: $redirect_url?error=upload_failed");
    exit();
}

// Verifica a extensão do arquivo
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($file_extension !== 'sql') {
    $_SESSION['settings_error_message'] = 'Formato de arquivo inválido. Apenas arquivos .sql são permitidos.';
    header("Location: $redirect_url?error=invalid_format");
    exit();
}

// 3. LÓGICA DE RESTAURAÇÃO
$tmp_file_path = $file['tmp_name'];

try {
    // Constrói o comando `mysql` para restauração
    // Usa as credenciais do config.php
    $command = sprintf(
        'mysql --host=%s --user=%s --password=%s --skip-ssl %s < %s',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($tmp_file_path)
    );

    // Executa o comando e captura a saída e o código de retorno
    exec($command, $output, $return_var);

    // 4. VERIFICAÇÃO E REDIRECIONAMENTO
    if ($return_var === 0) {
        // Sucesso
        $_SESSION['settings_success_message'] = 'Banco de dados restaurado com sucesso!';
        header("Location: $redirect_url?success=restored");
    } else {
        // Falha
        $error_details = implode("\n", $output);
        error_log("Erro na restauração v2 (SSL-FIX). Código: $return_var. Saída: $error_details");
        $_SESSION['settings_error_message'] = 'Falha na restauração (v2). O erro foi registrado nos logs. Verifique o SSL.';
        header("Location: $redirect_url?error=restore_failed_v2");
    }
} catch (Exception $e) {
    error_log("Exceção durante a restauração: " . $e->getMessage());
    $_SESSION['settings_error_message'] = 'Ocorreu um erro crítico durante o processo de restauração.';
    header("Location: $redirect_url?error=restore_exception");
} finally {
    // Garante que o arquivo temporário seja removido
    if (file_exists($tmp_file_path)) {
        unlink($tmp_file_path);
    }
}

exit();
?>
