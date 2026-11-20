<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ValidationException;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\TechnicianProfileService;

final class AdminTechnicianProfileController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly TechnicianProfileService $profiles
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->requireAdmin();

        return $this->renderPage('pages/admin/technicians/index', [
            'pageTitle' => 'Профили мастеров — администрирование',
            'activeNav' => 'admin',
            'technicians' => $this->profiles->adminList($user),
        ]);
    }

    public function updateVisibility(Request $request, array $params = []): Response
    {
        $user = $this->requireAdmin();
        $visibility = $request->post('is_visible', '');
        $reason = $request->post('reason', '');

        try {
            $this->profiles->moderateVisibility(
                $user,
                (int) ($params['id'] ?? 0),
                [
                    'is_visible' => is_string($visibility) ? $visibility : '',
                    'reason' => is_string($reason) ? $reason : '',
                ]
            );
        } catch (ValidationException $exception) {
            $this->session->flashErrors($exception->errors());
            $this->session->flash('error', 'Не удалось изменить видимость профиля.');
            return Response::redirect('/admin/technicians');
        }

        $this->session->flash('success', 'Видимость профиля мастера обновлена.');
        return Response::redirect('/admin/technicians');
    }

    /** @return array<string, mixed> */
    private function requireAdmin(): array
    {
        $user = $this->auth->user();
        if ($user === null || (string) ($user['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }

        return $user;
    }
}
