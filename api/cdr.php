<?php

$config = include __DIR__ . '/.api.env.php';

//$validApiKey = $config['api_key'];
$host = $config['db_host'] ?? 'localhost';
$db = $config['db_name_cdr'] ?? 'asteriskcdrdb';
$user = $config['db_user'] ?? 'freepbxuser';
$pass = $config['db_pass'];
$baseDir = $config['recordings_path'];
$order_direction = 'ASC';

require_once __DIR__ . '/auth.php';

check_auth($config['api_key']);
// === АВТОРИЗАЦИЯ ===

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
$keep_one_answered_for_uniqueid = isset($_GET['keep_one_answered_for_uniqueid']) && ($_GET['keep_one_answered_for_uniqueid'] === '1');

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

function buildLinkedIdMapUnified(array $rows): array
{
    $map = [];

    // 1. Через ATTENDEDTRANSFER / BLINDTRANSFER / PARK_END
    foreach ($rows as $row) {
        if (in_array($row['eventtype'], ['ATTENDEDTRANSFER', 'BLINDTRANSFER', 'PARK_END'])) {
            $child = $row['peer'] ?? '';
            $parent = $row['linkedid'] ?? '';
            if ($child && $parent && $child !== $parent) {
                $map[$child] = $parent;
            }
        }
    }

    // 2. Через связи peer → channame (fallback)
    $chanLinkedMap = [];
    $peerToChan = [];

    foreach ($rows as $row) {
        $chan = $row['channame'] ?? '';
        $peer = $row['peer'] ?? '';
        $linkedid = $row['linkedid'] ?? '';

        if ($chan && $linkedid) {
            $chanLinkedMap[$chan] = $linkedid;
        }

        if ($peer && $chan) {
            $peerToChan[$peer][] = $chan;
        }
    }

    foreach ($peerToChan as $peer => $chans) {
        if (!isset($chanLinkedMap[$peer])) continue;
        $parent = $chanLinkedMap[$peer];

        foreach ($chans as $chan) {
            if (!isset($chanLinkedMap[$chan])) continue;
            $child = $chanLinkedMap[$chan];
            if ($child !== $parent && !isset($map[$child])) {
                $map[$child] = $parent;
            }
        }
    }

    return $map;
}

