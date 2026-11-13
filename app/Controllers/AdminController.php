<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Repositories\RequestRepository;
use FixPoint\Repositories\ServiceCategoryRepository;
use FixPoint\Repositories\ServiceRepository;
use FixPoint\Repositories\UserRepository;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;

/** Admin dashboard backed by real aggregate queries. */
final class AdminController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RequestRepository $requests,
        private readonly UserRepository $users,
        private readonly ServiceRepository $services,
        private readonly ServiceCategoryRepository $categories
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $counts = $this->requests->statusCountsAll();
        $total = $this->requests->countAll();
        $closed = ($counts['completed'] ?? 0) + ($counts['cancelled'] ?? 0) + ($counts['rejected'] ?? 0);

        return $this->renderPage('pages/admin/dashboard', [
            'pageTitle' => 'Администрирование — ' . $this->config->get('app.name'),
            'activeNav' => 'admin',
            'requests' => $this->requests->latest(8),
            'statusCounts' => $counts,
            'summary' => [
                'requests_total' => $total,
                'requests_active' => max(0, $total - $closed),
                'requests_unassigned' => $this->requests->countUnassigned(),
                'requests_completed' => $counts['completed'] ?? 0,
                'users_total' => $this->users->countAll(),
                'customers' => $this->users->countByRole('customer'),
                'technicians' => $this->users->countByRole('technician'),
                'services_active' => $this->services->countAllActive(),
                'categories_active' => $this->categories->countActive(),
            ],
        ]);
    }
}
