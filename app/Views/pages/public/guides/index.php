<?php
declare(strict_types=1);

$pageTitle = 'Частые неисправности';
$activeNav = 'guides';
$guides = isset($guides) && is_array($guides) ? $guides : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$filters = isset($filters) && is_array($filters) ? $filters : ['q' => '', 'category' => ''];
$pagination = isset($pagination) && is_array($pagination) ? $pagination : ['page' => 1, 'pages' => 1, 'total' => 0];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div>
                <span class="eyebrow">База знаний / Самостоятельная проверка</span>
                <h1>Частые неисправности</h1>
                <p>Понятные и безопасные проверки до обращения в сервис. Это не диагноз и не замена профессиональной диагностики.</p>
            </div>
            <a class="button button--primary button--large" href="/requests/create">Создать заявку <span aria-hidden="true">↗</span></a>
        </div>

        <form class="guide-search" action="/guides" method="get" role="search">
            <label class="field" for="guide-search"><span class="field__label">Поиск по симптомам и устройствам</span>
                <input id="guide-search" type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="Например, ноутбук не включается">
            </label>
            <label class="field" for="guide-category"><span class="field__label">Категория</span>
                <select id="guide-category" name="category">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button--primary" type="submit">Найти статьи</button>
        </form>

        <div class="account-recent__head">
            <div><span class="eyebrow">Проверенные материалы</span><h2>Инструкции по неисправностям</h2></div>
            <span class="guide-card__meta"><?= e((string) ($pagination['total'] ?? 0)) ?> материалов</span>
        </div>

        <?php if ($guides !== []): ?>
            <div class="service-grid" data-reveal>
                <?php foreach ($guides as $guide): ?>
                    <article class="guide-card">
                        <div class="guide-card__meta"><span><?= e($guide['category_label'] ?? '') ?></span><span aria-hidden="true">·</span><span><?= e($guide['device_type'] ?? '') ?></span></div>
                        <h2><?= e($guide['title'] ?? '') ?></h2>
                        <p><?= e($guide['summary'] ?? '') ?></p>
                        <a class="guide-card__link" href="/guides/<?= e($guide['slug'] ?? '') ?>">Открыть инструкцию <span aria-hidden="true">↗</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (($pagination['pages'] ?? 1) > 1): ?>
                <?php
                $page = (int) ($pagination['page'] ?? 1);
                $query = http_build_query(array_filter(['q' => $filters['q'], 'category' => $filters['category']], static fn ($value) => $value !== ''));
                $prefix = '/guides' . ($query !== '' ? '?' . $query . '&' : '?');
                ?>
                <nav class="pagination" aria-label="Страницы базы знаний">
                    <?php if ($page > 1): ?><a class="button button--ghost" href="<?= e($prefix . 'page=' . ($page - 1)) ?>">Назад</a><?php endif; ?>
                    <span>Страница <?= e((string) $page) ?> из <?= e((string) $pagination['pages']) ?></span>
                    <?php if ($page < (int) $pagination['pages']): ?><a class="button button--ghost" href="<?= e($prefix . 'page=' . ($page + 1)) ?>">Далее</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><h2>Подходящих статей пока нет</h2><p>Измените поисковый запрос или создайте заявку, чтобы мастер помог определить причину.</p><a class="button button--primary" href="/requests/create">Создать заявку</a></div>
        <?php endif; ?>

        <p class="guide-notice">При запахе гари, дыме, вздутии аккумулятора, следах жидкости или сильном нагреве прекратите использование устройства и обратитесь к специалисту.</p>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
