<?php
// auth.php - CORRIGIDO

function start_secure_session() {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
    }

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['last_access'] = time();
}

function is_logged_in() {
    start_secure_session();
    return isset($_SESSION['user_id']);
}

function require_login($redirect_url = '/index.php') {
    if (!is_logged_in()) {
        header("Location: " . $redirect_url . "?error=pleaselogin");
        exit();
    }
}

function is_admin() {
    if (!is_logged_in()) {
        return false;
    }
    // CORREÇÃO: Verificando a variável de sessão correta -> 'role'
    return isset($_SESSION['role']) &&
           ($_SESSION['role'] === 'admin' ||
            $_SESSION['role'] === 'admin-aprovador' ||
            $_SESSION['role'] === 'superAdmin');
}

function is_super_admin() {
    if (!is_logged_in()) {
        return false;
    }
    // CORREÇÃO: Verificando a variável de sessão correta -> 'role'
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superAdmin';
}

function is_approver() {
    if (!is_logged_in()) {
        return false;
    }
    // CORREÇÃO: Verificando a variável de sessão correta -> 'role'
    return isset($_SESSION['role']) &&
           ($_SESSION['role'] === 'admin-aprovador' ||
            $_SESSION['role'] === 'superAdmin');
}

function require_admin($redirect_url = '/home.php', $error_message = 'Acesso negado. Permissões de administrador necessárias.') {
    if (!is_admin()) {
        if ($redirect_url === '/index.php' || $redirect_url === '/home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
             header("Location: /home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

function require_super_admin($redirect_url = '/home.php', $error_message = 'Acesso negado. Permissões de super administrador necessárias.') {
    if (!is_super_admin()) {
        if ($redirect_url === '/index.php' || $redirect_url === '/home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
             header("Location: /home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

function require_admin_api() {
    if (!is_admin()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Acesso negado. Permissões de administrador necessárias.']);
        exit();
    }
}

// ==================================================================
// NOVA FUNÇÃO DE SEGURANÇA ADICIONADA
// ==================================================================
/**
 * Exige que o usuário tenha permissão de aprovador.
 * Redireciona para a home caso não tenha, com uma mensagem de erro.
 */
function require_approver($redirect_url = '/home.php', $error_message = 'Acesso negado. Você não tem permissão para aprovar termos.') {
    if (!is_approver()) {
        header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        exit();
    }
}
// ==================================================================

?>