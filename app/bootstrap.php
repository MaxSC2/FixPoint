<?php

declare(strict_types=1);

use FixPoint\Core\App;
use FixPoint\Core\Config;
use FixPoint\Core\Env;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/app/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'FixPoint\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

Env::load(BASE_PATH . '/.env');

$config = Config::fromEnvironment(BASE_PATH);
date_default_timezone_set((string) $config->get('app.timezone'));

return new App($config);
