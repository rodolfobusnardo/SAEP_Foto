<?php
// Inclui e inicia a sessão segura ANTES de qualquer outra lógica ou saída.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php'; // Include config for version number
start_secure_session();

// If user is already logged in, redirect to home.php
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

require_once __DIR__ . '/db_connect.php';
$db_specific_unidade_nome = '';
if (isset($conn) && $conn instanceof mysqli) {
    $stmt_settings = $conn->prepare("SELECT unidade_nome FROM settings WHERE config_id = 1");
    if ($stmt_settings) {
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        if ($result_settings->num_rows > 0) {
            $row_settings = $result_settings->fetch_assoc();
            if (!empty(trim($row_settings['unidade_nome'] ?? ''))) {
                $db_specific_unidade_nome = htmlspecialchars(trim($row_settings['unidade_nome'] ?? ''));
            }
        }
        $stmt_settings->close();
    }
}

$base_site_title = "Sistema de Achados e Perdidos";
$display_h1_title = $base_site_title;
if (!empty($db_specific_unidade_nome)) {
    $suffix = " - Sesc " . $db_specific_unidade_nome;
    $display_h1_title .= $suffix;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Achados e Perdidos</title>
    <style>
        /* --- Reset Básico e Estilo do Corpo --- */
        :root {
            --primary-blue: #007bff;
            --light-blue: #f0f8ff;
            --dark-blue: #0056b3;
            --success-green-bg: #d4edda;
            --success-green-border: #c3e6cb;
            --success-green-text: #155724;
            --error-red-bg: #f8d7da;
            --error-red-border: #f5c6cb;
            --error-red-text: #721c24;
            --light-gray-bg: #f0f2f5;
            --border-color: #ced4da;
        }

        html, body {
            height: 100%;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--light-gray-bg);
            display: flex;
            flex-direction: column;
        }

        /* --- Header e Footer --- */
        .page-header, .page-footer {
            background-color: var(--primary-blue);
            color: white;
            padding: 20px;
            text-align: center;
            flex-shrink: 0;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.8em;
        }
        .page-footer {
            font-size: 0.9em;
        }

        /* --- Container Principal do Login --- */
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            padding: 20px;
        }

        /* --- Card de Login --- */
        .login-card {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
        }

        /* --- Formulário --- */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        
        /* --- Botão de Login --- */
        .login-button {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px; /* Adiciona um espaço acima do botão */
        }

        .login-button:hover {
            background-color: var(--dark-blue);
        }

        /* --- Mensagens de Alerta --- */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 5px;
            text-align: center;
        }
        .success-message {
            color: var(--success-green-text);
            background-color: var(--success-green-bg);
            border-color: var(--success-green-border);
        }
        .error-message {
            color: var(--error-red-text);
            background-color: var(--error-red-bg);
            border-color: var(--error-red-border);
        }
    </style>
</head>
<body>

    <header class="page-header">
        <h1><?php echo $display_h1_title; ?></h1>
    </header>

    <main class="login-wrapper">
        <div class="login-card">
            
            <?php
            if (isset($_GET['error'])) {
                $errorMessage = '';
                switch ($_GET['error']) {
                    case 'emptyfields': $errorMessage = 'Por favor, preencha usuário e senha.'; break;
                    case 'sqlerror': $errorMessage = 'Erro no sistema. Tente novamente mais tarde.'; break;
                    case 'wrongpassword': $errorMessage = 'Usuário ou senha incorreta.'; break;
                    case 'nouser': $errorMessage = 'Usuário não encontrado no sistema.'; break;
                    case 'pleaselogin': $errorMessage = 'Por favor, faça login para continuar.'; break;
                    case 'usernametoolong': $errorMessage = 'Nome de usuário muito longo.'; break;
                    case 'usernametooshort': $errorMessage = 'Nome de usuário muito curto.'; break;
                    case 'ad_auth_failed': $errorMessage = 'Falha na autenticação. Verifique seu usuário e senha.'; break;
                    case 'user_not_registered_in_app': $errorMessage = 'Usuário não cadastrado na aplicação. Contate o administrador.'; break;
                    case 'ldap_connection_failed': $errorMessage = 'Não foi possível conectar ao servidor de autenticação.'; break;
                    case 'ldap_search_failed_or_user_not_found': $errorMessage = 'Usuário não encontrado no servidor de autenticação.'; break;
                    default: $errorMessage = 'Ocorreu um erro desconhecido durante o login.';
                }
                echo '<p class="error-message message">' . htmlspecialchars($errorMessage) . '</p>';
            }
            if (isset($_GET['message']) && $_GET['message'] == 'loggedout') {
                echo '<p class="success-message message">Você foi desconectado com sucesso.</p>';
            }
            ?>

            <form action="login_handler.php" method="POST">
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-button">Entrar</button>
            </form>
        </div>
    </main>

    <footer class="page-footer">
        &copy; <?php echo date("Y"); ?> Sistema de Achados e Perdidos - Versão <?php echo defined('APP_VERSION') ? APP_VERSION : 'N/A'; ?>
    </footer>

</body>
</html>