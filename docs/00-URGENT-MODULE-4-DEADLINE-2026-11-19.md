# 🚨 MODULE 4 — срочный план сдачи FixPoint

> [!CAUTION]
> **<u>СРОК СДАЧИ: ЧЕТВЕРГ, 19 НОЯБРЯ 2026 ГОДА</u>**
>
> Это главный контрольный документ. Красные исторические сборки оставлены в таблице намеренно; зелёный статус относится только к указанному commit SHA.


## 🔴 Самый свежий подтверждённый HEAD — 16 ноября 2026

**Проверенный commit:** `bb27989ecf08992efa4002850f313361355f5a05`  
**Ветка:** `qa/browser-visual-capture`  
**PR #21:** Draft, открыт, не влит.

| Workflow | Run | Статус | Результат |
|---|---|---|---|
| CI | [43758735045](https://github.com/MaxSC2/FixPoint/actions/runs/43758735045) | ✅ PASS | 175 тестов, 0 ошибок, 1711 assertions; 30 HTTP smoke checks; Docker Compose runtime passed |
| Browser Visual QA | [43758734990](https://github.com/MaxSC2/FixPoint/actions/runs/43758734990) | ✅ PASS | 139 скриншотов; 0 HTTP errors; 0 horizontal overflow; 0 axe violations; 0 browser errors; 0 fatal errors |
| Module 4 source audit | [43758735109](https://github.com/MaxSC2/FixPoint/actions/runs/43758735109) | ✅ PASS WITH CAPTION LIMITATION | 56 абзацев DOCX, 31 уникальная ссылка/видео; автоматический доступ к субтитрам заблокирован YouTube, их наличие не подтверждено |

### Ручной просмотр свежего screenshot archive

Артефакт `fixpoint-visual-review-68` из Browser QA, срок хранения GitHub Actions — 14 дней. Дополнительно вручную открыты:
- `admin__admin-requests__compact-1024.png` — таблица заявок и все ссылки «Назначить» видимы, ширина не ломается.
- `admin__admin-requests-1-assign__compact-1024.png` — форма назначения и карточка заявки читаемы, действия доступны.
- `admin__admin-services__compact-1024.png` — CRUD-таблица и форма услуги расположены последовательно, без наложения.
- `admin__admin-services__small-mobile-360__viewport.png` — мобильная форма перестроена в одну колонку.
- `customer-kovalev__requests__small-mobile-360__viewport.png` — мобильная панель клиента, фильтры и нижняя навигация не перекрывают ширину экрана.

Это ручная проверка выбранных representative screenshots, а не заявление о ручном просмотре каждого из 139 кадров.

### Обновлённые пункты плана

- [x] На актуальном HEAD повторно пройдены CI, Docker runtime, Browser QA и source audit.
- [x] Ручной выборочный просмотр свежего архива: админский реестр, назначение, CRUD услуг, мобильный список заявок и мобильная форма.
- [ ] Остался ручной функциональный проход в браузере под реальными тестовыми ролями: создать заявку, назначить, внести диагностику, согласовать/отклонить смету, проверить ограничения чужой заявки и внутренних комментариев.
- [x] Три ролика задания №9 идентифицированы по транскриптам: редактирование текстов страниц, защита админки, перенос БД на хостинг (docs/32).

**Не путать:** успешный source-audit подтверждает извлечение ссылок и выполнение проверки, но не содержание видео. Успешный Browser QA подтверждает автоматизированный Chromium-проход, а не физический тест на телефоне.

---

## Текущее состояние Git

- Репозиторий: https://github.com/MaxSC2/FixPoint
- Рабочая ветка: qa/browser-visual-capture
- PR: [#21 — UI refinement](https://github.com/MaxSC2/FixPoint/pull/21), target develop, Draft, открыт, НЕ влит.
- Исходное задание: [assignment/Модуль 4.docx](../assignment/%D0%9C%D0%BE%D0%B4%D1%83%D0%BB%D1%8C%204.docx).
- Карта требований: [docs/02-MODULE-4-MAPPING.md](02-MODULE-4-MAPPING.md).
- Шпаргалка с темами уроков, всеми 31 ссылками и ответами: [docs/27-MODULE-4-DEFENCE-NOTES.md](27-MODULE-4-DEFENCE-NOTES.md).
- Пошаговый сценарий ручной демонстрации и негативных проверок: [docs/28-MANUAL-DEFENCE-RUNBOOK.md](28-MANUAL-DEFENCE-RUNBOOK.md).

## 🚦 Build watch — актуальные и исторические сборки

### Текущий проверенный кодовый HEAD

| Commit | Workflow | Run | Статус | Подтверждённый результат |
|---|---|---:|---|---|
| 94a9090 | CI | [43758348983](https://github.com/MaxSC2/FixPoint/actions/runs/43758348983) | ✅ PASS | PHP 8.3 + MySQL 8: 175 passed, 0 failed, 1711 assertions; HTTP smoke: 30 checks; Docker Compose runtime: success |
| 94a9090 | Browser Visual QA | [43758348984](https://github.com/MaxSC2/FixPoint/actions/runs/43758348984) | ✅ PASS | 139 captures; 0 HTTP errors; 0 horizontal overflow; 0 axe violations; 0 browser errors; 0 fatal errors |
| 94a9090 | Module 4 source audit | [43758348939](https://github.com/MaxSC2/FixPoint/actions/runs/43758348939) | ✅ PASS WITH BLOCKED CAPTIONS | 56 DOCX paragraphs, 31 unique embedded links; all 31 subtitle probes blocked by YouTube anti-bot/auth. Captions remain unverified, not absent |

### Исторические красные прогоны — исправлены, не удалять из истории

| Commit | Workflow | Run | Статус | Первопричина / исправление |
|---|---|---:|---|---|
| df1de17 / 77b4feb | CI | [43742186611](https://github.com/MaxSC2/FixPoint/actions/runs/43742186611), [43742112309](https://github.com/MaxSC2/FixPoint/actions/runs/43742112309) | ❌ FAIL | Миграция 007 использовала LEAD как неэкранированное имя колонки. MySQL 8 резервирует LEAD. Исправлено quoting в 26e53b8 |
| df1de17 / 77b4feb | Browser Visual QA | [43742186607](https://github.com/MaxSC2/FixPoint/actions/runs/43742186607), [43742112334](https://github.com/MaxSC2/FixPoint/actions/runs/43742112334) | ❌ FAIL | Миграция 007 падала до browser capture. Исправление подтверждено последующим успешным QA |
| 26e53b8 | CI | [43745382609](https://github.com/MaxSC2/FixPoint/actions/runs/43745382609) | ❌ FAIL | Миграции и seed прошли; 173 теста прошли, один contract-тест падал из-за нового аргумента PageController. Тест обновлён; 3df9ef2 прошёл |
| 26e53b8 | Browser Visual QA | [43745382603](https://github.com/MaxSC2/FixPoint/actions/runs/43745382603) | ✅ PASS | 89 снимков; результат подтверждён повторным прогоном 3df9ef2 |
| старые workflow revisions | Browser QA / source audit | [43739898996](https://github.com/MaxSC2/FixPoint/actions/runs/43739898996), [43739893384](https://github.com/MaxSC2/FixPoint/actions/runs/43739893384), [43740097150](https://github.com/MaxSC2/FixPoint/actions/runs/43740097150) | ❌ SETUP FAIL | Ранние ошибки скрипта визуального отчёта и конфигурации source-audit workflow; исправлены последующими revision |

**Правило:** новый push может отменить предыдущие runs. CANCELLED/SUPERSEDED не считается ни ошибкой кода, ни успешным тестированием нового HEAD.

## Статус субтитров — блокер снят 17.11.2026

- В DOCX 32 ссылки, из них **31 уникальное** видео (урок №1 повторяется в заданиях 1 и 3).
- Субтитры скачаны для всех 31 видео: **31 × `ru.vtt`**, всего **39 001 слово**, ≈ **7 ч 36 мин**.
- Лежат в `assignment/subtitles/`: `README.md` (индекс по заданиям), `full-transcript.md` (всё одним файлом), `*.ru.txt` (читаемый текст), `*.ru.vtt` (с таймкодами).
- Три видео задания №9 без названий в DOCX расшифрованы: редактирование текстов страниц, защита админки Basic Auth, финальный урок про перенос БД на хостинг.
- Подробный разбор и таблица соответствия требованиям курса — `docs/32-ASSIGNMENT-VIDEOS-TRANSCRIPTS-2026-11-17.md`.
- Ограничение: текст машинный, термины и латиница искажаются; дословная цитата требует сверки с видео. Cookies и токены в репозиторий не попадали.

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
- [x] Просмотреть три видео задания №9 и зафиксировать темы (по транскриптам, docs/32).
- [ ] Провести ручной проход реальных сценариев по каждой роли на телефоне.

### Вторник, 17 ноября — функции и desktop
- [x] Повторно сопоставить каждый пункт DOCX с маршрутом, БД и тестом; карту и шпаргалку сверить с исходным DOCX.
- [x] Сквозная заявка проверена интеграционным тестом: клиент → администратор → мастер → согласование → завершение; проверены все 7 событий истории.
- [x] CRUD пользователей, услуг и категорий проверен интеграционными тестами; редактор «О сервисе» покрыт отдельными HTTP-тестами.
- [x] Desktop 1440/1280/1024 px: все публичные и ролевые маршруты сняты; вручную просмотрены главная, клиентские заявки/форма, реестр и назначение, статистика, CRUD и карточка мастера. Визуально подтверждено по архиву `fixpoint-visual-review-66`.
- [x] Дефекты компактного desktop-реестра и CRUD-таблиц исправлены по схеме снимок → код → тест → свежий снимок.

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

## Latest verified HEAD — 16 November 2026, commit c4509cb

The exact code HEAD is `c4509cbf2479b1f3ba7308c3f8e9b4a4fc0fc957`. All three required workflows passed on this same commit:

- CI: 175 tests, 0 failures, 1711 assertions; 30 HTTP smoke checks; Docker Compose runtime passed.
- Browser Visual QA: 139 screenshots across public, customer, technician and administrator routes; viewports 1440, 1280, 1024, 768, 390 and 360 px as applicable. Zero HTTP errors, horizontal overflow, axe violations, browser errors or fatal errors.
- Module 4 source audit: 56 DOCX paragraphs and 31 unique embedded video links extracted. YouTube blocked all 31 automated caption checks; no claim is made that subtitles are absent.

### Desktop defects found and fixed during screenshot review

- At 1024 px, the admin request registry clipped the “Назначить” action. The redundant open icon was removed for compact widths, then the date column was hidden at 761–1100 px to preserve the assignment action. The final 1024 px screenshot shows the complete action link.
- Admin CRUD lists for users, services and categories were squeezed beside the create form at 1024 px. They now use the full content width, with the create form below the list.
- CRUD table wrappers now have accessible region labels and keyboard focus.
- Latest screenshot archive: `fixpoint-visual-review-65`, GitHub Actions run [43752695221](https://github.com/MaxSC2/FixPoint/actions/runs/43752695221), retained for 14 days.

### End-to-end workflow proof

A new integration test `testCompleteRepairLifecycleAcrossCustomerAdminAndTechnician` covers customer creation, admin assignment, technician acceptance and diagnostics, estimate preparation, customer approval, repair completion and final customer view. It verifies all seven status-history transitions against the real MySQL test database.

### Remaining release blockers

- [ ] Manually identify and review the three unnamed videos from task 9. The DOCX supplies URLs only; YouTube blocks automated metadata/caption extraction.
- [ ] Manually demonstrate the full workflow in the browser using all three roles, including the negative authorization path.
- [ ] Final release-day regression on the final documentation/code HEAD.
- [ ] Confirm local startup instructions and test-account credentials from README/.env before demonstration.

## Revalidation on latest PR HEAD — 16 November 2026

- **Latest branch HEAD:** `94a909083a0734779a0cfaa7a97cfeed9ac726be` (documentation-only update on top of verified code commit `c4509cbf2479b1f3ba7308c3f8e9b4a4fc0fc957`).
- [CI #43758348983](https://github.com/MaxSC2/FixPoint/actions/runs/43758348983): **PASS** — 175 tests, 0 failures, 1711 assertions; 30 HTTP smoke checks; Docker Compose runtime passed.
- [Browser Visual QA #43758348984](https://github.com/MaxSC2/FixPoint/actions/runs/43758348984): **PASS** — 139 captures; zero HTTP errors, horizontal overflow, axe violations, browser errors or fatal errors.
- [Module 4 source audit #43758348939](https://github.com/MaxSC2/FixPoint/actions/runs/43758348939): **PASS** — 56 DOCX paragraphs, 31 unique video links; YouTube blocked all automated caption probes, so subtitle availability remains unverified.
- Screenshot archive: `fixpoint-visual-review-67`, run [43758348984](https://github.com/MaxSC2/FixPoint/actions/runs/43758348984), 14-day retention. The latest 1024px admin request registry was manually inspected: the complete “Назначить” action is visible. The 360px request form was also manually inspected.
- The end-to-end lifecycle test added in `c4509cb` verifies all seven status-history transitions across customer, administrator and technician roles using the MySQL integration test database.
- Commit `54381e2` had an intermediate Browser QA run cancelled by a newer push; it is **CANCELLED/SUPERSEDED**, not a code failure. The subsequent complete QA runs on `fbbe5e1` and `94a9090` passed.

**Current release boundary:** private photo uploads, technician capability profiles/matching, real-time chat, push, AI triage, official SVG brand logos and public deployment remain unimplemented. Do not claim otherwise in the submission.

---

## Актуализация 17 ноября 2026 — единая точка handoff

**Актуальный проверенный кодовый HEAD до добавления этой записи:** `11c6d5a582b4b8648169231cac433f94459b3efc`.

- CI [#43905942977](https://github.com/MaxSC2/FixPoint/actions/runs/43905942977): PASS — lint 138 files; 175 tests passed, 0 failed, **1715 assertions**; 30 smoke checks; Docker Compose runtime passed.
- Browser Visual QA [#43905942899](https://github.com/MaxSC2/FixPoint/actions/runs/43905942899): PASS — 139 captures; zero HTTP errors, horizontal overflow, axe violations, browser errors and fatal errors.
- Module 4 source audit [#43905942915](https://github.com/MaxSC2/FixPoint/actions/runs/43905942915): 56 paragraphs, 31 unique links; YouTube blocked automated caption checks. Captions are UNVERIFIED, not absent.
- Screenshot artifact: `fixpoint-visual-review-73`, available from the Browser QA run above, 14-day retention.

The 17 November repo audit, ER diagram, current routes, exact schema, architecture, screenshot index, agent operating rules and P0 checklist are consolidated in [docs/29-AGENT-HANDOFF-FINAL-AUDIT-2026-11-17.md](29-AGENT-HANDOFF-FINAL-AUDIT-2026-11-17.md).

**Important documentation correction:** previous sections list earlier commits and 1711 assertions. They remain historical. The latest confirmed CI at 11c6d5a reports 1715 assertions. Re-run all workflows after any new code or documentation push and update this record only with results for the exact new HEAD.

The remaining release work is manual browser validation of the complete customer → admin → technician → customer workflow, negative access/CSRF/internal-note checks, final DB demonstration readiness, and final regression on one exact HEAD. Do not implement speculative photo upload/AI/matching features before the deadline unless a verified assignment requirement demands them.

---

## Финальный аудит 17 ноября 2026 - принятый HEAD и результаты

**Код-HEAD до этой документационной записи:** `979795b` (merge `origin/qa/browser-visual-capture` @ `6b36ee0` с аудит-фиксами `2d114ac`, `4a9bd94`, `4c8e74d`). Запись в docs меняет только документацию; код в этом коммите идентичен проверенному `979795b`.

### Что исправлено в ходе финального аудита

- **P0, тестовая инфраструктура:** в PR #21 `tests/Integration/MigrationTest.php` был заменён SQL-файлом без `<?php`, поэтому 4 теста миграций молча не выполнялись. Файл восстановлен, счётчик миграций поднят до 8, добавлен тест seeded `about`-блока. `tests/run.php` теперь падает, если заявленный test-класс не найден; `tests/lint.php` требует `<?php`.
- **P1, статистика:** локальная TASK-005D интегрирована вместо дублирующей реализации PR #21. Добавлены период `from`/`to`, явная ошибка `periodError`, заполнение пропусков по месяцам, топ услуг и 7 HTTP-тестов.
- **P0, UI:** компонент `status-badge.php` перезаписывал `$statusLabels` в области видимости карточки мастера, из-за чего подписи допустимых переходов и текущего статуса были пустыми. Переменные переименованы; добавлены регрессионные проверки видимых подписей.
- **P1, навигация:** пункт «Контент» отсутствовал на 9 admin-страницах. Навигация приведена к единому виду, добавлен тест консистентности на всех admin-маршрутах.
- **P1, каталог услуг (`/services`), 17.11.2026:** блок поиска и фильтра выглядел как три вложенные рамки (панель → поле → сам input), а на ширине ≤900 px разваливался: `flex: 1 1 420px` в колоночном режиме превращался в высоту 420 px, селект — 230 px. Убрана внешняя панель, сброшены стили вложенных input/select, `flex-basis` переведён в `auto`, добавлен `focus-within` на поле. Проверено в 360/390/768/1024/1440: высоты 46 px, горизонтального переполнения нет.

### Локальные проверки на проверенном HEAD

- `php tests/lint.php`: **138/138 файлов OK**.
- `php tests/run.php`: **186 passed, 0 failed, 1876 assertions** (MySQL 8.4.6).
- `php tests/smoke.php`: **30/30 проверок**.
- `php database/migrate.php --fresh`: применены все **8 миграций**.
- `php database/seed.php`: 6 пользователей, 4 заявки, 15 записей истории, 5 комментариев.

### Сквозной браузерный сценарий

Клиент `customer.kovalev@fixpoint.test` создал заявку `FP-2026-000081`; администратор назначил Ивана Петрова; мастер провёл `accepted → diagnostics → awaiting_customer_approval`, сохранил диагностику и смету, добавил внутреннюю и публичную заметки; клиент согласовал смету; мастер провёл `repairing → ready_for_pickup → completed`. Итоговая карточка клиента показывает все 7 событий истории и статус «Завершена», внутренняя заметка клиенту не видна.

Негативные проверки: чужая заявка — 404; `/admin` и `/technician/requests` клиентом — 403; POST `/logout` без CSRF — 419; терминальная заявка не предлагает переходов.

Скриншоты после исправлений сняты на 360/390/768/1024/1280/1440 px: статистика, карточка мастера, финальная карточка клиента, админ-дашборд (`qa-final-*.png` в рабочем каталоге, не коммитятся).

### Известные ограничения, которые нельзя скрывать

- `final_cost` приложением не записывается ни в одном сценарии; в клиентской карточке итоговая стоимость остаётся «Не определена». Это осознанное ограничение MVP, а не UI-баг.
- Docker Compose локально не проверялся (Docker отсутствует); проверка выполняется в CI.
- AI-диагностика, auto-assign, real-time chat, push, загрузка фото и полный каталог моделей не реализованы.
- Merge PR #21 и синхронизация `main` требуют отдельного разрешения владельца.
- Субтитры 31 видео сняты и лежат в `assignment/subtitles/`; текст машинный, термины и латиница искажаются, дословная цитата требует сверки с видео.
- Расхождение с курсом (не P0): `meta description` в лейауте статический, а не из БД; из текстов страниц редактируется только «О нас».

