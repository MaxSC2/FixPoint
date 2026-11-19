<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ValidationException;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;
use FixPoint\Services\AuthService;
use FixPoint\Services\RequestService;

/**
 * Customer cabinet landing page: only the authenticated customer's data, plus
 * the form that changes the customer's own password.
 */
final class AccountController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly RequestService $requestService,
        private readonly AuthService $authService
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

    /**
     * Change the password of the signed-in customer. The current password has
     * to be confirmed, otherwise a stolen session could take over the account.
     */
    public function changePassword(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            $this->abort(403, 'Authentication required');
        }

        $currentPassword = $this->stringPost($request, 'current_password');
        $password = $this->stringPost($request, 'password');
        $confirmation = $this->stringPost($request, 'password_confirmation');

        try {
            $this->authService->changePassword(
                (int) $user['id'],
                $currentPassword,
                $password,
                $confirmation
            );
        } catch (ValidationException $exception) {
            $this->flashValidation($exception);

            return Response::redirect('/account');
        }

        $this->session->flash('success', 'Пароль изменён. Используйте его при следующем входе.');

        return Response::redirect('/account');
    }

    private function stringPost(Request $request, string $key): string
    {
        $value = $request->post($key, '');

        return is_string($value) ? trim($value) : '';
    }

    private function flashValidation(ValidationException $exception): void
    {
        $errors = $exception->errors();
        if (isset($errors['general'])) {
            $this->session->flash('error', $errors['general']);
            unset($errors['general']);
        }
        if ($errors !== []) {
            $this->session->flashErrors($errors);
        }
    }
}
