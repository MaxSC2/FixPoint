#!/usr/bin/env php
<?php

declare(strict_types=1);

use FixPoint\Database\Seeder;
use FixPoint\Security\PasswordHasher;

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$options = getopt('', ['force']);

try {
    $seeder = new Seeder($app->database()->connection(), new PasswordHasher(), $app->config());
    $summary = $seeder->run(isset($options['force']));

    echo "Seed completed:\n";
    foreach ($summary as $table => $count) {
        echo "  {$table}: {$count}\n";
    }
    echo "Local accounts use password from SEED_PASSWORD (default: FixPoint!Dev1).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Seed error: ' . $exception->getMessage() . "\n");
    exit(1);
}
