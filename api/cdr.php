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
$host = $config['db_host'] ?? 'localhost';
$db = $config['db_name_cdr'] ?? 'asteriskcdrdb';
$user = $config['db_user'] ?? 'freepbxuser';
$pass = $config['db_pass'];
$baseDir = $config['recordings_path'];
$order_direction = 'ASC';

// === АВТОРИЗАЦИЯ ===
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches) || $matches[1] !== $validApiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// === ПАРАМЕТРЫ ЗАПРОСА ===
$src = $_GET['src'] ?? '';
$dst = $_GET['dst'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(1000, (int)($_GET['per_page'] ?? 100)));
$minDuration = isset($_GET['min_duration']) ? (int)$_GET['min_duration'] : null;
$answered = isset($_GET['answered']) ? (int)$_GET['answered'] : null;
$fieldset = $_GET['fieldset'] ?? ''; // all - для отладки

$offset = ($page - 1) * $perPage;

function extractExtension($channel)
{
    if (preg_match('/(?:SIP|PJSIP|Local)\/(\d+)(?:\-|\/)/', $channel, $m)) {
        return $m[1];
    }
    return null;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

function getTrunkNameFromChannel($channel)
{
    if (preg_match('/^(SIP|PJSIP|DAHDI|IAX2)\\/([\\w\\-\\.]+?)-\\w+$/', $channel, $m)) {
        $name = $m[2];
        if (!preg_match('/^\\d{2,6}$/', $name)) {
            return $name;
        }
    }
    return null;
}

// === SQL ===
$sqlWhere = "WHERE 1=1";
$params = [];

if (!empty($src)) {
    $sqlWhere .= " AND src = :src";
    $params[':src'] = $src;
}
if (!empty($dst)) {
    $sqlWhere .= " AND dst = :dst";
    $params[':dst'] = $dst;
}
if (!empty($dateFrom)) {
    $sqlWhere .= " AND calldate >= :date_from";
    $params[':date_from'] = $dateFrom . " 00:00:00";
}
if (!empty($dateTo)) {
    $sqlWhere .= " AND calldate <= :date_to";
    $params[':date_to'] = $dateTo . " 23:59:59";
}
if (!is_null($minDuration)) {
    $sqlWhere .= " AND duration > :min_duration";
    $params[':min_duration'] = $minDuration;
}
if (!is_null($answered)) {
    if ($answered === 1) {
        $sqlWhere .= " AND disposition = 'ANSWERED'";
    } elseif ($answered === 0) {
        $sqlWhere .= " AND disposition != 'ANSWERED'";
    }
}

$countSql = "SELECT COUNT(*) FROM cdr $sqlWhere";
$stmtCount = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $stmtCount->bindValue($key, $value);
}
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();

$fields = "uniqueid, linkedid, calldate, clid, accountcode, src, dst, channel, dstchannel, dcontext, duration, billsec, disposition";
if ($fieldset === 'all') {
    $fields = "*";
}

$dataSql = "SELECT $fields FROM cdr $sqlWhere ORDER BY calldate $order_direction , uniqueid $order_direction LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($dataSql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as &$row) {
    $uniqueid = $row['uniqueid'];
    $row['src_ext'] = extractExtension($row['channel']);
    $row['dst_ext'] = extractExtension($row['dstchannel']);
    $row['src_trunk'] = getTrunkNameFromChannel($row['channel']);
    $row['dst_trunk'] = getTrunkNameFromChannel($row['dstchannel']);

    $recordingFiles = [];
    $date = new DateTime(trim($row['calldate']));
    $subDir = $date->format('Y/m/d');
    $dirPath = "$baseDir/$subDir";

    $patternWav = "$dirPath/*$uniqueid.wav";
    $patternMp3 = "$dirPath/*$uniqueid.mp3";
    $matches = array_merge(glob($patternWav), glob($patternMp3));

    foreach ($matches as $match) {
        $recordingFiles[] = "$subDir/" . basename($match);
    }

    $row['recording_urls'] = $recordingFiles;
}

$seen = [];
$grouped = [];
foreach ($results as $row) {
    // Пропускаем дубликаты по uniqueid
    $uid = $row['uniqueid'];
    if (isset($seen[$uid])) {
        continue;
    }
    $seen[$uid] = true;

    $linkedid = $row['linkedid'] ?? $row['uniqueid'];
    if (!isset($grouped[$linkedid])) {
        $grouped[$linkedid] = [];
    }
    $grouped[$linkedid][] = $row;
}

$groupedArray = [];
foreach ($grouped as $linkedid => $records) {
    $groupedArray[] = [
        'linkedid' => $linkedid,
        'items' => $records
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'pages_count' => ceil($total / $perPage),
    'content' => $groupedArray
], JSON_PRETTY_PRINT);

