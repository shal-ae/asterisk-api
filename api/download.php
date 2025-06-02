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

// === КОНВЕРСИЯ ПУТИ ЕСЛИ ЗАПРОШЕН WAV, НО ЕСТЬ MP3 ===
// === Совместимость с PHP 7 ===
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (str_ends_with($fullPath, '.wav') && !file_exists($fullPath)) {
    $mp3Path = substr($fullPath, 0, -4) . '.mp3';
    if (file_exists($mp3Path) ) {
        $fullPath = $mp3Path;
    }
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

$filename = basename($fullPath);
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentType = ($ext === 'mp3') ? 'audio/mpeg' : (($ext === 'wav') ? 'audio/wav' : 'application/octet-stream');

header('Content-Description: File Transfer');
header("Content-Type: $contentType");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
