<?php

declare(strict_types=1);

/**
 * Router for `php -S localhost:8080 router.php`, local development only.
 *
 * Serves /js/* from this monorepo's js/ directory so the example can iterate on the
 * widget without waiting for a jsDelivr tag. This script lives in examples/, not in
 * the connect-sdk-core Composer package, so it is free to assume the monorepo layout.
 * Production sites load the widget from jsDelivr instead, see docs/WIDGET.md.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($uri, '/js/')) {
    $relative = substr($uri, strlen('/js/'));
    $jsRoot = realpath(__DIR__ . '/../../js');
    $file = $jsRoot !== false ? realpath($jsRoot . '/' . $relative) : false;

    if ($file === false || $jsRoot === false || ! str_starts_with($file, $jsRoot . DIRECTORY_SEPARATOR) || ! is_file($file)) {
        http_response_code(404);

        return true;
    }

    $mime = match (pathinfo($file, PATHINFO_EXTENSION)) {
        'js' => 'application/javascript',
        'css' => 'text/css',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($file);

    return true;
}

return false;
