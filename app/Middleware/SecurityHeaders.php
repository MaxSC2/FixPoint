<?php

declare(strict_types=1);

namespace FixPoint\Middleware;

use FixPoint\Core\Config;
use FixPoint\Core\Request;
use FixPoint\Core\Response;

/**
 * Security headers are applied to every response, including 404/500 pages.
 */
final class SecurityHeaders
{
    public function __construct(private readonly Config $config)
    {
    }

    public function apply(Response $response, Request $request): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; "
                . "frame-ancestors 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
                . "script-src 'self'; font-src 'self'; connect-src 'self'",
            'Cache-Control' => 'no-store, private',
        ];

        if ($request->isSecure() || str_starts_with((string) $this->config->get('app.url'), 'https://')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
