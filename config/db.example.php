<?php
declare(strict_types=1);
// Copy to db.php (gitignored) with real credentials. Keep real file outside public_html on shared hosting.
return new PDO(
    'mysql:host=localhost;dbname=ho_v2;charset=utf8mb4',
    'DB_USER',
    'DB_PASS',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
