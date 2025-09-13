<?php
// 1. AUTENTICAÇÃO E AUTORIZAÇÃO
require_once '../auth.php';
require_once '../config.php'; // Para as credenciais do banco

// Inicia a sessão e exige privilégios de super admin
start_secure_session();
require_super_admin();

// 2. LÓGICA DE BACKUP
try {
    // Define o nome do arquivo de backup com a data e hora
    $backup_filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';

    // Constrói o comando `mysqldump`
    // Usa as constantes definidas em config.php
    // Adiciona --no-create-info para não incluir a declaração CREATE DATABASE
    // Adiciona --skip-triggers para evitar problemas com triggers
    // Adiciona --no-tablespaces para evitar erros de permissão em alguns ambientes.
    // Adiciona --add-drop-table para garantir que a restauração não falhe por chaves duplicadas.
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --skip-ssl %s --no-tablespaces --add-drop-table --skip-triggers',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME)
    );

    // 3. EXECUÇÃO E OUTPUT
    // Define os headers para forçar o download do arquivo
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup_filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Executa o comando e envia a saída diretamente para o output
    // passthru() é ideal para comandos que geram dados binários ou texto puro
    passthru($command, $return_var);

    // Verifica se o comando foi executado com sucesso
    if ($return_var !== 0) {
        // Se houver um erro, loga e redireciona com uma mensagem de erro
        error_log("Erro ao executar mysqldump. Código de retorno: " . $return_var);
        // Não é possível redirecionar após o envio de headers, mas o log é importante.
        // O usuário verá um arquivo vazio ou corrompido, e o erro estará no log do servidor.
    }

    exit();

} catch (Exception $e) {
    // Em caso de exceção, loga o erro.
    error_log("Falha no processo de backup: " . $e->getMessage());

    // Tenta redirecionar para a página de configurações com uma mensagem de erro,
    // se nenhum header tiver sido enviado ainda.
    if (!headers_sent()) {
        $_SESSION['settings_error_message'] = 'Ocorreu um erro inesperado durante o backup.';
        header('Location: settings_page.php?error=backup_failed');
    }

    exit();
}
?>
