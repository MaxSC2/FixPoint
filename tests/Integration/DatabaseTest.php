<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Database;
use FixPoint\Tests\IntegrationTestCase;

final class DatabaseTest extends IntegrationTestCase
{
    public function testPdoConnectsWithUtf8mb4AndApplicationTimeZone(): void
    {
        $pdo = app()->database()->connection();
        $timezone = (string) config('app.timezone', 'UTC');

        $this->assertSame('1', (string) $pdo->query('SELECT 1')->fetchColumn());
        $this->assertSame('utf8mb4', $pdo->query('SELECT @@character_set_connection')->fetchColumn());

        // The database has to work in the timezone of the application. If the
        // two clocks disagree, timestamps written by MySQL and timestamps
        // written by PHP end up in different bases and the interface shows
        // times shifted by the UTC offset.
        $expectedOffset = (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('P');
        $this->assertSame($expectedOffset, $pdo->query('SELECT @@session.time_zone')->fetchColumn());

        $databaseNow = new \DateTimeImmutable((string) $pdo->query('SELECT NOW()')->fetchColumn());
        $applicationNow = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        $difference = abs($databaseNow->getTimestamp() - $applicationNow->getTimestamp());
        $this->assertTrue(
            $difference <= 2,
            'MySQL and PHP must report the same current time, difference: ' . $difference . 's'
        );

        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
        // PHP returns bool on some builds and int 0/1 on others.
        $this->assertSame(0, (int) $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES));
    }

    public function testCoreTablesExist(): void
    {
        $tables = $this->tableNames();
        foreach ([
            'schema_migrations',
            'users',
            'service_categories',
            'services',
            'repair_requests',
            'request_status_history',
            'request_comments',
        ] as $table) {
            $this->assertTrue(in_array($table, $tables, true), 'Missing table: ' . $table);
        }
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        $rows = app()->database()->connection()
            ->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
            ->fetchAll(\PDO::FETCH_NUM);

        return array_map(static fn (array $row): string => (string) $row[0], $rows);
    }
}
