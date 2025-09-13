<?php

require_once __DIR__ . '/ldap_config.php';

function authenticate_ad_user($username_from_form, $password) {
    if (empty($password)) {
        return 'empty_password';
    }

    $ldap_conn = null;

    // Tentar conectar a cada host AD definido (usaremos apenas o primeiro, como no exemplo)
    // O exemplo funcional usa AD_HOSTS[0] diretamente.
    $ldap_host_uri = (AD_USE_LDAPS ? 'ldaps://' : 'ldap://') . AD_HOSTS[0];
    $ldap_conn = @ldap_connect($ldap_host_uri, AD_PORT);

    if (!$ldap_conn) {
        $error_detail = "Host: $ldap_host_uri, Port: " . AD_PORT . ".";
        if (function_exists('ldap_errno') && function_exists('ldap_err2str')) {
            $last_ldap_errno = ldap_errno(null); // Tenta obter erro global
            if ($last_ldap_errno != 0) {
                $error_detail .= " LDAP Error No: " . $last_ldap_errno . " - " . ldap_err2str($last_ldap_errno);
            }
        }
        error_log("LDAP connection failed: $error_detail");
        return 'ldap_connection_failed';
    }

    // Definir opções LDAP
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // Configuração de TLS (copiado do exemplo funcional)
    if (defined('AD_LDAP_TLS_REQUIRE_CERT')) {
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, AD_LDAP_TLS_REQUIRE_CERT);
    }

    if (!AD_USE_LDAPS && defined('AD_LDAP_START_TLS') && AD_LDAP_START_TLS) {
        if (!@ldap_start_tls($ldap_conn)) {
            $tls_error_detail = ldap_error($ldap_conn);
            error_log("LDAP StartTLS Error for $username_from_form: " . $tls_error_detail);
            ldap_close($ldap_conn);
            return 'ldap_tls_failed';
        }
    }

    // Construir o usuário para o bind (ex: user@domain)
    // $username_from_form é o que o usuário digitou no formulário.
    $bind_user = $username_from_form;
    if (strpos($username_from_form, '@') === false) {
        // Derivar o domínio do AD_BASE_DN (como no exemplo funcional)
        $dc_parts = explode(',', AD_BASE_DN);
        $domain_parts = [];
        foreach ($dc_parts as $part) {
            if (stripos($part, 'dc=') === 0) { // stripos para case-insensitive
                $domain_parts[] = substr($part, 3);
            }
        }
        $derived_domain = implode('.', $domain_parts);
        if (!empty($derived_domain)) {
            $bind_user = $username_from_form . '@' . $derived_domain;
        }
        // Se não for possível derivar o domínio, $bind_user permanece como $username_from_form.
        // Ou podemos usar AD_DOMAIN_CONTROLLERS[0] como fallback se $derived_domain estiver vazio.
        // O exemplo funcional não mostra um fallback explícito aqui, usa o que foi derivado.
        // Se $derived_domain for vazio e AD_DOMAIN_CONTROLLERS existir, podemos usar:
        // else if (!empty(AD_DOMAIN_CONTROLLERS[0])) {
        //    $bind_user = $username_from_form . '@' . AD_DOMAIN_CONTROLLERS[0];
        // }
        // Por enquanto, vamos manter estritamente como o exemplo: só deriva do Base DN.
    }

    // Tentar autenticar (bind) com o usuário construído e a senha
    if (@ldap_bind($ldap_conn, $bind_user, $password)) {
        ldap_close($ldap_conn);
        return true; // Autenticação bem-sucedida
    } else {
        $ldap_error = ldap_error($ldap_conn);
        error_log("LDAP Bind Failed for user '$bind_user' (original form input: '$username_from_form'). Error: $ldap_error");
        ldap_close($ldap_conn);
        // Retornar um erro que possa ser mapeado para "usuário ou senha inválidos"
        // ou um erro mais específico se o bind falhar por outros motivos que não credenciais.
        // O exemplo funcional retorna 'Usuário ou senha inválidos (AD)'
        return 'ldap_bind_failed'; // Este será mapeado em login_handler.php
    }
}

?>
