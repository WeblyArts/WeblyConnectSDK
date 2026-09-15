<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

/** @var \WeblyConnect\Sdk\Site\SiteConnect $connect */
$connect = require __DIR__ . '/config/site-connect.php';

header('Content-Type: application/json');

$message = isset($_GET['message']) ? (string) $_GET['message'] : 'Say hello in one short sentence.';

try {
    $reply = $connect->chat($message);
    echo json_encode($reply, JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()]);
}
