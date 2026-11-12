<?php

declare(strict_types=1);

namespace FixPoint\Core;

/**
 * Declarative route definition. Parameters use {name} or {name:regex}.
 */
final class Route
{
    /**
     * @param list<string> $methods
     * @param array{0: class-string, 1: string}|\Closure $handler
     * @param list<class-string|object> $middleware
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $path,
        public readonly array|\Closure $handler,
        public readonly array $middleware,
        public readonly string $pattern
    ) {
    }
}
