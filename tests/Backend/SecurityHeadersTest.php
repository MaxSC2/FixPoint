<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\Request;
use FixPoint\Core\Response;
use FixPoint\Middleware\SecurityHeaders;
use FixPoint\Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function testHeadersAreAppliedToHtmlResponses(): void
    {
        $middleware = new SecurityHeaders(app()->config());
        $response = $middleware->apply(Response::html('<h1>ok</h1>'), Request::create('GET', '/'));

        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));
        $this->assertContains("frame-ancestors 'none'", (string) $response->header('Content-Security-Policy'));
        $this->assertSame('no-store, private', $response->header('Cache-Control'));
        $this->assertNull($response->header('Strict-Transport-Security'));
    }

    public function testHstsIsAddedForHttpsRequests(): void
    {
        $middleware = new SecurityHeaders(app()->config());
        $request = Request::create('GET', '/', server: ['HTTPS' => 'on']);
        $response = $middleware->apply(Response::html('ok'), $request);

        $this->assertContains('max-age=31536000', (string) $response->header('Strict-Transport-Security'));
    }
}
