<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\DatabaseSessionHandler;
use FixPoint\Tests\IntegrationTestCase;

final class DatabaseSessionHandlerTest extends IntegrationTestCase
{
    public function testSessionPayloadPersistsAcrossHandlerInstances(): void
    {
        $sessionId = 'deployment-test-' . bin2hex(random_bytes(12));
        $payload = 'user_id|42';

        $writer = new DatabaseSessionHandler(app()->database()->connection());
        $this->assertTrue($writer->open('', 'fixpoint_test'));
        $this->assertTrue($writer->write($sessionId, $payload));
        $this->assertTrue($writer->close());

        $reader = new DatabaseSessionHandler(app()->database()->connection());
        $this->assertTrue($reader->open('', 'fixpoint_test'));
        $this->assertTrue($reader->validateId($sessionId));
        $this->assertSame($payload, $reader->read($sessionId));
        $this->assertTrue($reader->updateTimestamp($sessionId, $payload));
        $this->assertTrue($reader->destroy($sessionId));
        $this->assertTrue($reader->close());
        $this->assertFalse($reader->validateId($sessionId));
    }
}
