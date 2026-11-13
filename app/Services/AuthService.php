<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\ValidationException;
use FixPoint\Repositories\UserRepository;
use FixPoint\Security\Auth;
use FixPoint\Security\PasswordHasher;
use PDOException;

/**
 * Credential and registration rules. HTTP controllers only translate the
 * result into redirects/flashes; they never touch password hashes directly.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Auth $auth
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $email = $this->normaliseEmail($email);
        if ($email === '' || $password === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || (int) $user['is_active'] !== 1) {
            return false;
        }

        if (!$this->hasher->verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if ($this->hasher->needsRehash((string) $user['password_hash'])) {
            $this->users->updatePasswordHash((int) $user['id'], $this->hasher->hash($password));
        }

        $this->auth->login((int) $user['id']);

        return true;
    }

    /**
     * Validate and create a customer account, then authenticate it.
     *
     * @throws ValidationException
     */
    public function register(
        string $fullName,
        string $email,
        string $phone,
        string $password,
        string $passwordConfirmation
    ): int {
        $errors = [];
        $fullName = trim($fullName);
        $email = $this->normaliseEmail($email);
        $phone = trim($phone);

        if (preg_match('/[\x00-\x1F\x7F]/u', $fullName) === 1) {
            $errors['full_name'] = 'Имя содержит недопустимые символы.';
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 160) {
            $errors['full_name'] = 'Укажите имя длиной от 2 до 160 символов.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Укажите корректный email.';
        } elseif (mb_strlen($email) > 190) {
            $errors['email'] = 'Email слишком длинный.';
        } elseif ($this->users->findByEmail($email) !== null) {
            $errors['email'] = 'Пользователь с таким email уже зарегистрирован.';
        }

        if ($phone !== '') {
            if (mb_strlen($phone) > 32 || preg_match('/^[0-9+()\s.\-]{7,32}$/u', $phone) !== 1) {
                $errors['phone'] = 'Укажите телефон в допустимом формате.';
            }
        }

        $passwordLength = mb_strlen($password);
        if ($passwordLength < 8) {
            $errors['password'] = 'Пароль должен быть не короче 8 символов.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Пароль слишком длинный.';
        } elseif (preg_match('/\p{L}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $errors['password'] = 'Пароль должен содержать хотя бы одну букву и одну цифру.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Пароли не совпадают.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $userId = $this->users->create(
                $fullName,
                $email,
                $phone === '' ? null : $phone,
                $this->hasher->hash($password)
            );
        } catch (PDOException $exception) {
            // The unique email index is the final guard against a concurrent signup.
            if ((string) $exception->getCode() === '23000') {
                throw new ValidationException(['email' => 'Пользователь с таким email уже зарегистрирован.']);
            }

            throw $exception;
        }

        $this->auth->login($userId);

        return $userId;
    }

    public function logout(): void
    {
        $this->auth->logout();
    }

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
