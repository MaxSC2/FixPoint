<?php

declare(strict_types=1);

namespace FixPoint\Database;

use FixPoint\Core\Config;
use FixPoint\Core\Env;
use FixPoint\Security\PasswordHasher;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Idempotent local seeder. Natural keys (email, slug, public_number) make a
 * repeated run safe. Existing password hashes are never overwritten.
 */
final class Seeder
{
    private readonly string $dataFile;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PasswordHasher $hasher,
        private readonly Config $config,
        ?string $dataFile = null
    ) {
        $this->dataFile = $dataFile ?? (string) $config->get('paths.seed_data');
    }

    /** @return array<string, int> */
    public function run(bool $force = false): array
    {
        if ($this->config->isProduction() && !$force) {
            throw new RuntimeException('Refusing to seed production data without --force.');
        }

        if (!is_file($this->dataFile)) {
            throw new RuntimeException('Seed data file not found: ' . $this->dataFile);
        }

        $data = require $this->dataFile;
        if (!is_array($data)) {
            throw new RuntimeException('Seed data file must return an array.');
        }

        $password = Env::get('SEED_PASSWORD', 'FixPoint!Dev1') ?? 'FixPoint!Dev1';
        if (mb_strlen($password) < 8) {
            throw new RuntimeException('SEED_PASSWORD must contain at least 8 characters.');
        }
        $passwordHash = $this->hasher->hash($password);

        $this->pdo->beginTransaction();
        try {
            $userIds = $this->seedUsers($data['users'] ?? [], $passwordHash);
            $categoryIds = $this->seedCategories($data['categories'] ?? []);
            $serviceIds = $this->seedServices($data['services'] ?? [], $categoryIds);
            $requestIds = $this->seedRequests($data['requests'] ?? [], $userIds, $serviceIds);
            $history = $this->seedHistory($data['history'] ?? [], $requestIds, $userIds);
            $comments = $this->seedComments($data['comments'] ?? [], $requestIds, $userIds);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'users' => count($userIds),
            'categories' => count($categoryIds),
            'services' => count($serviceIds),
            'requests' => count($requestIds),
            'history' => $history,
            'comments' => $comments,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function seedUsers(array $rows, string $passwordHash): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, is_active)
             VALUES (:full_name, :email, :phone, :password_hash, :role, :is_active)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                phone = VALUES(phone),
                role = VALUES(role),
                is_active = VALUES(is_active)'
        );

        $ids = [];
        foreach ($rows as $row) {
            $statement->execute([
                'full_name' => (string) $row['full_name'],
                'email' => strtolower((string) $row['email']),
                'phone' => $row['phone'] ?? null,
                'password_hash' => $passwordHash,
                'role' => (string) $row['role'],
                'is_active' => (int) ($row['is_active'] ?? 1),
            ]);
            $ids[(string) $row['key']] = $this->idBy('users', 'email', strtolower((string) $row['email']));
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function seedCategories(array $rows): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO service_categories (name, slug, description, sort_order, is_active)
             VALUES (:name, :slug, :description, :sort_order, :is_active)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                sort_order = VALUES(sort_order),
                is_active = VALUES(is_active)'
        );

        $ids = [];
        foreach ($rows as $row) {
            $statement->execute([
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'description' => $row['description'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => (int) ($row['is_active'] ?? 1),
            ]);
            $ids[(string) $row['slug']] = $this->idBy('service_categories', 'slug', (string) $row['slug']);
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int> $categoryIds
     * @return array<string, int>
     */
    private function seedServices(array $rows, array $categoryIds): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO services
                (category_id, name, slug, short_description, description, price_from, estimated_minutes, is_active)
             VALUES
                (:category_id, :name, :slug, :short_description, :description, :price_from, :estimated_minutes, :is_active)
             ON DUPLICATE KEY UPDATE
                category_id = VALUES(category_id),
                name = VALUES(name),
                short_description = VALUES(short_description),
                description = VALUES(description),
                price_from = VALUES(price_from),
                estimated_minutes = VALUES(estimated_minutes),
                is_active = VALUES(is_active)'
        );

        $ids = [];
        foreach ($rows as $row) {
            $categoryId = $categoryIds[(string) $row['category']] ?? null;
            if ($categoryId === null) {
                throw new RuntimeException('Unknown seed category: ' . (string) $row['category']);
            }

            $statement->execute([
                'category_id' => $categoryId,
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'short_description' => $row['short_description'] ?? null,
                'description' => $row['description'] ?? null,
                'price_from' => $row['price_from'] ?? null,
                'estimated_minutes' => $row['estimated_minutes'] ?? null,
                'is_active' => (int) ($row['is_active'] ?? 1),
            ]);
            $ids[(string) $row['slug']] = $this->idBy('services', 'slug', (string) $row['slug']);
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int> $userIds
     * @param array<string, int> $serviceIds
     * @return array<string, int>
     */
    private function seedRequests(array $rows, array $userIds, array $serviceIds): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO repair_requests
                (public_number, customer_id, technician_id, service_id, status, priority,
                 device_type, device_brand, device_model, problem_description, diagnostic_result,
                 estimated_cost, final_cost, estimated_ready_at, customer_contact,
                 created_at, updated_at, completed_at)
             VALUES
                (:public_number, :customer_id, :technician_id, :service_id, :status, :priority,
                 :device_type, :device_brand, :device_model, :problem_description, :diagnostic_result,
                 :estimated_cost, :final_cost, :estimated_ready_at, :customer_contact,
                 :created_at, :updated_at, :completed_at)
             ON DUPLICATE KEY UPDATE
                -- A seed is additive for existing requests: never reset a request
                -- that a developer or user has already changed in the application.
                public_number = VALUES(public_number)'
        );

        $ids = [];
        foreach ($rows as $row) {
            $customerId = $userIds[(string) $row['customer']] ?? null;
            if ($customerId === null) {
                throw new RuntimeException('Unknown seed customer: ' . (string) $row['customer']);
            }

            $technicianId = null;
            if (($row['technician'] ?? null) !== null) {
                $technicianId = $userIds[(string) $row['technician']] ?? null;
                if ($technicianId === null) {
                    throw new RuntimeException('Unknown seed technician: ' . (string) $row['technician']);
                }
            }

            $serviceId = $serviceIds[(string) $row['service']] ?? null;
            if ($serviceId === null) {
                throw new RuntimeException('Unknown seed service: ' . (string) $row['service']);
            }

            $createdAt = (string) $row['created_at'];
            $statement->execute([
                'public_number' => (string) $row['public_number'],
                'customer_id' => $customerId,
                'technician_id' => $technicianId,
                'service_id' => $serviceId,
                'status' => (string) $row['status'],
                'priority' => (string) $row['priority'],
                'device_type' => (string) $row['device_type'],
                'device_brand' => $row['device_brand'] ?? null,
                'device_model' => $row['device_model'] ?? null,
                'problem_description' => (string) $row['problem_description'],
                'diagnostic_result' => $row['diagnostic_result'] ?? null,
                'estimated_cost' => $row['estimated_cost'] ?? null,
                'final_cost' => $row['final_cost'] ?? null,
                'estimated_ready_at' => $row['estimated_ready_at'] ?? null,
                'customer_contact' => (string) $row['customer_contact'],
                'created_at' => $createdAt,
                'updated_at' => (string) ($row['updated_at'] ?? $createdAt),
                'completed_at' => $row['completed_at'] ?? null,
            ]);
            $ids[(string) $row['public_number']] = $this->idBy(
                'repair_requests',
                'public_number',
                (string) $row['public_number']
            );
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int> $requestIds
     * @param array<string, int> $userIds
     */
    private function seedHistory(array $rows, array $requestIds, array $userIds): int
    {
        $exists = $this->pdo->prepare(
            'SELECT COUNT(*) FROM request_status_history
             WHERE request_id = :request_id AND to_status = :to_status
               AND created_at = :created_at AND changed_by = :changed_by'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO request_status_history
                (request_id, from_status, to_status, reason, changed_by, created_at)
             VALUES
                (:request_id, :from_status, :to_status, :reason, :changed_by, :created_at)'
        );

        $count = 0;
        foreach ($rows as $row) {
            $requestId = $requestIds[(string) $row['request']] ?? null;
            $userId = $userIds[(string) $row['changed_by']] ?? null;
            if ($requestId === null || $userId === null) {
                throw new RuntimeException('Unknown request/user in seed history.');
            }

            $exists->execute([
                'request_id' => $requestId,
                'to_status' => (string) $row['to'],
                'created_at' => (string) $row['created_at'],
                'changed_by' => $userId,
            ]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }

            $insert->execute([
                'request_id' => $requestId,
                'from_status' => $row['from'] ?? null,
                'to_status' => (string) $row['to'],
                'reason' => $row['reason'] ?? null,
                'changed_by' => $userId,
                'created_at' => (string) $row['created_at'],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int> $requestIds
     * @param array<string, int> $userIds
     */
    private function seedComments(array $rows, array $requestIds, array $userIds): int
    {
        $exists = $this->pdo->prepare(
            'SELECT COUNT(*) FROM request_comments
             WHERE request_id = :request_id AND author_id = :author_id
               AND created_at = :created_at AND body = :body'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO request_comments (request_id, author_id, body, is_internal, created_at)
             VALUES (:request_id, :author_id, :body, :is_internal, :created_at)'
        );

        $count = 0;
        foreach ($rows as $row) {
            $requestId = $requestIds[(string) $row['request']] ?? null;
            $userId = $userIds[(string) $row['author']] ?? null;
            if ($requestId === null || $userId === null) {
                throw new RuntimeException('Unknown request/user in seed comments.');
            }

            $body = (string) $row['body'];
            $createdAt = (string) $row['created_at'];
            $exists->execute([
                'request_id' => $requestId,
                'author_id' => $userId,
                'created_at' => $createdAt,
                'body' => $body,
            ]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }

            $insert->execute([
                'request_id' => $requestId,
                'author_id' => $userId,
                'body' => $body,
                'is_internal' => (int) ($row['is_internal'] ?? 0),
                'created_at' => $createdAt,
            ]);
            $count++;
        }

        return $count;
    }

    private function idBy(string $table, string $column, string $value): int
    {
        $allowed = [
            'users' => ['email'],
            'service_categories' => ['slug'],
            'services' => ['slug'],
            'repair_requests' => ['public_number'],
        ];
        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            throw new RuntimeException('Unsafe seed lookup.');
        }

        $statement = $this->pdo->prepare(
            sprintf('SELECT id FROM `%s` WHERE `%s` = :value LIMIT 1', $table, $column)
        );
        $statement->execute(['value' => $value]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Seed lookup failed for ' . $table . '.' . $column);
        }

        return (int) $id;
    }
}
