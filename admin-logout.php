<?php
declare(strict_types=1);

require __DIR__ . '/admin-auth.php';

ho_admin_logout();

header('Location: /admin-login.php', true, 302);
exit;
