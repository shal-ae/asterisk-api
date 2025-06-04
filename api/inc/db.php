<?php

function dbConnect(array $config, $cdr = true): pdo
{
    $host = $config['db_host'] ?? 'localhost';
    if ($cdr) {
        $db = $config['db_name_cdr'] ?? 'asteriskcdrdb';
    } else {
        $db = $config['db_name_conf'] ?? 'asterisk';
    }
    $user = $config['db_user'] ?? 'freepbxuser';
    $pass = $config['db_pass'];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection failed']);
        exit;
    }
    return $pdo;
}

