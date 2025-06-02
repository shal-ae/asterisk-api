<?php
// === Совместимость с PHP 7 ===
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

// === НАСТРОЙКИ ===
$configFile = __DIR__ . '/.api.env.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found']);
    exit;
}
$config = include $configFile;

$validApiKey = $config['api_key'];
$host = $config['db_host'] ?? 'localhost';
$db = $config['db_name_cdr'] ?? 'asteriskcdrdb';
$user = $config['db_user'] ?? 'freepbxuser';
$pass = $config['db_pass'];
$baseDir = $config['recordings_path'];

$convert = isset($_GET['convert-to-mp3']) && $_GET['convert-to-mp3'] == '1';
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
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$filenameBase = substr(basename($fullPath), 0, strrpos(basename($fullPath), '.'));
$dir = dirname($fullPath);
$mp3Path = "$dir/$filenameBase.mp3";
$wavPath = "$dir/$filenameBase.wav";

// === Авто-конвертация WAV → MP3
if (
    $convert &&
    !file_exists($mp3Path) &&
    file_exists($wavPath)
) {
    $cmd = "sox " . escapeshellarg($wavPath) . " -t wav - | lame -b 64 - " . escapeshellarg($mp3Path);
//    $cmd = "sox " . escapeshellarg($fullPath) . " " . escapeshellarg($mp3Path);    - только sox
    exec($cmd, $out, $ret);
    if ($ret !== 0 || !file_exists($mp3Path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Conversion failed']);
        exit;
    }

    $pdo = new PDO("mysql:host={$host};dbname={$db}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("UPDATE cdr SET recordingfile = :new_file WHERE recordingfile = :old_file");
    $stmt->execute([
        ':new_file' => basename($mp3Path),
        ':old_file' => basename($wavPath)
    ]);

    $fullPath = $mp3Path;
    $ext = 'mp3';

    @unlink($wavPath);
}

// === WAV отсутствует? fallback на MP3
if ($ext === 'wav' && !file_exists($fullPath) && file_exists($mp3Path)) {
    $fullPath = $mp3Path;
    $ext = 'mp3';
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
