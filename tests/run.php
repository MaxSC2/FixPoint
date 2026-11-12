<?php

declare(strict_types=1);

use FixPoint\Database\Migrator;
use FixPoint\Database\Seeder;
use FixPoint\Security\PasswordHasher;
use FixPoint\Tests\TestCase;

$app = require __DIR__ . '/bootstrap.php';
require __DIR__ . '/IntegrationTestCase.php';

$arguments = array_slice($argv, 1);
$unitOnly = in_array('--unit', $arguments, true);
$filter = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--filter=')) {
        $filter = substr($argument, 9);
    }
}

if (!$unitOnly) {
    $databaseName = (string) $app->config()->get('database.name');
    if (!str_ends_with($databaseName, '_test')) {
        fwrite(STDERR, "Refusing to run destructive database tests.\n");
        fwrite(STDERR, "Set DB_DATABASE to a database whose name ends with _test (current: {$databaseName}).\n");
        fwrite(STDERR, "PowerShell: \$env:DB_DATABASE='fixpoint_test'; php tests/run.php\n");
        fwrite(STDERR, "Bash: DB_DATABASE=fixpoint_test php tests/run.php\n");
        exit(2);
    }

    try {
        $migrator = new Migrator($app->config(), $app->logger());
        $applied = $migrator->migrate(true, true);

        $seeder = new Seeder($app->database()->connection(), new PasswordHasher(), $app->config());
        $seeded = $seeder->run();

        echo 'Test database ready: ' . $databaseName . "\n";
        echo '  migrations applied: ' . count($applied) . "\n";
        echo '  seed users: ' . $seeded['users'] . ', services: ' . $seeded['services']
            . ', requests: ' . $seeded['requests'] . "\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Test database setup failed: ' . $exception->getMessage() . "\n");
        exit(2);
    }
}

$directories = [__DIR__ . '/Backend'];
if (!$unitOnly) {
    $directories[] = __DIR__ . '/Integration';
}

$classes = [];
foreach ($directories as $directory) {
    foreach (glob($directory . '/*Test.php') ?: [] as $file) {
        require_once $file;
        $normalised = str_replace('\\', '/', $file);
        $namespace = str_contains($normalised, '/Backend/')
            ? 'FixPoint\\Tests\\Backend\\'
            : 'FixPoint\\Tests\\Integration\\';
        $class = $namespace . basename($file, '.php');
        if (class_exists($class) && is_subclass_of($class, TestCase::class)) {
            $classes[] = $class;
        }
    }
}
sort($classes);

$passed = 0;
$failed = 0;
$assertions = 0;

foreach ($classes as $class) {
    if ($filter !== null && !str_contains($class, $filter)) {
        continue;
    }

    /** @var TestCase $test */
    $test = new $class();
    $result = $test->run();
    $passed += $result['passed'];
    $failed += $result['failed'];
    $assertions += $result['assertions'];
}

printf(
    "\nTests: %d passed, %d failed, %d assertions.\n",
    $passed,
    $failed,
    $assertions
);

exit($failed === 0 ? 0 : 1);
