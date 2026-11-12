<?php

declare(strict_types=1);

// Built-in PHP server: let it serve real static files (frontend-owned assets).
if (PHP_SAPI === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $candidate = realpath(__DIR__ . (is_string($uriPath) ? $uriPath : '/'));
    $publicRoot = realpath(__DIR__);
    if ($candidate !== false && $publicRoot !== false
        && str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($candidate)
    ) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->runHttp();
