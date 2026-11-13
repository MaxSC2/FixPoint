<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\UserRepository;
use FixPoint\Security\PasswordHasher;
use PDOException;

/**
 * Administrator user management. Account input is validated here so the
 * controller never hashes passwords or decides which roles are legal.
 */
final class AdminUserService
{
    /** @var list<string> */
    private const ROLES = ['customer', 'technician', 'admin'];

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher
    ) {
    }

    /**
     * @param array<string, mixed> $actor
     * @return list<array<string, mixed>>
     */
    public function listUsers(array $actor): array
    {
        $this->assertAdmin($actor);

        return $this->users->listAllForAdmin();
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, mixed>|null
     */
    public function findUser(int $id, array $actor): ?array
    {
        $this->assertAdmin($actor);
        $user = $this->users->findById($id);
        if ($user === null) {
            return null;
        }

        unset($user['password_hash']);

        return $user;
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function create(array $actor, array $input): int
    {
        $this->assertAdmin($actor);
        $data = $this->validateProfile($input, null);
        $errors = [];

        $password = $this->scalar($input['password'] ?? '');
        $passwordLength = mb_strlen($password);
        if ($passwordLength < 8) {
            $errors['password'] = 'Пароль должен быть не короче 8 символов.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Пароль слишком длинный.';
        } elseif (preg_match('/\p{L}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $errors['password'] = 'Пароль должен содержать хотя бы одну букву и одну цифру.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            return $this->users->createWithRole(
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $this->hasher->hash($password),
                $data['role'],
                true
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ValidationException(['email' => 'Пользователь с таким email уже существует.']);
            }

            throw $exception;
        }
    }

    /**
     * Update profile fields without touching the password hash. Self-demotion
     * and self-deactivation are rejected, and the last active administrator
     * cannot be removed by another administrator either.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @throws ValidationException|HttpException
     */
    public function update(int $id, array $actor, array $input): void
    {
        $this->assertAdmin($actor);
        $existing = $this->users->findById($id);
        if ($existing === null) {
            throw new HttpException(404, 'User not found');
        }

        $data = $this->validateProfile($input, $id);
        $isActive = $this->parseActive($input, (int) ($existing['is_active'] ?? 0));
        $errors = [];

        if ($isActive === null) {
            $errors['is_active'] = 'Некорректное состояние учётной записи.';
            $isActive = (int) ($existing['is_active'] ?? 0) === 1;
        }

        $wasActiveAdmin = (string) ($existing['role'] ?? '') === 'admin'
            && (int) ($existing['is_active'] ?? 0) === 1;
        $willRemainActiveAdmin = $data['role'] === 'admin' && $isActive;

        if ($wasActiveAdmin && !$willRemainActiveAdmin) {
            if ((int) ($actor['id'] ?? 0) === $id) {
                if ($data['role'] !== 'admin') {
                    $errors['role'] = 'Нельзя снять с себя роль администратора.';
                }
                if (!$isActive) {
                    $errors['is_active'] = 'Нельзя отключить собственную учётную запись.';
                }
            }

            if ($this->users->countActiveAdminsExcluding($id) === 0) {
                $errors['general'] = 'Нельзя оставить систему без активного администратора.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $this->users->updateProfile(
                $id,
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $data['role'],
                $isActive
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ValidationException(['email' => 'Пользователь с таким email уже существует.']);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{full_name: string, email: string, phone: ?string, role: string}
     * @throws ValidationException
     */
    private function validateProfile(array $input, ?int $excludeId): array
    {
        $errors = [];

        $fullName = $this->scalar($input['full_name'] ?? '');
        if (preg_match('/[\x00-\x1F\x7F]/u', $fullName) === 1) {
            $errors['full_name'] = 'Имя содержит недопустимые символы.';
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 160) {
            $errors['full_name'] = 'Укажите имя длиной от 2 до 160 символов.';
        }

        $email = $this->normaliseEmail($this->scalar($input['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Укажите корректный email.';
        } elseif (mb_strlen($email) > 190) {
            $errors['email'] = 'Email слишком длинный.';
        } else {
            $existing = $this->users->findByEmail($email);
            if ($existing !== null && (int) ($existing['id'] ?? 0) !== $excludeId) {
                $errors['email'] = 'Пользователь с таким email уже существует.';
            }
        }

        $phone = $this->scalar($input['phone'] ?? '');
        if ($phone !== ''
            && (mb_strlen($phone) > 32 || preg_match('/^[0-9+()\s.\-]{7,32}$/u', $phone) !== 1)
        ) {
            $errors['phone'] = 'Укажите телефон в допустимом формате.';
        }

        $role = $this->scalar($input['role'] ?? '');
        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Выберите допустимую роль.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone === '' ? null : $phone,
            'role' => $role,
        ];
    }

    /** @param array<string, mixed> $input */
    private function parseActive(array $input, int $current): ?bool
    {
        if (!array_key_exists('is_active', $input)) {
            return $current === 1;
        }

        $value = $this->scalar($input['is_active']);
        if (!in_array($value, ['0', '1'], true)) {
            return null;
        }

        return $value === '1';
    }

    /** @param array<string, mixed> $actor */
    private function assertAdmin(array $actor): void
    {
        if ((string) ($actor['role'] ?? '') !== 'admin') {
            throw new HttpException(403, 'Admin access required');
        }
    }

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