function fetchCelRowsByLinkedIds(PDO $pdo, array $linkedids): array
{
    if (empty($linkedids)) {
        return [];
    }

    // Подготовим плейсхолдеры
    $placeholders = [];
    $params = [];
    foreach ($linkedids as $i => $id) {
        $ph = ":id$i";
        $placeholders[] = $ph;
        $params[$ph] = $id;
    }

    $sql = "
        SELECT
            id, eventtime, eventtype, linkedid, uniqueid, channame, peer, cid_num, cid_name, context, exten, appname        FROM cel
        WHERE linkedid IN (" . implode(', ', $placeholders) . ")
        ORDER BY eventtime DESC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $ph => $val) {
        $stmt->bindValue($ph, $val);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Возвращает "нормализованный" linkedid (корень цепочки)
 *
 * @param array $linkedIdMap
 * @param string $linkedid
 * @return string
 */
function normalize_linkedId(array $linkedIdMap, string $linkedid): string
{
    $visited = [];
    while (isset($linkedIdMap[$linkedid])) {
        if (in_array($linkedid, $visited)) {
            // Предотвращаем бесконечный цикл
            break;
        }
        $visited[] = $linkedid;
        $linkedid = $linkedIdMap[$linkedid];
    }

    return $linkedid;
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

// Строим массив отвеченных uniqueid
$answeredIds = [];
foreach ($results as $row) {
    $uniqueid = $row['uniqueid'];
    if ($row['disposition'] === 'ANSWERED') {
        $answeredIds[$uniqueid] = true;
    }
}

// Сбор уникальных linkedid из результатов
$linkedIds = [];
foreach ($results as $row) {
    if (!empty($row['linkedid'])) {
        $linkedIds[] = $row['linkedid'];
    }
}
$linkedIds = array_unique($linkedIds);

// Получаем CEL данные
$celRows = fetchCelRowsByLinkedIds($pdo, $linkedIds);

// Получаем соответствия
$linkedIdMap = buildLinkedIdMapUnified($celRows);

$grouped = [];
foreach ($results as $row) {
    $uniqueid = $row['uniqueid'];

    // Если выбран режим - "только один отвеченный на uniqueid"
    if (($row['disposition'] !== 'ANSWERED') && isset($answeredIds[$uniqueid]) && $keep_one_answered_for_uniqueid) {
        continue;
    }

    $linkedid = $row['linkedid'] ?? $uniqueid; // хотя ни разу не был пустой

    // Нормализуем linkedid через цепочку трансферов
    $normalized = normalize_linkedId($linkedIdMap, $linkedid);

    if (!isset($grouped[$normalized])) {
        $grouped[$normalized] = [];
    }
    $grouped[$normalized][] = $row;
}

$groupedArray = [];
foreach ($grouped as $linkedid => $records) {
    $groupedArray[] = [
        'linkedid' => $linkedid,
        'items' => $records
    ];
}


// === Коррекция disposition и billsec на основе CEL ===
foreach ($groupedArray as &$group) {
    $linkedid = $group['linkedid'] ?? null;
    if (!$linkedid) continue;

    $celSubset = array_filter($celRows, function ($r) use ($linkedid) {
        return $r['linkedid'] === $linkedid;
    });

    $bridgeEnter = null;
    $bridgeExit = null;
    foreach ($celSubset as $event) {
        if ($event['eventtype'] === 'BRIDGE_ENTER') {
            $bridgeEnter = strtotime($event['eventtime']);
        } elseif ($event['eventtype'] === 'BRIDGE_EXIT') {
            $bridgeExit = strtotime($event['eventtime']);
        }
    }

    $realDuration = ($bridgeEnter && $bridgeExit && $bridgeExit > $bridgeEnter)
        ? $bridgeExit - $bridgeEnter
        : null;

    foreach ($group['items'] as &$item) {
        $item['real_answered'] = false;
        if ($realDuration !== null) {
            $item['disposition'] = 'ANSWERED';
            $item['billsec'] = $realDuration;
            $item['real_answered'] = true;
        }
    }
}

// === Финальная фильтрация по uniqueid внутри каждой группы linkedid ===
if ($keep_one_answered_for_uniqueid) {
    foreach ($groupedArray as &$group) {
        $byUniqueid = [];
        foreach ($group['items'] as $item) {
            $uid = $item['uniqueid'];
            if (!isset($byUniqueid[$uid])) {
                $byUniqueid[$uid] = [];
            }
            $byUniqueid[$uid][] = $item;
        }

        $filteredItems = [];
        foreach ($byUniqueid as $uid => $records) {
            $hasAnswered = false;
            foreach ($records as $rec) {
                if (($rec['disposition'] ?? '') === 'ANSWERED') {
                    $hasAnswered = true;
                    break;
                }
            }

            if ($hasAnswered) {
                foreach ($records as $rec) {
                    if (($rec['disposition'] ?? '') === 'ANSWERED') {
                        $filteredItems[] = $rec;
                        break;
                    }
                }
            } else {
                foreach ($records as $rec) {
                    $filteredItems[] = $rec;
                }
            }
        }

        $group['items'] = $filteredItems;
    }
    unset($group); // good practice with references
}

header('Content-Type: application/json');
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
        'answered' => $answered,
        'fields' => $fields,
        'keep_one_answered_for_uniqueid' => $keep_one_answered_for_uniqueid
    ],
    'linkedIdMap' => $linkedIdMap,
    'content' => $groupedArray
], JSON_PRETTY_PRINT);

