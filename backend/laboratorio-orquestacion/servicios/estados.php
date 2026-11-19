<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/estados/101') {
    http_response_code(200);

    echo json_encode([
        'solicitud_id' => 101,
        'estado' => 'pendiente',
    ]);

    exit;
}

http_response_code(404);

echo json_encode([
    'error' => 'Estado no encontrado',
]);