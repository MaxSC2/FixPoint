<?php

declare(strict_types=1);

use FixPoint\Controllers\HealthController;
use FixPoint\Controllers\HomeController;
use FixPoint\Controllers\PageController;
use FixPoint\Controllers\ServicesController;
use FixPoint\Core\App;
use FixPoint\Core\Router;

/**
 * Explicit route table. Dynamic route parameters are passive values only;
 * no route name or URL fragment is ever used in include/require/eval.
 */
return static function (Router $router, App $app): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/services', [ServicesController::class, 'index']);
    $router->get('/services/{slug}', [ServicesController::class, 'show']);
    $router->get('/about', [PageController::class, 'about']);
    $router->get('/contacts', [PageController::class, 'contacts']);
    $router->get('/health', [HealthController::class, 'index']);
};
