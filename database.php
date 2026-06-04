<?php
declare(strict_types=1);

/**
 * Hoosier Online Sales Portal database connection placeholder.
 * Fill credentials after the MySQL database/user is created in cPanel.
 */
function ho_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = 'localhost';
    $dbname = 'spofnkte_db';
    $user = 'spofnkte_user';
    $pass = '4acc6d8b!A1';

    $pdo = new PDO('mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4', $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
