<?php

declare(strict_types=1);

namespace FixPoint\Services;

use FixPoint\Core\HttpException;
use FixPoint\Core\ValidationException;
use FixPoint\Repositories\ChatRepository;
use FixPoint\Repositories\RequestRepository;
use PDO;
use Throwable;

final class ChatService
{
    public function __construct(
        private readonly ChatRepository $chats,
        private readonly RequestRepository $requests,
        private readonly AttachmentService $attachments
    ) {
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function messagesForRequest(array $request, array $user): array
    {
        $requestId = (int) ($request['id'] ?? 0);
        if ($requestId < 1 || !$this->canAccess($request, $user)) {
            return [];
        }

        $messages = $this->chats->messagesForRequest($requestId);
        $attachmentsByMessage = $this->attachments->forMessages(
            $requestId,
            array_map(static fn (array $message): int => (int) ($message['id'] ?? 0), $messages)
        );
        foreach ($messages as &$message) {
            $messageId = (int) ($message['id'] ?? 0);
            $message['attachments'] = $attachmentsByMessage[$messageId] ?? [];
        }
        unset($message);

        return $messages;
    }

    /**
     * @param array<string, mixed> $user
     * @throws ValidationException|HttpException
     */
    public function postMessage(int $requestId, array $user, string $body, array $uploads = []): void
    {
        $request = $this->requests->findById($requestId);
        if ($request === null || !$this->canAccess($request, $user)) {
            throw new HttpException(404, 'Request not found');
        }

        $body = trim($body);
        if ($body === '' && $uploads === []) {
            throw new ValidationException(['body' => 'Введите сообщение или прикрепите изображение.']);
        }
        if (mb_strlen($body) > 2000) {
            throw new ValidationException(['body' => 'Сообщение слишком длинное (максимум 2000 символов).']);
        }
        if ($this->hasControlCharacters($body)) {
            throw new ValidationException(['body' => 'Сообщение содержит недопустимые символы.']);
        }

        $savedKeys = [];
        try {
            $this->chats->addMessage(
                $requestId,
                (int) ($user['id'] ?? 0),
                $body === '' ? null : $body,
                function (PDO $pdo, int $messageId) use ($requestId, $user, $uploads, &$savedKeys): void {
                    $savedKeys = $this->attachments->storeUploads(
                        $pdo,
                        $requestId,
                        $messageId,
                        (int) ($user['id'] ?? 0),
                        $uploads
                    );
                }
            );
        } catch (Throwable $exception) {
            $this->attachments->cleanup($savedKeys);
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function listThreads(array $user, bool $onlyUnread = false): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        if ($userId < 1 || !in_array($role, ['customer', 'technician', 'admin'], true)) {
            throw new HttpException(403, 'Chat access denied');
        }

        return $this->chats->listThreads($userId, $role, $onlyUnread);
    }

    /** @param array<string, mixed> $user */
    public function unreadTotal(array $user): int
    {
        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        if ($userId < 1 || !in_array($role, ['customer', 'technician', 'admin'], true)) {
            return 0;
        }

        return $this->chats->unreadTotal($userId, $role);
    }

    /**
     * Advance the read cursor only after checking the request's actual owner
     * or assigned technician.
     *
     * @param array<string, mixed> $user
     */
    public function markRead(int $requestId, array $user): void
    {
        $request = $this->requests->findById($requestId);
        if ($request === null || !$this->canAccess($request, $user)) {
            throw new HttpException(404, 'Request not found');
        }

        $this->chats->markRead($requestId, (int) ($user['id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $user
     */
    private function canAccess(array $request, array $user): bool
    {
        $userId = (int) ($user['id'] ?? 0);

        return match ((string) ($user['role'] ?? '')) {
            'admin' => $userId > 0,
            'customer' => $userId > 0 && (int) ($request['customer_id'] ?? 0) === $userId,
            'technician' => $userId > 0 && (int) ($request['technician_id'] ?? 0) === $userId,
            default => false,
        };
    }

    private function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
    }
}
