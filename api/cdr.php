<?php

$config = include __DIR__ . '/.api.env.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/logic/linked-id-map.php';
require_once __DIR__ . '/logic/fetch-cel.php';
require_once __DIR__ . '/logic/fix-disposition.php';
require_once __DIR__ . '/logic/utils.php';
require_once __DIR__ . '/logic/keep-one-answered.php';

check_auth($config['api_key']);
$pdo = dbConnect($config);

$baseDir = $config['recordings_path'];
$order_direction = 'ASC';

// === ПАРАМЕТРЫ ЗАПРОСА ===
$src = $_GET['src'] ?? '';
$dst = $_GET['dst'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(1000, (int)($_GET['per_page'] ?? 100)));
$offset = ($page - 1) * $perPage;
$minDuration = isset($_GET['min_duration']) ? (int)$_GET['min_duration'] : null;
$answered = isset($_GET['answered']) ? (int)$_GET['answered'] : null;
$keep_one_answered_for_uniqueid = isset($_GET['keep_one_answered_for_uniqueid']) && ($_GET['keep_one_answered_for_uniqueid'] === '1');
$skip_no_answer_internal = isset($_GET['skip_no_answer_internal']) && ($_GET['skip_no_answer_internal'] === '1');
$internal_only_answered = isset($_GET['internal_only_answered']) && ($_GET['internal_only_answered'] === '1');
$use_cel = isset($_GET['use_cel']) && ($_GET['use_cel'] === '1');
$fieldset = $_GET['fieldset'] ?? ''; // all - для отладки

// осторожно, евристика!
$fix_disposition = isset($_GET['fix_disposition']) && ($_GET['fix_disposition'] === '1');


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
$db_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Дозаполняем, ищем файлы записей
foreach ($db_results as &$row) {
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
unset($row);

// Фильтруем выборку
$results = [];
foreach ($db_results as $row) {
    if (is_null($row['src_trunk']) && is_null($row['dst_trunk'])) {
        if ($skip_no_answer_internal && $row['disposition'] === 'NO ANSWER') {
            continue;
        }
        if ($internal_only_answered && $row['disposition'] !== 'ANSWERED') {
            continue;
        }
    }
    $results[] = $row;
}
unset($row);

// Сбор уникальных linkedid из результатов
$linkedIds = [];
foreach ($results as $row) {
    if (!empty($row['linkedid'])) {
        $linkedIds[] = $row['linkedid'];
    }
}
$linkedIds = array_unique($linkedIds);

// Получаем CEL данные
$celRows = $use_cel ? fetchCelRowsByLinkedIds($pdo, $linkedIds) : [];

// Получаем соответствия
$linkedIdMap = buildLinkedIdMapUnified($celRows);

$grouped = [];
foreach ($results as $row) {
    $uniqueid = $row['uniqueid'];
    $linkedid = $row['linkedid'] ?? $uniqueid; // хотя ни разу не был пустой

    // Нормализуем linkedid через цепочку трансферов
    $normalized = normalize_linkedId($linkedIdMap, $linkedid);

    if (!isset($grouped[$normalized])) {
        $grouped[$normalized] = [];
    }
    $grouped[$normalized][] = $row;
}
unset($row);

$groupedArray = [];
foreach ($grouped as $linkedid => $recs) {
    $groupedArray[] = [
        'linkedid' => $linkedid,
        'items' => $recs
    ];
}

// === Коррекция disposition и billsec на основе CEL ===
$markedAnsweredCount = 0;
if ($fix_disposition) {
    $markedAnsweredCount = fixDispositionFromCEL($groupedArray, $celRows);
}

// === Финальная фильтрация по uniqueid внутри каждой группы linkedid ===
if ($keep_one_answered_for_uniqueid) {
    keepOneAnswered($groupedArray);
}

header('Content-Type: application/json');
header('Expires: 0');
header('Cache-Control: must-revalidate');
echo json_encode([
    'status' => 'OK',
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'pages_count' => ceil($total / $perPage),
    'params' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'src' => $src,
        'dst' => $dst,
        'min_duration' => $minDuration,
        'fields' => $fields,
        'keep_one_answered_for_uniqueid' => $keep_one_answered_for_uniqueid
    ],
    'markedAnsweredCount' => $markedAnsweredCount,
    'linkedIdArray' => linkedArrayByMap($linkedIdMap),
    'content' => $groupedArray
], JSON_PRETTY_PRINT);

