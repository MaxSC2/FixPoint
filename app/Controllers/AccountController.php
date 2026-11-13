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
use FixPoint\Services\RequestService;

/** Customer cabinet landing page: only the authenticated customer's data. */
final class AccountController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RequestService $requestService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        return $this->renderPage('pages/customer/dashboard', [
            'pageTitle' => 'Личный кабинет — ' . $this->config->get('app.name'),
            'activeNav' => 'requests',
            'recentRequests' => $this->requestService->recentForCustomer((int) $user['id'], 4),
        ]);
    }
}
