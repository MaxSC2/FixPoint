<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Core\HttpException;
use FixPoint\Core\Request;
use FixPoint\Core\Router;
use FixPoint\Tests\TestCase;
use LogicException;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', ['Home', 'index']);
        $this->router->get('/services', ['Services', 'index']);
        $this->router->get('/services/{slug}', ['Services', 'show']);
        $this->router->get('/requests/{id:\d+}', ['Requests', 'show']);
        $this->router->post('/logout', ['Auth', 'logout']);
    }

    public function testMatchesStaticAndParameterisedRoutes(): void
    {
        $root = $this->router->match(Request::create('GET', '/'));
        $this->assertSame(['Home', 'index'], $root->handler);

        $list = $this->router->match(Request::create('GET', '/services?q=screen'));
        $this->assertSame(['Services', 'index'], $list->handler);

        $show = $this->router->match(Request::create('GET', '/services/laptop-cleaning'));
        $this->assertSame('laptop-cleaning', $show->params['slug']);

        $request = $this->router->match(Request::create('GET', '/requests/42'));
        $this->assertSame('42', $request->params['id']);
    }

    public function testTrailingSlashIsNormalised(): void
    {
        $match = $this->router->match(Request::create('GET', '/services/'));

        $this->assertSame(['Services', 'index'], $match->handler);
    }

    public function testUnknownRouteThrows404(): void
    {
        try {
            $this->router->match(Request::create('GET', '/missing'));
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->statusCode);

            return;
        }

        $this->fail('Expected 404.');
    }

    public function testWrongMethodThrows405WithAllowHeader(): void
    {
        try {
            $this->router->match(Request::create('POST', '/services'));
        } catch (HttpException $exception) {
            $this->assertSame(405, $exception->statusCode);
            $this->assertArrayHasKey('Allow', $exception->headers);
            $this->assertContains('GET', $exception->headers['Allow']);
            $this->assertContains('HEAD', $exception->headers['Allow']);

            return;
        }

        $this->fail('Expected 405.');
    }

    public function testRegexConstraintRejectsNonNumericId(): void
    {
        try {
            $this->router->match(Request::create('GET', '/requests/abc'));
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->statusCode);

            return;
        }

        $this->fail('Expected 404 for non-numeric id.');
    }

    public function testDuplicateRouteIsRejected(): void
    {
        try {
            $this->router->get('/services', ['Other', 'index']);
        } catch (LogicException $exception) {
            $this->assertContains('Duplicate route', $exception->getMessage());

            return;
        }

        $this->fail('Expected duplicate route rejection.');
    }

    public function testHeadMatchesGetRoute(): void
    {
        $match = $this->router->match(Request::create('HEAD', '/'));

        $this->assertSame(['Home', 'index'], $match->handler);
    }
}
