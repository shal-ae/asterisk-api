<?php
// === НАСТРОЙКИ ===
$validApiKey = 'my-secret-call-key';  // замените на свой ключ
$outgoingDir = '/var/spool/asterisk/outgoing';
$tempDir = '/tmp';
$context = 'from-internal';

// === ПРОВЕРКА API-KEY ===
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches) || $matches[1] !== $validApiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// === ПАРАМЕТРЫ ===
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// === ВАЛИДАЦИЯ ===
if (!preg_match('/^\d{2,6}$/', $from)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid from number']);
    exit;
}
if (!preg_match('/^\d{3,15}$/', $to)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid to number']);
    exit;
}

// === СОДЕРЖИМОЕ ФАЙЛА ===
$fileContent = 
    "Channel: Local/{$from}@{$context}\r\n" .
    "Callerid: Web Call <{$from}>\r\n" .
    "Context: {$context}\r\n" .
    "Extension: {$to}\r\n" .
    "Priority: 1\r\n" .
    "MaxRetries: 2\r\n" .
    "RetryTime: 60\r\n" .
    "WaitTime: 30\r\n";

// === СОЗДАНИЕ ФАЙЛА ===
$tempFile = tempnam($tempDir, 'call-');
file_put_contents($tempFile, $fileContent);

// === УСТАНОВКА ПРАВ И ПЕРЕНОС ===
chown($tempFile, 'asterisk');
chgrp($tempFile, 'asterisk');
chmod($tempFile, 0644);

$destFile = $outgoingDir . '/' . basename($tempFile);
rename($tempFile, $destFile);

// === ОТВЕТ ===
header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'from' => $from,
    'to' => $to,
    'file' => basename($destFile)
]);

