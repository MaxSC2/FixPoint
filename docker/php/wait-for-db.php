<?php

declare(strict_types=1);

$app = require '/var/www/html/app/bootstrap.php';
$attempts = max(1, (int) (getenv('DB_WAIT_ATTEMPTS') ?: 30));

for ($attempt = 1; $attempt <= $attempts; $attempt++) {
    try {
        $app->database()->connection()->query('SELECT 1');

        return;
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Waiting for database (%d/%d)...\n", $attempt, $attempts));
        sleep(2);
    }
}

fwrite(STDERR, "Database is not reachable.\n");
exit(1);
