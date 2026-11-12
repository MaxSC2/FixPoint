<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Core\Database;
use FixPoint\Tests\IntegrationTestCase;

final class DatabaseTest extends IntegrationTestCase
{
    public function testPdoConnectsWithUtf8mb4AndUtc(): void
    {
        $pdo = app()->database()->connection();

        $this->assertSame('1', (string) $pdo->query('SELECT 1')->fetchColumn());
        $this->assertSame('utf8mb4', $pdo->query('SELECT @@character_set_connection')->fetchColumn());
        $this->assertSame('+00:00', $pdo->query('SELECT @@session.time_zone')->fetchColumn());
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
