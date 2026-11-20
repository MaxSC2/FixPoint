<?php
declare(strict_types=1);

/**
 * Chat of a repair request. The customer, the assigned technician and support
 * share one thread, so a question can be answered without changing the status
 * workflow.
 *
 * @var list<array<string, mixed>> $chatMessages
 * @var string $chatFormAction
 * @var bool $chatCanPost
 * @var array<string, mixed>|null $authUser
 * @var array<string, string> $errors
 */
$chatMessages = isset($chatMessages) && is_array($chatMessages) ? array_values(array_filter($chatMessages, 'is_array')) : [];
$chatFormAction = isset($chatFormAction) ? (string) $chatFormAction : '';
$chatCanPost = !empty($chatCanPost);
$chatRequestId = (string) ($request['id'] ?? '');
$chatAuthorId = isset($authUser['id']) ? (int) $authUser['id'] : 0;
$chatRoleLabels = ['customer' => 'Клиент', 'technician' => 'Мастер', 'admin' => 'Поддержка'];
$chatFormat = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y · H:i');
    } catch (Throwable) {
        return '';
    }
};
?>
<section id="chat" class="request-chat" data-reveal>
    <div class="request-chat__head">
        <div><span class="eyebrow">Общение</span><h2>Чат по заявке</h2></div>
        <span class="request-chat__count"><?= count($chatMessages) ?> <?= e(ru_plural(count($chatMessages), 'сообщение', 'сообщения', 'сообщений')) ?></span>
    </div>

    <?php if ($chatMessages === []): ?>
        <p class="request-chat__empty">Сообщений пока нет. Задайте вопрос мастеру или поддержке — ответ появится здесь же, а в истории заявки сохранится вся переписка.</p>
    <?php else: ?>
        <ol class="request-chat__list">
            <?php foreach ($chatMessages as $message): ?>
                <?php
                $messageAuthorId = (int) ($message['author_id'] ?? 0);
                $messageRole = (string) ($message['author_role'] ?? '');
                $isOwn = $chatAuthorId > 0 && $messageAuthorId === $chatAuthorId;
                ?>
                <li class="request-chat__message<?= $isOwn ? ' request-chat__message--own' : '' ?>">
                    <div class="request-chat__meta">
                        <strong><?= e((string) ($message['author_name'] ?? 'Участник')) ?></strong>
                        <span class="request-chat__role"><?= e($chatRoleLabels[$messageRole] ?? 'Участник') ?></span>
                        <time><?= e($chatFormat((string) ($message['created_at'] ?? ''))) ?></time>
                    </div>
                    <?php if (trim((string) ($message['body'] ?? '')) !== ''): ?>
                        <p><?= nl2br(e((string) $message['body'])) ?></p>
                    <?php endif; ?>
                    <?php $messageAttachments = isset($message['attachments']) && is_array($message['attachments']) ? $message['attachments'] : []; ?>
                    <?php if ($messageAttachments !== []): ?>
                        <div class="request-chat__attachments" aria-label="Вложения сообщения">
                            <?php foreach ($messageAttachments as $attachment): ?>
                                <a class="request-attachment-thumb" href="/requests/<?= e(rawurlencode($chatRequestId)) ?>/attachments/<?= e((string) (int) ($attachment['id'] ?? 0)) ?>" target="_blank" rel="noopener">
                                    <img src="/requests/<?= e(rawurlencode($chatRequestId)) ?>/attachments/<?= e((string) (int) ($attachment['id'] ?? 0)) ?>" alt="<?= e((string) ($attachment['original_name'] ?? 'Изображение')) ?>" loading="lazy">
                                    <span><?= e((string) ($attachment['original_name'] ?? 'Изображение')) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($chatCanPost && $chatFormAction !== ''): ?>
        <form class="request-chat__form" method="post" action="<?= e($chatFormAction) ?>" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
            <label class="field<?= isset($errors['body']) ? ' field--invalid' : '' ?>" for="chat-message-body">
                <span class="field__label">Сообщение</span>
                <textarea id="chat-message-body" name="body" rows="3" maxlength="2000" placeholder="Например: устройство готово к выдаче, можно забрать после 18:00"<?= isset($errors['body']) ? ' aria-invalid="true" aria-describedby="chat-message-body-error"' : '' ?>></textarea>
                <?php if (isset($errors['body'])): ?><small class="field__error" id="chat-message-body-error"><?= e((string) $errors['body']) ?></small><?php endif; ?>
            </label>
            <label class="field<?= isset($errors['attachments']) ? ' field--invalid' : '' ?>" for="chat-attachments">
                <span class="field__label">Фотографии <span class="field__optional">необязательно</span></span>
                <input id="chat-attachments" type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple<?= isset($errors['attachments']) ? ' aria-invalid="true" aria-describedby="chat-attachments-error"' : '' ?>>
                <small class="field__help">До 5 изображений за сообщение, каждый файл до 8 МБ.</small>
                <?php if (isset($errors['attachments'])): ?><small class="field__error" id="chat-attachments-error"><?= e((string) $errors['attachments']) ?></small><?php endif; ?>
            </label>
            <button class="button button--primary" type="submit">Отправить</button>
        </form>
    <?php endif; ?>
</section>
