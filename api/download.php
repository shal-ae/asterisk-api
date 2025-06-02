<?php

// === НАСТРОЙКИ ===
$configFile = __DIR__ . '/.api.env.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found']);
    exit;
}
$config = include $configFile;

$validApiKey = $config['api_key'];
$baseDir = $config['recordings_path'];
$relPath = $_GET['file'] ?? '';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches) || $matches[1] !== $validApiKey) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}


if (!preg_match('#^[0-9]{4}/[0-9]{2}/[0-9]{2}/[-\w\.]+\.(wav|mp3)$#', $relPath)) {
    http_response_code(400);
    echo "Invalid file path.";
    exit;
}

$fullPath = "$baseDir/$relPath";

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

$ext = pathinfo($fullPath, PATHINFO_EXTENSION);
switch (strtolower($ext)) {
    case 'mp3':
        header('Content-Type: audio/mpeg');
        break;
    case 'wav':
        header('Content-Type: audio/wav');
        break;
    default:
        header('Content-Type: application/octet-stream');
}

header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
readfile($fullPath);

