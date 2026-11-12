<?php

declare(strict_types=1);

namespace FixPoint\Core;

use LogicException;

/**
 * Explicit router. Handlers are class/method pairs or closures registered in
 * config/routes.php; no user input is ever used for includes or dynamic code.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|object> $middleware
     */
    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add(['GET'], $path, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|object> $middleware
     */
    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add(['POST'], $path, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|object> $middleware
     */
    public function add(array $methods, string $path, array|callable $handler, array $middleware = []): void
    {
        $methods = array_values(array_unique(array_map(static fn (string $method): string => strtoupper($method), $methods)));
        if ($methods === []) {
            throw new LogicException('A route needs at least one HTTP method.');
        }

        $path = $this->normalisePath($path);
        $pattern = $this->compile($path);

        if (!is_array($handler) && !$handler instanceof \Closure) {
            $handler = \Closure::fromCallable($handler);
        }

        foreach ($this->routes as $route) {
            if ($route->pattern === $pattern && array_intersect($route->methods, $methods) !== []) {
                throw new LogicException(sprintf('Duplicate route: %s %s', implode('|', $methods), $path));
            }
        }

        $this->routes[] = new Route($methods, $path, $handler, $middleware, $pattern);
    }

    /** @throws HttpException */
    public function match(Request $request): RouteMatch
    {
        $path = $request->path();
        $method = $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            $matches = [];
            if (preg_match($route->pattern, $path, $matches) !== 1) {
                continue;
            }

            $methodAllowed = in_array($method, $route->methods, true)
                || ($method === 'HEAD' && in_array('GET', $route->methods, true));

            if (!$methodAllowed) {
                array_push($allowed, ...$route->methods);
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return new RouteMatch($route->handler, $params, $route->middleware);
        }

        if ($allowed !== []) {
            if (in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }
            throw HttpException::methodNotAllowed($allowed);
        }

        throw HttpException::notFound('Route not found');
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    private function normalisePath(string $path): string
    {
        if ($path === '') {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function compile(string $path): string
    {
        if ($path === '/') {
            return '#^/$#uD';
        }

        $segments = explode('/', trim($path, '/'));
        $compiled = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::(.+))?\}$/', $segment, $match) === 1) {
                $name = $match[1];
                $regex = $match[2] ?? '[^/]+';
                if (str_contains($regex, '#')) {
                    throw new LogicException('Route parameter regex must not contain the "#" delimiter.');
                }
                $compiled[] = sprintf('(?P<%s>%s)', $name, $regex);
                continue;
            }

            $compiled[] = preg_quote($segment, '#');
        }

        return '#^/' . implode('/', $compiled) . '$#uD';
    }
}
