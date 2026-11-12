<?php

declare(strict_types=1);

namespace FixPoint\Core;

use PDO;
use PDOException;

/**
 * PDO factory with exception mode, native prepared statements and utf8mb4.
 */
final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $this->connection = $this->connect(true);

        return $this->connection;
    }

    public function serverConnection(): PDO
    {
        return $this->connect(false);
    }

    public function createDatabaseIfMissing(): void
    {
        $name = (string) $this->config->get('database.name');
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new PDOException('Unsafe database name.');
        }

        $this->serverConnection()->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $name
        ));
    }

    public function name(): string
    {
        return (string) $this->config->get('database.name');
    }

    private function connect(bool $withDatabase): PDO
    {
        $database = $this->config->get('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s%s',
            $database['host'],
            $database['port'],
            $database['charset'],
            $withDatabase ? ';dbname=' . $database['name'] : ''
        );

        $pdo = new PDO($dsn, (string) $database['username'], (string) $database['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        // Keep TIMESTAMP math predictable: the application exchanges UTC with MySQL.
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
