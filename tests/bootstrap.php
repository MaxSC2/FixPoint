<?php

declare(strict_types=1);

// Output buffering keeps headers_sent() false so the hardened session can be
// configured even though the runner prints progress before integration tests.
ob_start();
register_shutdown_function(static function (): void {
    if (ob_get_level() > 0) {
        @ob_end_flush();
    }
});

putenv('APP_ENV=testing');

$app = require dirname(__DIR__) . '/app/bootstrap.php';

require __DIR__ . '/TestCase.php';

// Start the session before any output; integration tests reuse this session.
$app->session()->start();

return $app;
