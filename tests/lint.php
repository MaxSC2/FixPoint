<?php

declare(strict_types=1);

/**
 * Zero-dependency PHP syntax linter.
 *
 * Scans every first-party PHP file and runs `php -l` on it. Vendor,
 * node_modules, storage and VCS directories are skipped.
 */

$root = dirname(__DIR__);
$directories = ['app', 'config', 'database', 'public', 'tests'];

/** @return list<string> */
function phpFiles(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $normalised = str_replace('\\', '/', $path);
        if (preg_match('#/(vendor|node_modules|storage|\.git)/#', $normalised) === 1) {
            continue;
        }
        $files[] = $path;
    }

    sort($files, SORT_STRING);

    return $files;
}

$files = [];
foreach ($directories as $directory) {
    array_push($files, ...phpFiles($root . DIRECTORY_SEPARATOR . $directory));
}

if ($files === []) {
    fwrite(STDERR, "No PHP files found.\n");
    exit(1);
}

$failures = [];
foreach ($files as $file) {
    $command = [PHP_BINARY, '-d', 'display_errors=1', '-l', $file];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $root, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        $failures[] = $file . ': could not start php -l';
        continue;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    if ($exit !== 0) {
        $failures[] = $file . "\n" . trim((string) $stdout . "\n" . (string) $stderr);
        continue;
    }

    echo 'OK ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file) . "\n";
}

$count = count($files);
$failed = count($failures);

if ($failed > 0) {
    echo "\n";
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL {$failure}\n");
    }
    fwrite(STDERR, "\nLint: {$failed} of {$count} files failed.\n");
    exit(1);
}

echo "\nLint: {$count} files OK.\n";
exit(0);
