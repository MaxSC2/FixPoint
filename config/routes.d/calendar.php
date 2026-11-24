<?php

declare(strict_types=1);

use FixPoint\Controllers\CustomerAppointmentController;
use FixPoint\Controllers\TechnicianAppointmentController;
use FixPoint\Controllers\TechnicianCalendarController;
use FixPoint\Core\App;
use FixPoint\Core\Router;
use FixPoint\Middleware\AuthMiddleware;
use FixPoint\Middleware\RoleMiddleware;

/**
 * CAL-01 route module. It is loaded automatically by App::router() from
 * config/routes.d/ so the shared config/routes.php stays untouched. If the
 * owner prefers explicit registration, require this file from routes.php
 * instead (never both).
 */
return static function (Router $router, App $app): void {
    $customerOnly = [
        AuthMiddleware::class,
        new RoleMiddleware($app->auth(), $app->session(), 'customer'),
    ];
    $technicianOnly = [
        AuthMiddleware::class,
        new RoleMiddleware($app->auth(), $app->session(), 'technician'),
    ];

    // Customer booking and personal appointment history.
    $router->get('/appointments', [CustomerAppointmentController::class, 'index'], $customerOnly);
    $router->get('/appointments/{id:\d+}', [CustomerAppointmentController::class, 'show'], $customerOnly);
    $router->post('/appointments/{id:\d+}/cancel', [CustomerAppointmentController::class, 'cancel'], $customerOnly);
    $router->post('/appointments/{id:\d+}/reschedule', [CustomerAppointmentController::class, 'reschedule'], $customerOnly);
    $router->post('/appointments/{id:\d+}/proposal', [CustomerAppointmentController::class, 'proposal'], $customerOnly);
    $router->get('/requests/{id:\d+}/appointments/book', [CustomerAppointmentController::class, 'create'], $customerOnly);
    $router->post('/requests/{id:\d+}/appointments/book', [CustomerAppointmentController::class, 'store'], $customerOnly);

    // Technician availability, private blocks and appointment lifecycle.
    $router->get('/technician/calendar', [TechnicianCalendarController::class, 'index'], $technicianOnly);
    $router->get('/technician/calendar/settings', [TechnicianCalendarController::class, 'settings'], $technicianOnly);
    $router->post('/technician/calendar/settings', [TechnicianCalendarController::class, 'updateSettings'], $technicianOnly);
    $router->get('/technician/calendar/blocks', [TechnicianCalendarController::class, 'blocks'], $technicianOnly);
    $router->post('/technician/calendar/blocks', [TechnicianCalendarController::class, 'storeBlock'], $technicianOnly);
    $router->post('/technician/calendar/blocks/{id:\d+}/delete', [TechnicianCalendarController::class, 'deleteBlock'], $technicianOnly);

    $router->get('/technician/appointments/{id:\d+}', [TechnicianAppointmentController::class, 'show'], $technicianOnly);
    $router->post('/technician/appointments/{id:\d+}/cancel', [TechnicianAppointmentController::class, 'cancel'], $technicianOnly);
    $router->post('/technician/appointments/{id:\d+}/confirm', [TechnicianAppointmentController::class, 'confirm'], $technicianOnly);
    $router->post('/technician/appointments/{id:\d+}/decline', [TechnicianAppointmentController::class, 'decline'], $technicianOnly);
    $router->post('/technician/appointments/{id:\d+}/reschedule', [TechnicianAppointmentController::class, 'reschedule'], $technicianOnly);
    $router->post('/technician/appointments/{id:\d+}/complete', [TechnicianAppointmentController::class, 'complete'], $technicianOnly);
    $router->post('/technician/appointments/{id:\d+}/no-show', [TechnicianAppointmentController::class, 'noShow'], $technicianOnly);
};
