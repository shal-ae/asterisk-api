<?php
// Подключение к базе данных FreePBX (настрой — если отличается)
$host = 'localhost';
$dbname = 'asteriskcdrdb';
$user = 'freepbxuser';
$pass = '492771210fa3a3dc478adeb9403615e9';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed', 'message' => $e->getMessage()]);
    exit;
}

// Опциональные параметры ?limit=100&start=2025-06-04 00:00:00&end=2025-06-04 23:59:59
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
$start = $_GET['start'] ?? '2000-01-01 00:00:00';
$end = $_GET['end'] ?? '2100-01-01 00:00:00';

$where = "eventtime BETWEEN :start AND :end";
$params = [
    ':start' => $start,
    ':end' => $end
];

$linkedids = isset($_GET['linkedid']) ? (array)$_GET['linkedid'] : [];
$linkedids = array_filter($linkedids, function ($id) {
    return trim($id) !== '';
});
// Фильтр по linkedid[]
if (!empty($linkedids)) {
    $placeholders = [];
    foreach ($linkedids as $index => $id) {
        $ph = ":linkedid$index";
        $placeholders[] = $ph;
        $params[$ph] = $id;
    }
    $where .= " AND linkedid IN (" . implode(',', $placeholders) . ")";
}


$sql = "
    SELECT
        id, eventtime, eventtype, linkedid, uniqueid, channame, peer, cid_num, cid_name, context
    FROM cel
    WHERE $where
    ORDER BY eventtime DESC
    LIMIT :limit
";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    function buildLinkedIdMapFromPeerRelations(array $rows): array
    {
        $chanLinkedMap = [];     // channame => linkedid
        $peerToChan = [];        // peer => channame
        $linkedIdMap = [];

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

        // Найдём связи по паре peer => channame
        foreach ($peerToChan as $peer => $chans) {
            if (!isset($chanLinkedMap[$peer])) {
                continue; // peer канал не стартовал — игнорируем
            }
            $parentLinkedid = $chanLinkedMap[$peer];
            foreach ($chans as $childChan) {
                if (isset($chanLinkedMap[$childChan])) {
                    $childLinkedid = $chanLinkedMap[$childChan];
                    if ($childLinkedid !== $parentLinkedid) {
                        $linkedIdMap[$childLinkedid] = $parentLinkedid;
                    }
                }
            }
        }

        return $linkedIdMap;
    }

    $linkedIdMap = buildLinkedIdMapFromPeerRelations($rows);

    echo json_encode([
        'count' => count($rows),
        'linkedids' => $linkedids,
        'linkedIdMap' => $linkedIdMap,
        'data' => $rows
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'message' => $e->getMessage()]);
}
