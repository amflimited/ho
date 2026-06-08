<?php
error_reporting(E_ALL); ini_set('display_errors', '1');
echo "Step 1: PHP running\n";
require_once __DIR__ . '/database.php';
echo "Step 2: database.php loaded\n";
require_once __DIR__ . '/ho-model.php';
echo "Step 3: ho-model.php loaded\n";
$pdo = ho_db();
echo "Step 4: DB connected\n";
