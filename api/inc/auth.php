<?php
function check_auth($validApiKey)
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (!preg_match('/Bearer\\s+(.+)/', $authHeader, $matches) || $matches[1] !== $validApiKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
