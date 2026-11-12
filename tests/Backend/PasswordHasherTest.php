<?php

declare(strict_types=1);

namespace FixPoint\Tests\Backend;

use FixPoint\Security\PasswordHasher;
use FixPoint\Tests\TestCase;
use InvalidArgumentException;

final class PasswordHasherTest extends TestCase
{
    public function testHashVerifyAndRehashDetection(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('local-test-password');

        $this->assertNotSame('local-test-password', $hash);
        $this->assertTrue($hasher->verify('local-test-password', $hash));
        $this->assertFalse($hasher->verify('wrong-password', $hash));
        $this->assertFalse($hasher->needsRehash($hash));
    }

    public function testEmptyPasswordIsRejected(): void
    {
        $hasher = new PasswordHasher();
        try {
            $hasher->hash('');
        } catch (InvalidArgumentException $exception) {
            $this->assertContains('empty', strtolower($exception->getMessage()));

            return;
        }

        $this->fail('Expected empty password rejection.');
    }

    public function testVerifyRejectsEmptyInputs(): void
    {
        $hasher = new PasswordHasher();

        $this->assertFalse($hasher->verify('', '$2y$10$abc'));
        $this->assertFalse($hasher->verify('password', ''));
    }
}
