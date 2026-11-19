<?php
declare(strict_types=1);

$threads = isset($threads) && is_array($threads)
    ? array_values(array_filter($threads, 'is_array'))
    : [];
$onlyUnread = !empty($onlyUnread);
$viewerRole = (string) ($authUser['role'] ?? '');
$pageTitle = 'Сообщения — FixPoint';
$activeNav = 'messages';

$formatMessageDate = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('d.m.Y · H:i');
    } catch (Throwable) {
        return '';
    }
};

require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div>
                <span class="eyebrow">Коммуникация по ремонту</span>
                <h1>Сообщения</h1>
                <p>Диалоги клиентов, мастеров и поддержки, привязанные к конкретным заявкам.</p>
            </div>
        </div>

        <div class="messages-toolbar" data-reveal>
            <div class="messages-toolbar__filters" aria-label="Фильтр диалогов">
                <a class="messages-filter<?= !$onlyUnread ? ' is-active' : '' ?>"
                   href="/messages"<?= !$onlyUnread ? ' aria-current="page"' : '' ?>>Все диалоги</a>
                <a class="messages-filter<?= $onlyUnread ? ' is-active' : '' ?>"
                   href="/messages?filter=unread"<?= $onlyUnread ? ' aria-current="page"' : '' ?>>Непрочитанные</a>
            </div>
            <span class="messages-toolbar__note">Обновление при открытии страницы</span>
        </div>

        <?php if ($threads === []): ?>
            <div class="messages-empty" data-reveal>
                <span class="messages-empty__icon" aria-hidden="true">✉</span>
                <h2><?= $onlyUnread ? 'Непрочитанных сообщений нет' : 'Диалогов пока нет' ?></h2>
                <p><?= $onlyUnread
                    ? 'Новые сообщения появятся здесь, когда клиент, мастер или поддержка ответит в заявке.'
                    : 'Когда в заявке появится первое сообщение, здесь будет доступна вся переписка по этому ремонту.' ?></p>
                <?php if ($viewerRole === 'customer'): ?>
                    <a class="button button--secondary" href="/requests">Перейти к заявкам</a>
                <?php elseif ($viewerRole === 'technician'): ?>
                    <a class="button button--secondary" href="/technician/requests">Открыть очередь мастера</a>
                <?php elseif ($viewerRole === 'admin'): ?>
                    <a class="button button--secondary" href="/admin/requests">Открыть реестр заявок</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="messages-thread-list">
                <?php foreach ($threads as $thread): ?>
                    <?php
                    $requestId = (int) ($thread['request_id'] ?? 0);
                    $deviceType = trim((string) ($thread['device_type'] ?? 'Устройство'));
                    $deviceTitle = trim(implode(' ', array_filter([
                        (string) ($thread['device_brand'] ?? ''),
                        (string) ($thread['device_model'] ?? ''),
                    ])));
                    if ($deviceTitle === '') {
                        $deviceTitle = $deviceType;
                    }
                    $unreadCount = (int) ($thread['unread_count'] ?? 0);
                    $lastAuthorId = (int) ($thread['last_author_id'] ?? 0);
                    $viewerId = (int) ($authUser['id'] ?? 0);
                    $lastBody = mb_substr(trim((string) ($thread['last_message_body'] ?? '')), 0, 170);
                    $lastAuthor = $lastAuthorId === $viewerId
                        ? 'Вы'
                        : (string) ($thread['last_author_name'] ?? 'Участник');
                    $counterpart = $viewerRole === 'customer'
                        ? (string) ($thread['technician_name'] ?? 'Мастер ещё не назначен')
                        : (string) ($thread['customer_name'] ?? 'Клиент');
                    ?>
                    <article class="messages-thread-card<?= $unreadCount > 0 ? ' messages-thread-card--unread' : '' ?>" data-reveal>
                        <div class="messages-thread-card__main">
                            <div class="messages-thread-card__top">
                                <span class="messages-thread-card__request"><?= e((string) ($thread['public_number'] ?? 'Заявка')) ?></span>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="messages-thread-card__unread"><?= e((string) $unreadCount) ?> непрочит.</span>
                                <?php endif; ?>
                                <time><?= e($formatMessageDate((string) ($thread['last_message_at'] ?? ''))) ?></time>
                            </div>
                            <h2><?= e($deviceTitle) ?></h2>
                            <p class="messages-thread-card__counterpart"><?= e($counterpart) ?></p>
                            <p class="messages-thread-card__preview"><strong><?= e($lastAuthor) ?>:</strong> <?= e($lastBody) ?></p>
                        </div>
                        <form class="messages-thread-card__action" method="post" action="/messages/<?= e((string) $requestId) ?>/read">
                            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                            <button class="button button--secondary" type="submit">Открыть диалог <span aria-hidden="true">→</span></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="messages-footnote">Показаны последние 100 диалогов. Полная переписка и история ремонта открываются в карточке заявки.</p>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
