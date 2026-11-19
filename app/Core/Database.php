<?php

declare(strict_types=1);

namespace FixPoint\Core;

use DateTimeImmutable;
use DateTimeZone;
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

        // The database has to work in the same timezone as the application.
        // Otherwise MySQL writes CURRENT_TIMESTAMP in one base while PHP writes
        // dates in another, and the interface shows times that differ by the
        // UTC offset. Named zones need the MySQL timezone tables, which a
        // portable installation does not always ship, so the numeric offset of
        // APP_TIMEZONE is used.
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec('SET time_zone = ' . $this->quoteTimeZone());

        return $pdo;
    }

    /** Numeric UTC offset of the application timezone, for example '+05:00'. */
    private function quoteTimeZone(): string
    {
        $name = trim((string) $this->config->get('app.timezone', 'UTC'));
        if ($name === '') {
            $name = 'UTC';
        }

        $offset = (new DateTimeImmutable('now', new DateTimeZone($name)))->getOffset();
        $sign = $offset < 0 ? '-' : '+';
        $absolute = abs($offset);

        return sprintf(
            "'%s%02d:%02d'",
            $sign,
            intdiv($absolute, 3600),
            intdiv($absolute % 3600, 60)
        );
    }
}
