<?php
// Настройки подключения к базе данных
$host = 'localhost';
$db = 'asteriskcdrdb';
$user = 'freepbxuser';
$pass = ''; // замените на ваш пароль

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// Чтение GET-параметров
$src = $_GET['src'] ?? '';
$dst = $_GET['dst'] ?? '';
$limit = $_GET['limit'] ?? 10;

// Подготовка SQL с параметрами
$sql = "SELECT uniqueid, calldate, clid, src, dst, duration, disposition, billsec, userfield FROM cdr WHERE 1=1";

$params = [];
if (!empty($src)) {
    $sql .= " AND src = :src";
    $params[':src'] = $src;
}
if (!empty($dst)) {
    $sql .= " AND dst = :dst";
    $params[':dst'] = $dst;
}

$sql .= " ORDER BY calldate DESC LIMIT :limit";
$stmt = $pdo->prepare($sql);
$params[':limit'] = (int)$limit;

// Привязка параметров вручную (LIMIT нельзя через bindValue с ассоц. массивом)
foreach ($params as $key => $value) {
    if ($key === ':limit') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Вывод в формате JSON
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);

