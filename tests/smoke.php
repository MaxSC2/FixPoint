<?php

declare(strict_types=1);

/**
 * HTTP smoke test against the PHP built-in server.
 *
 * Requires a migrated and seeded application database (see README).
 * Usage: php tests/smoke.php   (SMOKE_PORT=8091 by default)
 */

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$port = (int) (getenv('SMOKE_PORT') ?: 8091);
$logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixpoint_smoke_' . getmypid() . '.log';
$baseUrl = 'http://127.0.0.1:' . $port;

$command = [
    PHP_BINARY,
    '-d', 'display_errors=0',
    '-d', 'log_errors=1',
    '-S', '127.0.0.1:' . $port,
    '-t', $root . '/public',
    $root . '/public/index.php',
];

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $logFile, 'a'],
    2 => ['file', $logFile, 'a'],
];

$process = proc_open($command, $descriptors, $pipes, $root, null, ['bypass_shell' => true]);
if (!is_resource($process)) {
    fwrite(STDERR, "Cannot start the PHP built-in server.\n");
    exit(1);
}

if (isset($pipes[0]) && is_resource($pipes[0])) {
    fclose($pipes[0]);
}

/**
 * @param list<string> $headers
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function httpRequest(string $method, string $url, array $headers = []): array
{
    $headerLines = array_merge(['Connection: close'], $headers);
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $rawHeaders = $http_response_header ?? [];
    $status = 0;
    $parsed = [];

    foreach ($rawHeaders as $index => $line) {
        if ($index === 0 && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
            $status = (int) $match[1];
            continue;
        }
        $position = strpos($line, ':');
        if ($position === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $position)));
        $parsed[$name] = trim(substr($line, $position + 1));
    }

    return [
        'status' => $status,
        'headers' => $parsed,
        'body' => $body === false ? '' : $body,
    ];
}

$checks = 0;
$failures = [];

/**
 * @param array{status: int, headers: array<string, string>, body: string} $response
 */
function check(string $label, bool $condition, array $response, string $detail = ''): void
{
    global $checks, $failures;
    $checks++;

    if ($condition) {
        echo "PASS {$label}\n";

        return;
    }

    $failures[] = $label . ($detail === '' ? '' : ' (' . $detail . ')') . ' — HTTP ' . $response['status'];
    echo "FAIL {$label}\n";
}

try {
    $ready = false;
    for ($attempt = 1; $attempt <= 80; $attempt++) {
        $probe = httpRequest('GET', $baseUrl . '/health', ['Accept: application/json']);
        if ($probe['status'] === 200) {
            $ready = true;
            break;
        }
        usleep(250_000);
    }

    if (!$ready) {
        fwrite(STDERR, "Server did not become ready on {$baseUrl}.\n");
        $log = (string) @file_get_contents($logFile);
        if ($log !== '') {
            fwrite(STDERR, $log . "\n");
        }
        exit(1);
    }

    $home = httpRequest('GET', $baseUrl . '/');
    check('GET / returns 200', $home['status'] === 200, $home);
    check('GET / renders database-backed home', str_contains($home['body'], 'Сервисный центр FixPoint'), $home);
    check('X-Content-Type-Options: nosniff', ($home['headers']['x-content-type-options'] ?? '') === 'nosniff', $home);
    check('X-Frame-Options: DENY', ($home['headers']['x-frame-options'] ?? '') === 'DENY', $home);
    check('CSP forbids framing', str_contains($home['headers']['content-security-policy'] ?? '', "frame-ancestors 'none'"), $home);
    check('X-Powered-By is removed', !isset($home['headers']['x-powered-by']), $home);

    $services = httpRequest('GET', $baseUrl . '/services');
    check('GET /services returns 200', $services['status'] === 200, $services);
    check('Catalogue shows seeded services', str_contains($services['body'], 'Чистка ноутбука от пыли'), $services);

    $detail = httpRequest('GET', $baseUrl . '/services/laptop-cleaning');
    check('GET /services/{slug} returns 200', $detail['status'] === 200, $detail);
    check('Service detail shows category', str_contains($detail['body'], 'Компьютеры и ноутбуки'), $detail);

    $about = httpRequest('GET', $baseUrl . '/about');
    check('GET /about returns 200', $about['status'] === 200, $about);

    $contacts = httpRequest('GET', $baseUrl . '/contacts');
    check('GET /contacts returns 200', $contacts['status'] === 200, $contacts);
    check('Contacts do not invent a phone', !str_contains($contacts['body'], '+7 700 000 00 00'), $contacts);

    $health = httpRequest('GET', $baseUrl . '/health', ['Accept: application/json']);
    check('GET /health returns 200', $health['status'] === 200, $health);
    $healthData = json_decode($health['body'], true);
    check('Health reports database ok', is_array($healthData) && ($healthData['database']['status'] ?? null) === 'ok', $health);

    $missing = httpRequest('GET', $baseUrl . '/definitely-missing');
    check('Unknown URL returns 404', $missing['status'] === 404, $missing);
    check('404 page is neutral', str_contains($missing['body'], 'Страница не найдена'), $missing);
    check('404 page leaks no exception details', !str_contains($missing['body'], 'Route not found'), $missing);

    $wrongMethod = httpRequest('POST', $baseUrl . '/health');
    check('POST /health returns 405', $wrongMethod['status'] === 405, $wrongMethod);
    check('405 includes Allow header', str_contains($wrongMethod['headers']['allow'] ?? '', 'GET'), $wrongMethod);

    $xss = httpRequest('GET', $baseUrl . '/services?q=' . rawurlencode('"><script>alert(1)</script>'));
    check('Search query is HTML-escaped', !str_contains($xss['body'], '<script>alert(1)</script>'), $xss);
    check('Escaped query is visible as text', str_contains($xss['body'], '&lt;script&gt;'), $xss);

    $head = httpRequest('HEAD', $baseUrl . '/');
    check('HEAD / returns 200', $head['status'] === 200, $head);
} finally {
    proc_terminate($process);
    $deadline = microtime(true) + 3.0;
    while (microtime(true) < $deadline) {
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        usleep(100_000);
    }
    if (proc_get_status($process)['running']) {
        proc_terminate($process, 9);
    }
    proc_close($process);
    @unlink($logFile);
}

if ($failures !== []) {
    echo "\n";
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAILED: {$failure}\n");
    }
    fwrite(STDERR, "\nSmoke: " . count($failures) . " of {$checks} checks failed.\n");
    exit(1);
}

echo "\nSmoke: {$checks} checks passed.\n";
exit(0);
