<?php
declare(strict_types=1);

// Copy this file to:
// /home1/spofnkte/hoosier-online-private/admin-secrets.php
//
// Then update the username/password hash. Generate a hash using:
// php -r "echo password_hash('YOUR-NEW-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"

return [
    'username' => 'operator',
    'password_hash' => '$2y$10$REPLACE_WITH_REAL_PASSWORD_HASH',
];
