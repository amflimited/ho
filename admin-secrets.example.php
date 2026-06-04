<?php
declare(strict_types=1);

/**
 * Copy this file to either:
 *   1) ../hoosier-admin-secrets.php  [preferred, outside public_html]
 *   2) ./admin-secrets.php           [fallback]
 *
 * Generate a password hash with:
 * php -r "echo password_hash('YOUR_PASSWORD_HERE', PASSWORD_DEFAULT);"
 */
return [
    'username' => 'operator',
    'password_hash' => 'PASTE_PASSWORD_HASH_HERE',
    'session_key' => 'PASTE_RANDOM_64_CHAR_HEX_STRING_HERE',
];
