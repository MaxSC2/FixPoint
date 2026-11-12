<?php

declare(strict_types=1);

namespace FixPoint\Core;

final class RouteMatch
{
    /**
     * @param array{0: class-string, 1: string}|\Closure $handler
     * @param array<string, string> $params
     * @param list<class-string|object> $middleware
     */
    public function __construct(
        public readonly array|\Closure $handler,
        public readonly array $params,
        public readonly array $middleware
    ) {
    }
}
