<?php

declare(strict_types=1);

namespace FixPoint\Middleware;

use FixPoint\Core\Request;
use FixPoint\Core\Response;

interface Middleware
{
    public function handle(Request $request, callable $next): Response;
}
