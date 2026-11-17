<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\AdminStatisticsService;

/**
 * Administrator analytics page. The route is admin-only; the service re-checks
 * the role and validates the optional period before running aggregates.
 */
final class AdminStatisticsController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly AdminStatisticsService $statistics
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        $report = $this->statistics->report(
            $user,
            $this->queryString($request, 'from'),
            $this->queryString($request, 'to')
        );

        return $this->renderPage('pages/admin/statistics', array_merge($report, [
            'pageTitle' => 'Статистика — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
        ]));
    }

    private function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');

        return is_string($value) ? trim($value) : '';
    }
}
