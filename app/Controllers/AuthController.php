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

/**
 * Guest authentication screens. All state-changing actions are POST and are
 * wrapped by the global CSRF middleware; the controller only validates input,
 * calls AuthService and redirects.
 */
final class AuthController extends Controller
{
    public function __construct(
        Config $config,
        ViewRenderer $view,
        Auth $auth,
        Csrf $csrf,
        Session $session,
        private readonly AuthService $authService
    ) {
        parent::__construct($config, $view, $auth, $csrf, $session);
    }

    public function showLogin(Request $request, array $params = []): Response
    {
        return $this->renderPage('pages/auth/login', [
            'pageTitle' => 'Вход — ' . $this->config->get('app.name'),
            'activeNav' => '',
            'returnTo' => $this->safeReturnTo($request->query('return_to')),
        ]);
    }

    public function login(Request $request, array $params = []): Response
    {
        $email = $this->stringPost($request, 'email');
        $password = $this->stringPost($request, 'password');
        $returnTo = $this->safeReturnTo($request->query('return_to'));

        if (!$this->authService->attempt($email, $password)) {
            $this->session->flash('error', 'Неверный email или пароль.');
            $this->session->flashOld(['email' => $email]);

            return Response::redirect($this->loginUrl($returnTo));
        }

        $this->session->flash('success', 'Вы вошли в систему.');
        $target = $returnTo ?? \role_home($this->auth->role());

        return Response::redirect($target);
    }

    public function showRegister(Request $request, array $params = []): Response
    {
        return $this->renderPage('pages/auth/register', [
            'pageTitle' => 'Регистрация — ' . $this->config->get('app.name'),
            'activeNav' => '',
        ]);
    }

    public function register(Request $request, array $params = []): Response
    {
        $fullName = $this->stringPost($request, 'full_name');
        $email = $this->stringPost($request, 'email');
        $phone = $this->stringPost($request, 'phone');
        $password = $this->stringPost($request, 'password');
        $confirmation = $this->stringPost($request, 'password_confirmation');

        try {
            $this->authService->register($fullName, $email, $phone, $password, $confirmation);
        } catch (ValidationException $exception) {
            $this->session->flashErrors($exception->errors());
            $this->session->flashOld([
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
            ]);

            return Response::redirect('/register');
        }

        $this->session->flash('success', 'Аккаунт создан. Добро пожаловать в FixPoint.');

        return Response::redirect('/account');
    }

    public function logout(Request $request, array $params = []): Response
    {
        $this->authService->logout();
        $this->session->flash('success', 'Вы вышли из аккаунта.');

        return Response::redirect('/');
    }

    private function stringPost(Request $request, string $key): string
    {
        $value = $request->post($key, '');

        return is_string($value) ? trim($value) : '';
    }

    private function safeReturnTo(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || !\is_safe_local_path($value)) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        if (in_array($path, ['/login', '/register', '/logout'], true)) {
            return null;
        }

        return $value;
    }

    private function loginUrl(?string $returnTo): string
    {
        return $returnTo === null
            ? '/login'
            : '/login?return_to=' . rawurlencode($returnTo);
    }
}
