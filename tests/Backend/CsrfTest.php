<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Security\Csrf;
use FixPoint\Tests\TestCase;

final class CsrfTest extends TestCase
{
    private Csrf $csrf;

    protected function setUp(): void
    {
        app()->session()->start();
        app()->session()->clear();
        $this->csrf = app()->csrf();
    }

    public function testTokenIsStableAndValidatedInConstantTimePath(): void
    {
        $token = $this->csrf->token();

        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $this->csrf->token());
        $this->assertTrue($this->csrf->validate($token));
        $this->assertFalse($this->csrf->validate($token . 'x'));
        $this->assertFalse($this->csrf->validate(null));
        $this->assertFalse($this->csrf->validate(''));
    }

    public function testRotationChangesTheToken(): void
    {
        $old = $this->csrf->token();
        $new = $this->csrf->rotate();

        $this->assertSame(64, strlen($new));
        $this->assertNotSame($old, $new);
        $this->assertFalse($this->csrf->validate($old));
        $this->assertTrue($this->csrf->validate($new));
    }
}
