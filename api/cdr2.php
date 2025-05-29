<?php
// === НАСТРОЙКИ ===
$validApiKey = 'my-secret-key-123'; // Замените на ваш ключ
$host = 'localhost';
$db = 'asteriskcdrdb';
$user = 'freepbxuser';
$pass = '492771210fa3a3dc478adeb9403615e9'; // Замените на ваш пароль

$baseDir = '/var/spool/asterisk/monitor';

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
$min_duration =  (int)($_GET['min_duration'] ?? 0);
$answered = $_GET['answered'] ?? '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(1000, (int)($_GET['per_page'] ?? 100)));
$offset = ($page - 1) * $perPage;

// === ПОДКЛЮЧЕНИЕ К БД ===
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// === ПОСТРОЕНИЕ ОСНОВНОГО SQL ===
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

if ($min_duration > 0) {
    $sqlWhere .= " AND duration >= :min_duration";
    $params[':min_duration'] = $min_duration;
}

if ($answered == '0') {
    $sqlWhere .= " AND disposition = 'NO ANSWER'";
}

if ($answered == '1') {
    $sqlWhere .= " AND disposition = 'ANSWERED'";
}

// === ПОЛУЧЕНИЕ ОБЩЕГО КОЛИЧЕСТВА ===
$countSql = "SELECT COUNT(*) FROM cdr $sqlWhere";
$stmtCount = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $stmtCount->bindValue($key, $value);
}
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();

// === ПОЛУЧЕНИЕ ДАННЫХ ===
$dataSql = "SELECT calldate, clid, src, dst, channel, dstchannel, duration, billsec, disposition, uniqueid, dcontext, linkedid FROM cdr $sqlWhere ORDER BY calldate DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($dataSql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);


function extractExtension($channel) {
    if (preg_match('/(?:SIP|PJSIP|Local)\/(\d+)(?:\-|\/)/', $channel, $m)) {
        return $m[1];
    }
    return null;
}


$linkedidCount = []; // key = linkedid, value = count


// === ДОБАВЛЕНИЕ ССЫЛОК НА ЗАПИСИ ===
foreach ($results as &$row) {
    $linkedid = $row['linkedid'];
    if (!isset($linkedidCount[$linkedid])) $linkedidCount[$linkedid] = 0;
    $linkedidCount[$linkedid]++;


    $uniqueid = $row['uniqueid'];
    try {
        $date = new DateTime(trim($row['calldate']));
        $subDir = $date->format('Y/m/d');
        $dirPath = "$baseDir/$subDir";
        $recordingFile = null;

        if (is_dir($dirPath)) {
            foreach (scandir($dirPath) as $file) {
		if (substr($file, -strlen("$uniqueid.wav")) === "$uniqueid.wav" ||
		    substr($file, -strlen("$uniqueid.mp3")) === "$uniqueid.mp3") {
		        $recordingFile = $file;
		        break;
                }
            }
        }

        $row['recording_url'] = $recordingFile ? "$subDir/$recordingFile" : null;

	$row['src_ext'] = extractExtension($row['channel']);
	$row['dst_ext'] = extractExtension($row['dstchannel']);

    } catch (Exception $e) {
        error_log("Ошибка в дате: {$row['calldate']}");
        $row['recording_url'] = null;
    }
}

foreach ($results as &$row) {
    $row['was_transferred'] = ($linkedidCount[$row['linkedid']] > 1);
}


// === ВОЗВРАТ JSON ===
header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'pages_count' => ceil($total / $perPage),
    'content' => $results
], JSON_PRETTY_PRINT);

