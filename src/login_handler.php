<?php
session_start();
require_once 'db_connect.php'; // For database connection
require_once __DIR__ . '/ldap_auth.php'; // For AD authentication

// List of users to be authenticated locally
define('LOCAL_USERS', ['admin', 'info']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        header('Location: index.php?error=emptyfields');
        exit();
    }
    if (strlen($username) > 255) {
        header('Location: index.php?error=usernametoolong');
        exit();
    }
    if (strlen($username) < 1) {
        header('Location: index.php?error=usernametooshort');
        exit();
    }

    if (in_array(strtolower($username), array_map('strtolower', LOCAL_USERS))) {
        // --- Local Authentication Logic ---
        $sql = "SELECT id, username, password, role, full_name FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("SQL Prepare Error for local user in login_handler.php: " . $conn->error);
            header('Location: index.php?error=sqlerror');
            exit();
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role']; 
                if ($_SESSION['role'] === 'common') {
                    header('Location: register_item_page.php');
                } else {
                    header('Location: home.php');
                }
                exit();
            } else {
                header('Location: index.php?error=wrongpassword');
                exit();
            }
        } else {
            header('Location: index.php?error=nouser');
            exit();
        }
        $stmt->close();
    } else {
        // --- AD Authentication Logic ---
        $ad_auth_result = authenticate_ad_user($username, $password);
        if ($ad_auth_result === true) {
            $sql = "SELECT id, username, role, full_name FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("SQL Prepare Error for AD user in login_handler.php: " . $conn->error);
                header('Location: index.php?error=sqlerror');
                exit();
            }
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                if ($_SESSION['role'] === 'common') {
                    header('Location: register_item_page.php');
                } else {
                    header('Location: home.php');
                }
                exit();
            } else {
                header('Location: index.php?error=user_not_registered_in_app');
                exit();
            }
            $stmt->close();
        } else {
            $error_param = 'ad_auth_failed';
            switch ($ad_auth_result) {
                case 'empty_password': $error_param = 'emptyfields'; break;
                case 'ldap_connection_failed': case 'ldap_tls_failed': $error_param = 'ldap_connection_failed'; break;
                case 'ldap_bind_failed': $error_param = 'wrongpassword'; break;
                default: $error_param = 'ad_auth_failed';
            }
            header('Location: index.php?error=' . $error_param);
            exit();
        }
    }
} else {
    header('Location: index.php');
    exit();
}
$conn->close();
?>