<?php

declare(strict_types=1);

namespace FixPoint\Database;

use FixPoint\Core\Config;
use FixPoint\Core\Database;
use FixPoint\Core\Logger;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Forward-only migration runner with checksums.
 *
 * Convention: one SQL statement per migration file. MySQL 8 performs atomic
 * DDL, so a failed statement leaves the schema unchanged and the migration is
 * not recorded. Applied files must never be edited; create a new migration.
 */
final class Migrator
{
    private readonly PDO $pdo;

    private readonly string $path;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        ?string $path = null
    ) {
        $database = new Database($config);
        $database->createDatabaseIfMissing();
        $this->pdo = $database->connection();
        $this->path = rtrim($path ?? (string) $config->get('paths.migrations'), '/\\');
    }

    /**
     * @return list<string> Applied migration filenames.
     */
    public function migrate(bool $fresh = false, bool $force = false): array
    {
        if ($fresh) {
            $this->fresh($force);
        }

        $this->ensureMigrationTable();
        $applied = $this->appliedMap();
        $executed = [];

        foreach ($this->files() as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            if ($checksum === false) {
                throw new RuntimeException('Cannot checksum migration: ' . $name);
            }

            if (isset($applied[$name])) {
                if (!hash_equals($applied[$name], $checksum)) {
                    throw new RuntimeException(
                        'Migration checksum mismatch: ' . $name . '. Applied migrations are immutable; add a new migration.'
                    );
                }
                continue;
            }

            $sql = trim((string) file_get_contents($file));
            if ($sql === '') {
                continue;
            }

            try {
                $this->pdo->exec($sql);
                $statement = $this->pdo->prepare(
                    'INSERT INTO schema_migrations (migration, checksum) VALUES (:migration, :checksum)'
                );
                $statement->execute(['migration' => $name, 'checksum' => $checksum]);
            } catch (Throwable $exception) {
                throw new RuntimeException('Migration failed: ' . $name, 0, $exception);
            }

            $executed[] = $name;
            $this->logger->info('Migration applied.', ['migration' => $name]);
        }

        return $executed;
    }

    /**
     * @return list<array{migration: string, checksum: string, applied_at: string|null, pending: bool}>
     */
    public function status(): array
    {
        $this->ensureMigrationTable();
        $applied = $this->appliedDetails();
        $rows = [];

        foreach ($this->files() as $file) {
            $name = basename($file);
            $rows[] = [
                'migration' => $name,
                'checksum' => (string) hash_file('sha256', $file),
                'applied_at' => $applied[$name]['applied_at'] ?? null,
                'pending' => !isset($applied[$name]),
            ];
        }

        return $rows;
    }

    public function fresh(bool $force = false): void
    {
        $environment = (string) $this->config->get('app.env');
        if (!in_array($environment, ['local', 'testing'], true) && !$force) {
            throw new RuntimeException('Refusing to drop tables outside local/testing without --force.');
        }

        $tables = $this->pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $row) {
                $table = (string) $row[0];
                $this->pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function ensureMigrationTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(255) NOT NULL,
                checksum CHAR(64) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, string> */
    private function appliedMap(): array
    {
        $rows = $this->pdo->query('SELECT migration, checksum FROM schema_migrations')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['migration']] = (string) $row['checksum'];
        }

        return $map;
    }

    /** @return array<string, array{checksum: string, applied_at: string}> */
    private function appliedDetails(): array
    {
        $rows = $this->pdo->query('SELECT migration, checksum, applied_at FROM schema_migrations')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['migration']] = [
                'checksum' => (string) $row['checksum'],
                'applied_at' => (string) $row['applied_at'],
            ];
        }

        return $map;
    }

    /** @return list<string> */
    private function files(): array
    {
        if (!is_dir($this->path)) {
            throw new RuntimeException('Migrations directory not found: ' . $this->path);
        }

        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            return [];
        }
        sort($files, SORT_STRING);

        return array_values($files);
    }
}
