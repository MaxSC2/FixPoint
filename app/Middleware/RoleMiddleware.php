<?php

declare(strict_types=1);

namespace FixPoint\Middleware;

use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Core\Session;
use FixPoint\Security\Auth;
use InvalidArgumentException;

/**
 * Server-side role gate. Always pair with AuthMiddleware in routes; this class
 * also handles guests defensively so a missing auth middleware cannot leak data.
 */
final class RoleMiddleware implements Middleware
{
    /** @var list<string> */
    private array $roles;

    public function __construct(
        private readonly Auth $auth,
        private readonly Session $session,
        string ...$roles
    ) {
        foreach ($roles as $role) {
            if (!in_array($role, ['customer', 'technician', 'admin'], true)) {
                throw new InvalidArgumentException('Unknown role: ' . $role);
            }
        }
        if ($roles === []) {
            throw new InvalidArgumentException('RoleMiddleware requires at least one role.');
        }

        $this->roles = array_values(array_unique($roles));
    }

    public static function allow(string ...$roles): self
    {
        return new self(app()->auth(), app()->session(), ...$roles);
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            if ($request->expectsJson()) {
                return Response::json(['error' => 'unauthorized'], 401);
            }

            $this->session->flash('info', 'Войдите, чтобы продолжить.');

            return Response::redirect('/login');
        }

        if (!in_array($user['role'], $this->roles, true)) {
            throw HttpException::forbidden('Role is not allowed for this route');
        }

        return $next($request);
    }
}
