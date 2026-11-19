<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/usuarios/7') {
    http_response_code(200);

    echo json_encode([
        'id' => 7,
        'nombre' => 'Ana',
    ]);

    exit;
}

http_response_code(404);

echo json_encode([
    'error' => 'Usuario no encontrado',
]);