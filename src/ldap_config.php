<?php

// Active Directory Configuration
define('AD_HOSTS', ['10.58.155.1']); // Array of AD hosts
define('AD_PORT', 389);             // Default LDAP port
define('AD_USE_LDAPS', false);      // Set to true if using LDAPS (LDAP over SSL)
define('AD_DOMAIN_CONTROLLERS', ['sescsp.org.br']); // For binding (e.g., your AD domain name)
define('AD_BASE_DN', 'dc=sescsp,dc=local'); // Base DN for your domain
define('AD_ADMIN_USERNAME', null); // Optional: Admin username for binding (if required for searching)
define('AD_ADMIN_PASSWORD', null); // Optional: Admin password for binding

// TLS specific settings
define('AD_LDAP_START_TLS', false); // Set to true to use StartTLS
define('AD_LDAP_TLS_REQUIRE_CERT', 0); // LDAP_OPT_X_TLS_REQUIRE_CERT setting (0 = LDAP_OPT_X_TLS_NEVER)

// Optional: Define which attribute is used for the username in AD
define('AD_USER_ID_ATTRIBUTE', 'samaccountname'); // or 'userprincipalname'

?>
