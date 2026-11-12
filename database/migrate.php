#!/usr/bin/env php
<?php

declare(strict_types=1);

use FixPoint\Database\Migrator;

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$options = getopt('', ['fresh', 'status', 'force']);

try {
    $migrator = new Migrator($app->config(), $app->logger());

    if (isset($options['status'])) {
        foreach ($migrator->status() as $row) {
            printf(
                "[%s] %s%s\n",
                $row['pending'] ? 'pending' : 'applied',
                $row['migration'],
                $row['applied_at'] !== null ? ' (' . $row['applied_at'] . ')' : ''
            );
        }
        exit(0);
    }

    $applied = $migrator->migrate(
        isset($options['fresh']),
        isset($options['force'])
    );

    if ($applied === []) {
        echo "Nothing to migrate.\n";
    } else {
        foreach ($applied as $name) {
            echo "Applied: {$name}\n";
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration error: ' . $exception->getMessage() . "\n");
    exit(1);
}
