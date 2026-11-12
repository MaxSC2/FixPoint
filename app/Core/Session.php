<?php

declare(strict_types=1);

namespace FixPoint\Core;

use RuntimeException;

/**
 * Hardened session wrapper. File sessions live under storage/sessions, cookies
 * are HttpOnly and SameSite by default, IDs rotate on login/logout, and idle
 * sessions expire after SESSION_LIFETIME minutes.
 */
final class Session
{
    private bool $started = false;

    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        $session = $this->config->get('session');
        $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $session['name']) ?: 'fixpoint_session';

        $savePath = (string) $session['save_path'];
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0775, true);
        }
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        $lifetime = (int) $session['lifetime'] * 60;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_samesite', (string) $session['samesite']);

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) $session['secure'],
            'httponly' => true,
            'samesite' => (string) $session['samesite'],
        ]);

        if (!@session_start()) {
            throw new RuntimeException('Unable to start the session.');
        }

        $this->started = true;

        $lastActivity = $_SESSION['_last_activity'] ?? null;
        if (is_int($lastActivity) && time() - $lastActivity > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash'] = [
                'type' => 'info',
                'message' => 'Сессия истекла. Войдите снова.',
            ];
        }

        $_SESSION['_last_activity'] = time();
        $_SESSION['_created_at'] ??= time();
    }

    public function id(): string
    {
        $this->start();

        return session_id();
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();
        session_regenerate_id($deleteOldSession);
    }

    public function destroy(): void
    {
        if (!$this->started && session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->started = false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    public function clear(): void
    {
        $this->start();
        $previousFlash = $_SESSION['_flash'] ?? null;
        $_SESSION = [];
        if ($previousFlash !== null) {
            $_SESSION['_flash'] = $previousFlash;
        }
        $_SESSION['_last_activity'] = time();
    }

    public function flash(string $type, string $message): void
    {
        if (!in_array($type, ['success', 'error', 'info', 'warning'], true)) {
            throw new \InvalidArgumentException('Unknown flash type.');
        }

        $this->set('_flash', ['type' => $type, 'message' => $message]);
    }

    /** @return array{type: string, message: string}|null */
    public function pullFlash(): ?array
    {
        $flash = $this->get('_flash');
        $this->remove('_flash');

        if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
            return null;
        }

        return ['type' => (string) $flash['type'], 'message' => (string) $flash['message']];
    }

    /** @param array<string, string> $errors */
    public function flashErrors(array $errors): void
    {
        $this->set('_errors', $errors);
    }

    /** @return array<string, string> */
    public function pullErrors(): array
    {
        $errors = $this->get('_errors', []);
        $this->remove('_errors');

        return is_array($errors) ? $errors : [];
    }

    /** @param array<string, mixed> $input */
    public function flashOld(array $input): void
    {
        $this->set('_old', $input);
    }

    /** @return array<string, mixed> */
    public function pullOld(): array
    {
        $old = $this->get('_old', []);
        $this->remove('_old');

        return is_array($old) ? $old : [];
    }
}
