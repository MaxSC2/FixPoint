<?php

declare(strict_types=1);

namespace FixPoint\Middleware;

use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Security\Csrf;

/**
 * Rejects state-changing requests without a valid session token. GET/HEAD
 * requests must never mutate state, so they are not token-checked.
 */
final class CsrfMiddleware implements Middleware
{
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::PROTECTED_METHODS, true)) {
            return $next($request);
        }

        $token = $request->post('_token');
        if (!is_string($token) || $token === '') {
            $token = $request->header('X-CSRF-Token');
        }

        if (!$this->csrf->validate($token)) {
            if ($request->expectsJson()) {
                return Response::json(['error' => 'csrf_token_invalid'], 419);
            }

            throw HttpException::csrf();
        }

        return $next($request);
    }
}
