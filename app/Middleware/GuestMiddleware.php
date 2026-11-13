<?php

declare(strict_types=1);

namespace FixPoint\Middleware;

use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Security\Auth;

/**
 * Keeps authenticated users away from guest-only screens by sending them to
 * the real landing page for their role instead of showing a second login form.
 */
final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->check()) {
            return $next($request);
        }

        return Response::redirect(role_home($this->auth->role()));
    }
}
