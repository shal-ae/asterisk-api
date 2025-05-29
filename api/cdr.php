<?php
// Настройки подключения к базе данных
$validApiKey = 'my-secret-key-123'; // установи свой API-ключ
$host = 'localhost';
$db = 'asteriskcdrdb';
$user = 'freepbxuser';
$pass = '492771210fa3a3dc478adeb9403615e9'; // замените на актуальный

// === ПРОВЕРКА API-KEY ===
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches) || $matches[1] !== $validApiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// Параметры GET
$src = $_GET['src'] ?? '';
$dst = $_GET['dst'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$limit = $_GET['limit'] ?? 10;
$limit = (int)$limit;

// Сбор SQL-запроса
$sql = "SELECT calldate, clid, src, dst, duration, billsec, disposition, uniqueid FROM cdr WHERE 1=1";
$params = [];

if (!empty($src)) {
    $sql .= " AND src = :src";
    $params[':src'] = $src;
}
if (!empty($dst)) {
    $sql .= " AND dst = :dst";
    $params[':dst'] = $dst;
}
if (!empty($dateFrom)) {
    $sql .= " AND calldate >= :date_from";
    $params[':date_from'] = $dateFrom . " 00:00:00";
}
if (!empty($dateTo)) {
    $sql .= " AND calldate <= :date_to";
    $params[':date_to'] = $dateTo . " 23:59:59";
}

$sql .= " ORDER BY calldate DESC LIMIT :limit";
$stmt = $pdo->prepare($sql);

// Привязка параметров
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Пути к записям
$baseDir = '/var/spool/asterisk/monitor';
$baseUrl = ''; // Замените на ваш IP или домен

foreach ($results as &$row) {
    $uniqueid = $row['uniqueid'];
    $date = new DateTime($row['calldate']);
    $subDir = $date->format('Y/m/d'); // YYYY/MM/DD
    $dirPath = "$baseDir/$subDir";
    $recordingFile = null;

    // Поиск файла, заканчивающегося на uniqueid.wav
    if (is_dir($dirPath)) {
        foreach (scandir($dirPath) as $file) {
	    if (substr($file, -strlen("$uniqueid.wav")) === "$uniqueid.wav") {
                $recordingFile = $file;
                break;
            }
        }
    }
    if ($recordingFile) {
        $row['recording_url'] = "$baseUrl/$subDir/$recordingFile";
    } else {
        $row['recording_url'] = null;
    }
}

// Ответ
header('Content-Type: application/json');
//echo json_encode($results, JSON_PRETTY_PRINT);

echo json_encode([
    'status' => 'OK',
    'content' => $results
], JSON_PRETTY_PRINT);
