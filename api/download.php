<?php
// Загружает файл записи разговора
// curl -H "Authorization: Bearer my-secret-key-123" \
//  -o out.wav \
// "http://your-server-ip/api/download.php?file=2025/05/27/имя_файла.wav"


$validApiKey = 'my-secret-key-123';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches) || $matches[1] !== $validApiKey) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$baseDir = '/var/spool/asterisk/monitor';
$relPath = $_GET['file'] ?? '';

if (!preg_match('#^[0-9]{4}/[0-9]{2}/[0-9]{2}/[-\w\.]+\.wav$#', $relPath)) {
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

header('Content-Type: audio/wav');
header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
readfile($fullPath);

