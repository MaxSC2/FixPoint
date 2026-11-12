<?php

declare(strict_types=1);

namespace FixPoint\Middleware;

use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Security\Auth;

/**
 * Guests are redirected to the contract login route with a local return_to.
 * JSON clients receive 401 instead of an HTML redirect.
 */
final class AuthMiddleware implements Middleware
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Session $session
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $this->session->flash('info', 'Войдите, чтобы продолжить.');

        return Response::redirect('/login?return_to=' . rawurlencode($this->returnTo($request)));
    }

    private function returnTo(Request $request): string
    {
        $uri = $request->uri();
        if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) {
            return '/';
        }

        return $uri;
    }
}
