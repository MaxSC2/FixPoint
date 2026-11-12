<?php

declare(strict_types=1);

namespace FixPoint\Tests\Integration;

use FixPoint\Database\Migrator;
use FixPoint\Tests\IntegrationTestCase;

final class MigrationTest extends IntegrationTestCase
{
    public function testAllMigrationsAreAppliedAndRerunIsIdempotent(): void
    {
        $migrator = new Migrator(app()->config(), app()->logger());

        $status = $migrator->status();
        $this->assertCount(6, $status);
        foreach ($status as $row) {
            $this->assertFalse($row['pending'], 'Pending migration: ' . $row['migration']);
        }

        $this->assertSame([], $migrator->migrate());
    }

    public function testEveryTableUsesInnodbAndUtf8mb4(): void
    {
        $rows = app()->database()->connection()->query(
            'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"'
        )->fetchAll();

        $this->assertGreaterThan(0, count($rows));
        foreach ($rows as $row) {
            $this->assertSame('InnoDB', $row['ENGINE'], 'Engine mismatch for ' . $row['TABLE_NAME']);
            $this->assertContains('utf8mb4', (string) $row['TABLE_COLLATION']);
        }
    }

    public function testForeignKeysExist(): void
    {
        $rows = app()->database()->connection()->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()'
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ([
            'fk_services_category',
            'fk_requests_customer',
            'fk_requests_technician',
            'fk_requests_service',
            'fk_history_request',
            'fk_history_user',
            'fk_comments_request',
            'fk_comments_author',
        ] as $constraint) {
            $this->assertTrue(in_array($constraint, $rows, true), 'Missing FK: ' . $constraint);
        }
    }

    public function testChecksumsAreStored(): void
    {
        $rows = app()->database()->connection()->query(
            'SELECT migration, checksum FROM schema_migrations ORDER BY migration'
        )->fetchAll();

        $this->assertCount(6, $rows);
        foreach ($rows as $row) {
            $this->assertSame(64, strlen((string) $row['checksum']));
        }
    }
}
