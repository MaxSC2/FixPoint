<?php

declare(strict_types=1);

namespace FixPoint\Tests;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        $app = app();
        $app->session()->start();
        $app->session()->clear();
        $app->auth()->logout();
    }
}
