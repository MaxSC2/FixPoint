# 🚨 MODULE 4 — срочный план сдачи FixPoint

> [!CAUTION]
> **<u>СРОК СДАЧИ: ЧЕТВЕРГ, 19 НОЯБРЯ 2026 ГОДА</u>**
>
> Это главный контрольный документ. Красные исторические сборки оставлены в таблице намеренно; зелёный статус относится только к указанному commit SHA.

## Текущее состояние Git

- Репозиторий: https://github.com/MaxSC2/FixPoint
- Рабочая ветка: qa/browser-visual-capture
- PR: [#21 — UI refinement](https://github.com/MaxSC2/FixPoint/pull/21), target develop, Draft, открыт, НЕ влит.
- Исходное задание: [assignment/Модуль 4.docx](../assignment/%D0%9C%D0%BE%D0%B4%D1%83%D0%BB%D1%8C%204.docx).
- Карта требований: [docs/02-MODULE-4-MAPPING.md](02-MODULE-4-MAPPING.md).
- Шпаргалка с темами уроков, всеми 31 ссылками и ответами: [docs/27-MODULE-4-DEFENCE-NOTES.md](27-MODULE-4-DEFENCE-NOTES.md).

## 🚦 Build watch — актуальные и исторические сборки

### Текущий проверенный кодовый HEAD

| Commit | Workflow | Run | Статус | Подтверждённый результат |
|---|---|---:|---|---|
| 3df9ef2 | CI | [43746104616](https://github.com/MaxSC2/FixPoint/actions/runs/43746104616) | ✅ PASS | PHP 8.3 + MySQL 8: 174 passed, 0 failed, 1650 assertions; HTTP smoke: 30 checks; Docker Compose runtime: success |
| 3df9ef2 | Browser Visual QA | [43746104494](https://github.com/MaxSC2/FixPoint/actions/runs/43746104494) | ✅ PASS | 89 captures; 0 HTTP/navigation errors; 0 horizontal overflow; 0 axe violation groups/nodes; 0 browser runtime errors |
| 3df9ef2 | Module 4 source audit | [43746104510](https://github.com/MaxSC2/FixPoint/actions/runs/43746104510) | ✅ PASS WITH BLOCKED CAPTIONS | 56 DOCX paragraphs, 31 unique embedded links, all 31 video subtitle requests blocked by YouTube anti-bot/auth requirement |

### Исторические красные прогоны — исправлены, не удалять из истории

| Commit | Workflow | Run | Статус | Первопричина / исправление |
|---|---|---:|---|---|
| df1de17 / 77b4feb | CI | [43742186611](https://github.com/MaxSC2/FixPoint/actions/runs/43742186611), [43742112309](https://github.com/MaxSC2/FixPoint/actions/runs/43742112309) | ❌ FAIL | Миграция 007 использовала LEAD как неэкранированное имя колонки. MySQL 8 резервирует LEAD. Исправлено quoting в 26e53b8 |
| df1de17 / 77b4feb | Browser Visual QA | [43742186607](https://github.com/MaxSC2/FixPoint/actions/runs/43742186607), [43742112334](https://github.com/MaxSC2/FixPoint/actions/runs/43742112334) | ❌ FAIL | Миграция 007 падала до browser capture. Исправление подтверждено последующим успешным QA |
| 26e53b8 | CI | [43745382609](https://github.com/MaxSC2/FixPoint/actions/runs/43745382609) | ❌ FAIL | Миграции и seed прошли; 173 теста прошли, один contract-тест падал из-за нового аргумента PageController. Тест обновлён; 3df9ef2 прошёл |
| 26e53b8 | Browser Visual QA | [43745382603](https://github.com/MaxSC2/FixPoint/actions/runs/43745382603) | ✅ PASS | 89 снимков; результат подтверждён повторным прогоном 3df9ef2 |
| старые workflow revisions | Browser QA / source audit | [43739898996](https://github.com/MaxSC2/FixPoint/actions/runs/43739898996), [43739893384](https://github.com/MaxSC2/FixPoint/actions/runs/43739893384), [43740097150](https://github.com/MaxSC2/FixPoint/actions/runs/43740097150) | ❌ SETUP FAIL | Ранние ошибки скрипта визуального отчёта и конфигурации source-audit workflow; исправлены последующими revision |

**Правило:** новый push может отменить предыдущие runs. CANCELLED/SUPERSEDED не считается ни ошибкой кода, ни успешным тестированием нового HEAD.

## Статус субтитров — отдельный блокер подготовки к защите

- Найдено 31 уникальное встроенное видео из оригинального DOCX.
- yt-dlp был запущен для всех 31 видео.
- Все 31 запроса получили от YouTube требование авторизации/проверку «not a bot».
- Итог: субтитры НЕ ПРОВЕРЕНЫ. Это не означает, что дорожек нет.
- Не хранить в репозитории cookies, токены или данные входа. Для получения текста использовать обычный интерфейс YouTube и встроенную команду «Показать текст видео», если доступна, либо конспектировать видео вручную.
- Три видео задания №9 не имеют названий в DOCX; темы нельзя выдумывать.

## Что требует лабораторная №4

1. Каркас сайта по теме проекта.
2. Создание БД и таблиц.
3. Динамический вывод информации.
4. Административный блок.
5. Минимум две дополнительные возможности.
6. Задания 1, 3, 5, 7 и 9 также содержат списки уроков/альтернатив для просмотра. Полная карта находится в docs/02 и docs/27.

## План до четверга

### Понедельник, 16 ноября — mobile UX и функциональность
- [x] Извлечён точный текст DOCX и все 31 уникальные ссылки.
- [x] Составлена точная карта требований и шпаргалка защиты.
- [x] Мобильные и desktop-скриншоты проверены: 360/390, 768, 1024, 1440 px.
- [x] CI: 174/174 теста пройдены; Docker и smoke прошли.
- [x] Browser QA: 89 снимков, ноль автоматических критических находок.
- [ ] Просмотреть три видео задания №9 и зафиксировать темы.
- [ ] Провести ручной проход реальных сценариев по каждой роли на телефоне.

### Вторник, 17 ноября — функции и desktop
- [ ] Повторно сопоставить каждый пункт DOCX с маршрутом, БД и тестом.
- [ ] Проверить сквозную заявку: клиент → администратор → мастер → согласование → история.
- [ ] Проверить CRUD пользователей, услуг и категорий, а также редактор «О сервисе».
- [ ] Проверить desktop 1440/1280/1024 px: публичные страницы, клиент, мастер, администратор, формы и таблицы.
- [ ] Если обнаружен дефект: снимок → описание → код → тест → свежий снимок.

### Среда, 18 ноября — регрессия и защита
- [ ] Повторить PHP lint, unit/integration tests, MySQL CI, Docker Compose runtime.
- [ ] Повторить Browser QA и проверить реальные снимки свежего HEAD.
- [ ] Проверить RBAC, CSRF, владельца заявки, статусы, комментарии и скрытие внутренних заметок.
- [ ] Подготовить демонстрацию БД и ответы по архитектуре.
- [ ] Проверить инструкции запуска и тестовые учётные записи.

### Четверг, 19 ноября — финальный контроль
- [ ] Последний commit и обязательные GitHub checks зелёные.
- [ ] Не осталось блокирующих ошибок.
- [ ] Финальные снимки, инструкции запуска и шпаргалка защиты готовы.
- [ ] PR остаётся Draft/не влит, пока владелец отдельно не разрешит merge.

## Готово в текущем коде

- Публичные страницы, каталог и динамические данные из MySQL.
- Регистрация/вход и роли customer/technician/admin.
- Клиентские заявки, фильтры, сортировка, пагинация и карточка заявки.
- Назначение мастера, диагностика, смета и согласование.
- История статусов и комментарии с защитой внутренних заметок.
- Административное управление пользователями, услугами, категориями, заявками и статистикой.
- Редактор публичного блока «О сервисе».
- Поиск бренда устройства и ручной ввод редкого бренда.
- Мобильная навигация клиента.
- Адаптивные снимки для основных ролей и страниц.

## Чего не заявлять как завершённое

Фото-вложения и приватное файловое хранилище, чат в реальном времени, push, профиль компетенций мастеров, автоматический подбор, AI-триаж, официальные SVG-логотипы брендов и публичный deployment не входят в подтверждённый текущий результат. Не обещать эти функции на защите без реализации и тестов.

## Definition of Done

1. Все требования DOCX сопоставлены с функциями и доказательствами.
2. Основные сценарии проходят end-to-end на реальной MySQL.
3. Ролевые и негативные проверки проходят.
4. UI проверен на телефонах и desktop; свежие снимки сохранены.
5. CI, Docker и Browser QA зелёные на актуальном коде.
6. Ограничения и статус субтитров названы честно.

## Источники

- [Карта Module 4](02-MODULE-4-MAPPING.md)
- [Шпаргалка защиты](27-MODULE-4-DEFENCE-NOTES.md)
- [Техническое задание](01-TECHNICAL-SPECIFICATION.md)
- [Критерии приёмки](08-ACCEPTANCE-CRITERIA.md)
- [Правила UI](11-UI-SPECIFICATION.md)
- [GitHub Actions](https://github.com/MaxSC2/FixPoint/actions)


## Обновление QA — 15 ноября, commit 24273ba

### Текущий подтверждённый HEAD

| Commit | Проверка | Run | Результат |
|---|---|---|---|
| 24273ba | CI | [43747926200](https://github.com/MaxSC2/FixPoint/actions/runs/43747926200) | ✅ PASS: 174 tests, 0 failures, 1650 assertions; 30 HTTP smoke checks; Docker Compose runtime passed |
| 24273ba | Browser Visual QA | [43747926168](https://github.com/MaxSC2/FixPoint/actions/runs/43747926168) | ✅ PASS: 112 captures, 0 HTTP errors, 0 horizontal overflows, 0 accessibility violations, 0 browser errors, 0 fatal errors |
| 24273ba | Module 4 source audit | [43747926189](https://github.com/MaxSC2/FixPoint/actions/runs/43747926189) | ✅ PASS: 56 paragraphs, 31 unique links; captions remain unverified because YouTube blocked all 31 automated requests |

Browser matrix расширена: viewport 360 px теперь проверяет все публичные маршруты и все маршруты customer, technician и admin, а не только основные страницы.

### Дополнительный найденный и исправленный дефект

| Commit | Run | Статус | Что обнаружено |
|---|---|---|---|
| 74c4db1 | [43747266873](https://github.com/MaxSC2/FixPoint/actions/runs/43747266873) | ❌ FAIL — исправлено в 24273ba | Новая проверка всех страниц при ширине 360 px выявила горизонтальное переполнение в трёх административных формах из-за длинных хлебных крошек: до 385 px на форме пользователя и 362 px на категории. |

Исправление: мобильные хлебные крошки в заголовке административной рабочей области теперь переносятся по словам/длинным фрагментам; внутренний контейнер заголовка ограничен шириной viewport. Исправление подтверждено новым полным прогоном на 24273ba.

### Что ещё важно

- На клиентской мобильной версии скрывается дополнительная горизонтальная навигация кабинета, потому что её функции дублирует фиксированная нижняя панель.
- 112 captures — автоматический охват скриншотами, не ручное подтверждение каждого пикселя. Перед сдачей нужен ручной проход ключевых пользовательских сценариев.
- Успешный source audit означает, что ссылки извлечены и проверены на попытку доступа; он НЕ подтверждает содержание видео или наличие субтитров.
- Субтитры 31 видео остаются непроверенными. Три видео задания №9 не имеют подписей в DOCX и требуют ручной идентификации через обычный YouTube UI.

### Текущая граница реализации

Пока не реализованы безопасные фото-вложения к заявке/комментариям, профили компетенций мастеров и автоматизированный подбор. Они требуют отдельного backend, миграций и тестов. Не считать их частью готового функционала только потому, что они описаны в продуктовой концепции.

## Mobile UX implementation slice — 15 November

- Customer request list now uses a two-column filter layout on small tablets and a single-column filter flow on narrow phones; search and submit controls receive full width.
- Request cards use tighter spacing and readable two-line problem previews on phones instead of clipping all text to a single line.
- Request creation layout becomes a single column, with the explanatory panel moved below the form; submit/cancel actions become full-width touch targets.
- Existing customer-only bottom navigation remains fixed with safe-area padding; the redundant in-page workspace navigation is hidden on mobile.
- CSS-only change, preserving routes, form field names, validation and database workflow. New visual and CI runs are required for the updated HEAD.

## Desktop QA queue after mobile sign-off

- Recheck 1440 / 1280 / 1024 px for the same public, customer, technician and admin route matrix.
- Inspect dense admin tables, assignment form, statistics charts, request details and the public service catalogue at desktop widths.
- Any visual defect must be tracked as screenshot → issue → fix → test → new screenshot. Do not mark a route reviewed from viewport metrics alone.

## Full mobile + desktop viewport pass — 15 November, commit 3283786

| Commit | Workflow | Run | Result |
|---|---|---|---|
| 3283786 | CI | [43750279819](https://github.com/MaxSC2/FixPoint/actions/runs/43750279819) | ✅ PASS: 174 tests, 0 failures, 1652 assertions; 30 HTTP smoke checks; Docker Compose runtime passed |
| 3283786 | Browser Visual QA | [43750280195](https://github.com/MaxSC2/FixPoint/actions/runs/43750280195) | ✅ PASS: 139 captures; 0 HTTP errors; 0 horizontal overflow; 0 accessibility violations; 0 browser errors; 0 fatal errors |
| 3283786 | Module 4 source audit | [43750279834](https://github.com/MaxSC2/FixPoint/actions/runs/43750279834) | ✅ PASS: 56 DOCX paragraphs and 31 unique links extracted; captions remain blocked/unverified |

The browser viewport matrix now includes 1440, 1280, 1024, 768, 390 and 360 px where applicable. The 1280 px desktop viewport is captured for every public and authenticated route. The 360 px minimum viewport covers all public and customer/technician/admin routes.

### Customer mobile workspace updates in this tested HEAD

- Request filters collapse to a single column on narrow phones; search and submit actions remain full-width.
- Request cards use compact spacing and a readable two-line problem summary rather than a one-line clipped preview.
- Request creation switches to one column, moves the explanatory panel below the form and gives primary actions full-width touch targets.
- Device type now has quick choices (laptop, smartphone, PC, tablet, monitor, printer, console, other) while retaining free-text input for anything unusual.
- Brand search and free-text fallback remain intact; the existing POST contract and database schema are unchanged.
- Customer bottom navigation remains separate from desktop workspace navigation and respects the device safe area.

**Current branch head at this pass:** `3283786814de0bd9efaa7122253bfb15c5d1eb66`. Browser screenshots artifact: `fixpoint-visual-review-57` (GitHub Actions run 43750280195, 14-day retention).

The next desktop pass is visual/manual review of the 1440/1280/1024 screenshots, especially admin tables, assignment, statistics, service catalog and request detail. Automated zero-overflow/axe results are not a substitute for inspecting layout hierarchy and content quality.
