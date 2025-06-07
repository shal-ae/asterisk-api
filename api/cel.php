<?php

$config = include __DIR__ . '/.api.env.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/logic/linked-id-map.php';

check_auth($config['api_key']);
$pdo = dbConnect($config);

header('Content-Type: application/json');

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

    $linkedIdMap = buildLinkedIdMapUnified($rows);

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
