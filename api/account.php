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
$db = $config['db_name_conf'] ?? 'asterisk';
$user = $config['db_user'] ?? 'freepbxuser';
$pass = $config['db_pass'] ?? '';


// === АВТОРИЗАЦИЯ ===
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches) || $matches[1] !== $validApiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// === ПОЛУЧЕНИЕ ДАННЫХ ===
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Попробуем получить из таблицы users
//    $stmt = $pdo->query("SELECT id AS extension, (SELECT value FROM pjsip_auth WHERE id = e.id AND `key` = 'username' LIMIT 1) AS name FROM pjsip_endpoints e ORDER BY id");

    $stmt = $pdo->query("SELECT extension, name FROM users ORDER BY extension");
    $extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'OK',
        'count' => count($extensions),
        'extensions' => $extensions
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}
