<?php

declare(strict_types=1);

namespace FixPoint\Controllers;

use FixPoint\Core\Config;
use FixPoint\Core\HttpException;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Core\ViewRenderer;
use FixPoint\Security\Auth;
use FixPoint\Security\Csrf;

/**
 * Thin base controller. It prepares the layout contract keys once so every
 * view receives pageTitle, activeNav, authUser, flash, errors, old, csrfToken.
 */
abstract class Controller
{
    public function __construct(
        protected readonly Config $config,
        protected readonly ViewRenderer $view,
        protected readonly Auth $auth,
        protected readonly Csrf $csrf,
        protected readonly Session $session
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function renderPage(string $view, array $data = [], int $status = 200): Response
    {
        return $this->view->renderPage($view, $this->viewData($data), $status);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed> */
    protected function viewData(array $data): array
    {
        $old = $this->session->pullOld();
        $old = array_filter(
            $old,
            static fn (mixed $value, string|int $key): bool => is_string($key)
                && preg_match('/(password|passwd|secret|token)/i', $key) !== 1,
            ARRAY_FILTER_USE_BOTH
        );

        $errors = [];
        foreach ($this->session->pullErrors() as $field => $message) {
            if (is_string($field) && is_scalar($message)) {
                $errors[$field] = (string) $message;
            }
        }

        return array_merge([
            'pageTitle' => (string) $this->config->get('app.name'),
            'activeNav' => 'home',
            'authUser' => $this->auth->user(),
            'flash' => $this->session->pullFlash(),
            'errors' => $errors,
            'old' => $old,
            'csrfToken' => $this->csrf->token(),
        ], $data);
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}
