<?php
if (!function_exists('is_admin')) {
    require_once __DIR__ . '/../auth.php';
}
start_secure_session();

if (!isset($conn) || !$conn instanceof mysqli) {
    require_once __DIR__ . '/../db_connect.php';
}

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
$display_page_title = $base_site_title;
$display_h1_title = $base_site_title;
if (!empty($db_specific_unidade_nome)) {
    $suffix = " - Sesc " . $db_specific_unidade_nome;
    $display_page_title .= $suffix;
    $display_h1_title .= $suffix;
}
$is_index_page = basename($_SERVER['PHP_SELF']) == 'index.php';
$h1_with_trigger = str_replace('Achados', '<span id="easter-egg-trigger">Achados</span>', $display_h1_title);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $display_page_title; ?></title>
    <link rel="stylesheet" href="/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body.ring-egg-active > header, body.ring-egg-active > main { filter: blur(5px) brightness(0.7); transition: filter 0.5s ease-out; }
        #one-ring-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 9998; opacity: 0; transition: opacity 0.5s ease-out; cursor: pointer; }
        #one-ring-text { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #FFD700; font-family: 'Times New Roman', Times, serif; font-size: 2.5em; text-align: center; z-index: 9999; opacity: 0; transition: opacity 1s ease-in; text-shadow: 0 0 5px #ffa500, 0 0 10px #ffa500, 0 0 15px #ff4500; pointer-events: none; }
        header { user-select: none; } #easter-egg-trigger { cursor: default; }
    </style>
</head>
<body>
    <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 1050;"></div>
    <div id="one-ring-overlay" style="display: none;"></div>
    <div id="one-ring-text" style="display: none;"></div>
    <header>
        <h1><?php echo $h1_with_trigger; ?></h1>
        <nav>
            <ul>
                <?php if (is_logged_in()): ?>
                    <?php $user_role = $_SESSION['role'] ?? 'common'; ?>

                    <?php // Módulos: Home e Termos (Visível para admin, admin-aprovador, superAdmin) ?>
                    <?php if (in_array($user_role, ['admin', 'admin-aprovador', 'superAdmin'])): ?>
                        <li><a href="/home.php">Home</a></li>
                    <?php endif; ?>

                    <?php // Módulo: Cadastrar Item (Visível para todos os perfis logados) ?>
                    <li><a href="/register_item_page.php">Cadastrar Item</a></li>

                    <?php // Módulos: Home e Termos (Visível para admin, admin-aprovador, superAdmin) ?>
                    <?php if (in_array($user_role, ['admin', 'admin-aprovador', 'superAdmin'])): ?>
                        <li><a href="/manage_terms.php">Termos</a></li>
                    <?php endif; ?>

                    <?php // Módulo: Aprovações de Termos (Visível para admin-aprovador, superAdmin) ?>
                    <?php if (is_approver()): ?>
                        <li><a href="/approvals_page.php">Aprovações de Termos</a></li>
                    <?php endif; ?>
                    
                    <?php // Módulos de Administração (Visíveis para admin, admin-aprovador, superAdmin) ?>
                    <?php if (is_admin()): ?>
                        <li><a href="/admin/dashboard.php">Dashboard</a></li>
                        <li><a href="/admin/manage_categories.php">Categorias</a></li>
                        <li><a href="/admin/manage_locations.php">Locais</a></li>
                        <li><a href="/admin/manage_companies_page.php">Empresas</a></li>
                    <?php endif; ?>

                    <?php //Módulo "Usuários" agora visível apenas para superAdmin ?>
                    <?php if (is_super_admin()): ?>
                         <li><a href="/admin/manage_users.php">Usuários</a></li>
                    <?php endif; ?>

                    <?php // Módulo: Configurações (Visível apenas para superAdmin) ?>
                    <?php if (is_super_admin()): ?>
                        <li><a href="/admin/settings_page.php">Configurações</a></li>
                    <?php endif; ?>
                    
                    <?php // Módulo: Sair (Visível para todos os perfis logados) ?>
                    <li><a href="/logout_handler.php">Sair (<?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuário'); ?>)</a></li>

                <?php elseif (!$is_index_page): ?>
                    <li><a href="/index.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('easter-egg-trigger');
    const overlay = document.getElementById('one-ring-overlay');
    const textElement = document.getElementById('one-ring-text');
    if (trigger && overlay && textElement) {
        let clickCount = 0, clickTimer = null, requiredClicks = 7;
        trigger.addEventListener('click', () => {
            clickCount++;
            clearTimeout(clickTimer);
            clickTimer = setTimeout(() => { clickCount = 0; }, 2000);
            if (clickCount === requiredClicks) {
                clickCount = 0;
                clearTimeout(clickTimer);
                activateRingEgg();
            }
        });
        function typeWriter(text, i) { if (i < text.length) { textElement.innerHTML += text.charAt(i); setTimeout(() => typeWriter(text, i + 1), 100); } }
        function activateRingEgg() {
            document.body.classList.add('ring-egg-active');
            overlay.style.display = 'block';
            textElement.style.display = 'block';
            setTimeout(() => { overlay.style.opacity = '1'; textElement.style.opacity = '1'; }, 10);
            const ringVerse = "Vá trabalhar!!!!";
            textElement.innerHTML = '';
            typeWriter(ringVerse, 0);
            overlay.addEventListener('click', deactivateRingEgg, { once: true });
        }
        function deactivateRingEgg() {
            overlay.style.opacity = '0';
            textElement.style.opacity = '0';
            document.body.classList.remove('ring-egg-active');
            setTimeout(() => { overlay.style.display = 'none'; textElement.style.display = 'none'; }, 500);
        }
    }
});
</script>