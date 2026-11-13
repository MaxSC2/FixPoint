<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Repositories\RequestRepository;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;

/** Customer cabinet landing page: only the authenticated customer's data. */
final class AccountController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RequestRepository $requests
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        $counts = $this->requests->statusCountsForCustomer($user['id']);
        $total = $this->requests->countForCustomer($user['id']);
        $completed = $counts['completed'] ?? 0;
        $closed = $completed + ($counts['cancelled'] ?? 0) + ($counts['rejected'] ?? 0);

        return $this->renderPage('pages/account/dashboard', [
            'pageTitle' => 'Личный кабинет — ' . $this->config->get('app.name'),
            'activeNav' => 'requests',
            'requests' => $this->requests->listForCustomer($user['id'], 6),
            'statusCounts' => $counts,
            'summary' => [
                'total' => $total,
                'active' => max(0, $total - $closed),
                'awaiting_approval' => $counts['awaiting_customer_approval'] ?? 0,
                'completed' => $completed,
            ],
        ]);
    }
}
