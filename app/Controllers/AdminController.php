<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Repositories\RequestRepository;
use FixPoint\Repositories\UserRepository;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;

/** Admin dashboard backed by real aggregate queries and the UI view contract. */
final class AdminController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RequestRepository $requests,
        private readonly UserRepository $users
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $counts = $this->requests->statusCountsAll();
        $total = $this->requests->countAll();

        $inProgress = 0;
        foreach (['accepted', 'diagnostics', 'awaiting_parts', 'repairing', 'ready_for_pickup'] as $status) {
            $inProgress += $counts[$status] ?? 0;
        }

        $stats = [
            'total_requests' => $total,
            'requests_new' => $counts['new'] ?? 0,
            'requests_in_progress' => $inProgress,
            'requests_waiting_approval' => $counts['awaiting_customer_approval'] ?? 0,
            'requests_completed' => $counts['completed'] ?? 0,
            'active_customers' => $this->users->countByRole('customer'),
            'active_technicians' => $this->users->countByRole('technician'),
        ];

        $recentRequests = array_map(
            static function (array $request): array {
                $request['service'] = ['name' => $request['service_name'] ?? null];

                return $request;
            },
            $this->requests->latest(8)
        );

        return $this->renderPage('pages/admin/dashboard', [
            'pageTitle' => 'Администрирование — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'stats' => $stats,
            'recentRequests' => $recentRequests,
        ]);
    }
}
