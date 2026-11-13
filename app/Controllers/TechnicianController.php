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

/**
 * Technician work queue. The query is scoped by technician_id; a technician
 * can never see another technician's queue through this controller.
 */
final class TechnicianController extends Controller
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

    public function queue(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        $counts = $this->requests->statusCountsForTechnician($user['id']);
        $total = $this->requests->countForTechnician($user['id']);
        $closed = ($counts['completed'] ?? 0) + ($counts['cancelled'] ?? 0) + ($counts['rejected'] ?? 0);

        return $this->renderPage('pages/technician/queue', [
            'pageTitle' => 'Мои заявки — ' . $this->config->get('app.name'),
            'activeNav' => 'technician',
            'requests' => $this->requests->listForTechnician($user['id'], 50),
            'statusCounts' => $counts,
            'summary' => [
                'total' => $total,
                'active' => max(0, $total - $closed),
                'awaiting_approval' => $counts['awaiting_customer_approval'] ?? 0,
                'completed' => $counts['completed'] ?? 0,
            ],
        ]);
    }
}
