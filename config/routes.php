<?php

declare(strict_types=1);

use FixPoint\Controllers\AccountController;
use FixPoint\Controllers\AdminController;
use FixPoint\Controllers\AuthController;
use FixPoint\Controllers\HealthController;
use FixPoint\Controllers\HomeController;
use FixPoint\Controllers\PageController;
use FixPoint\Controllers\RequestController;
use FixPoint\Controllers\ServicesController;
use FixPoint\Controllers\TechnicianController;
use FixPoint\Core\App;
use FixPoint\Core\Router;
use FixPoint\Middleware\AuthMiddleware;
use FixPoint\Middleware\GuestMiddleware;
use FixPoint\Middleware\RoleMiddleware;

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

    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class]);
    $router->get('/register', [AuthController::class, 'showRegister'], [GuestMiddleware::class]);
    $router->post('/register', [AuthController::class, 'register'], [GuestMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

    $router->get(
        '/account',
        [AccountController::class, 'index'],
        [AuthMiddleware::class, new RoleMiddleware($app->auth(), $app->session(), 'customer')]
    );

    $customerOnly = [AuthMiddleware::class, new RoleMiddleware($app->auth(), $app->session(), 'customer')];
    $router->get('/requests', [RequestController::class, 'index'], $customerOnly);
    $router->get('/requests/create', [RequestController::class, 'create'], $customerOnly);
    $router->post('/requests/create', [RequestController::class, 'store'], $customerOnly);
    $router->get('/requests/{id:\d+}', [RequestController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/requests/{id:\d+}/comments', [RequestController::class, 'comment'], [AuthMiddleware::class]);
    $router->post(
        '/requests/{id:\d+}/estimate-decision',
        [RequestController::class, 'decideEstimate'],
        [AuthMiddleware::class]
    );

    $router->get(
        '/technician/requests',
        [TechnicianController::class, 'queue'],
        [AuthMiddleware::class, new RoleMiddleware($app->auth(), $app->session(), 'technician')]
    );
    $router->get(
        '/admin',
        [AdminController::class, 'index'],
        [AuthMiddleware::class, new RoleMiddleware($app->auth(), $app->session(), 'admin')]
    );
};
