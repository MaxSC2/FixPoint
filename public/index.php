<?php

declare(strict_types=1);

// Never print PHP errors into the response. This has to happen before the
// bootstrap is loaded: a configuration mistake must not leak absolute paths
// or a stack trace to the browser.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

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
