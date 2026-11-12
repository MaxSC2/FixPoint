<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\Database;
use FixPoint\Core\Logger;
use Throwable;

final class DatabaseHealthService
{
    public function __construct(
        private readonly Database $database,
        private readonly Logger $logger
    ) {
    }

    public function check(): bool
    {
        try {
            $this->database->connection()->query('SELECT 1')->fetchColumn();

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Database health check failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
