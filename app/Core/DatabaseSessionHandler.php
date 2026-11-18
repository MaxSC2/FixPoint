<?php

declare(strict_types=1);

namespace FixPoint\\Core;

use PDO;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * MySQL-backed PHP sessions for stateless/ephemeral application containers.
 * A MySQL advisory lock serializes requests sharing the same session ID.
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private ?string $lockName = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        if ($this->lockName !== null) {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute(['lock_name' => $this->lockName]);
            $this->lockName = null;
        }

        return true;
    }

    public function read(string $id): string|false
    {
        $this->lockName = 'fp_session_' . substr(hash('sha256', $id), 0, 48);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name' => $this->lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            $this->lockName = null;

            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT payload FROM app_sessions WHERE session_id = :session_id AND expires_at > :now'
        );
        $statement->execute(['session_id' => $id, 'now' => time()]);
        $payload = $statement->fetchColumn();

        return $payload === false ? '' : (string) $payload;
    }

    public function write(string $id, string $data): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO app_sessions (session_id, payload, expires_at) VALUES (:session_id, :payload, :expires_at)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)'
        );
        $statement->bindValue(':session_id', $id);
        $statement->bindValue(':payload', $data, PDO::PARAM_LOB);
        $statement->bindValue(':expires_at', time() + (int) ini_get('session.gc_maxlifetime'), PDO::PARAM_INT);

        return $statement->execute();
    }

    public function destroy(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM app_sessions WHERE session_id = :session_id');
        return $statement->execute(['session_id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = $this->pdo->prepare('DELETE FROM app_sessions WHERE expires_at <= :now');
        if (!$statement->execute(['now' => time()])) {
            return false;
        }

        return $statement->rowCount();
    }

    public function validateId(string $id): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM app_sessions WHERE session_id = :session_id AND expires_at > :now'
        );
        $statement->execute(['session_id' => $id, 'now' => time()]);

        return $statement->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE app_sessions SET expires_at = :expires_at WHERE session_id = :session_id'
        );
        $statement->execute([
            'expires_at' => time() + (int) ini_get('session.gc_maxlifetime'),
            'session_id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }
}
