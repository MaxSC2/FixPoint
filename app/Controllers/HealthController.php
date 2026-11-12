<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Services\DatabaseHealthService;

final class HealthController extends Controller
{
    public function __construct(
        \FixPoint\Core\Config $config,
        \FixPoint\Core\ViewRenderer $view,
        \FixPoint\Security\Auth $auth,
        \FixPoint\Security\Csrf $csrf,
        \FixPoint\Core\Session $session,
        private readonly DatabaseHealthService $health
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $databaseOk = $this->health->check();

        return Response::json([
            'status' => $databaseOk ? 'ok' : 'error',
            'database' => ['status' => $databaseOk ? 'ok' : 'error'],
            'time' => gmdate('c'),
        ], $databaseOk ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
