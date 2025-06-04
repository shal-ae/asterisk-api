<?php
$config = include __DIR__ . '/.api.env.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

check_auth($config['api_key']);
$pdo = dbConnect($config, false);

$stmt = $pdo->query("SELECT extension, name FROM users ORDER BY extension");
$extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'count' => count($extensions),
    'extensions' => $extensions
], JSON_PRETTY_PRINT);
