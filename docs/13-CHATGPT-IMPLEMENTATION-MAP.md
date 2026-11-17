# Карта работ ChatGPT — FixPoint

**Владелец:** ChatGPT (архитектура, UI/UX, frontend, интеграционный QA)  
**Основной контракт:** `docs/10-INTEGRATION-CONTRACT.md`  
**Границы:** `TEAM_WORK_SPLIT.md`

## Цель

Построить полноценный интерфейс сервисного центра, работающий с PHP backend и MySQL, а не отдельный статический макет. Формы должны отправлять реальные запросы, показывать серверные ошибки и состояния загрузки; никаких фиктивных «успешно сохранено».

## Моя зона владения

- `app/Views/layouts/` — общий layout, навигация, footer, flash, breadcrumbs.
- `app/Views/components/` — кнопки, поля, статусы, карточки, таблицы, модальные окна, пагинация.
- `app/Views/pages/public/` — главная, каталог услуг, контакты, о сервисе.
- `app/Views/pages/auth/` — вход и регистрация.
- `app/Views/pages/client/` — dashboard, новая заявка, список/детали заявок, профиль.
- `app/Views/pages/technician/` — очередь, ремонт, диагностика, смета, смена статуса.
- `app/Views/pages/admin/` — dashboard, заявки, пользователи/мастера, услуги/категории.
- `public/assets/css/` — tokens, base, layout, components, pages, responsive.
- `public/assets/js/` — фильтры, подтверждения, раскрытия и UX-валидация.
- `docs/` — UX, интеграционные дополнения, инструкции и QA-отчёты.

## План

### UI-01 — Foundation и дизайн-система
Проверить прототип, согласовать токены; создать общий layout, header/footer, контейнеры, сетку, типографику, кнопки, формы, статусы, alerts и карточки. Адаптивность: desktop/tablet/mobile, доступный keyboard focus. **DoD:** компоненты повторно используемы, горизонтального скролла на 360 px нет.

### UI-02 — Публичная часть
Главная с CTA, каталог услуг с категориями/поиском/ориентировочными ценами, контакты и «О сервисе». **DoD:** данные каталога приходят из backend, есть корректные пустые и ошибочные состояния.

### UI-03 — Авторизация и путь клиента
Формы login/register, dashboard, создание заявки, список/детали, timeline, комментарии, согласование сметы. **DoD:** сценарий от регистрации до выдачи устройства проходит полностью, данные сохраняются после перезагрузки.

### UI-04 — Рабочее место мастера
Очередь, фильтры, детали, диагностика, оценка стоимости/срока, смена разрешённых статусов. **DoD:** действия соответствуют серверным переходам, ошибки не скрываются.

### UI-05 — Административная панель
KPI, заявки, фильтры, пользователи/мастера, CRUD услуг/категорий, подтверждения опасных операций и пагинация. **DoD:** только реальные серверные операции.

### UI-06 — Интеграция и QA
Проверить полный сценарий клиент → администратор → мастер → клиент; ответы 401/403/404/422/500, CSRF, пустые данные, длинные строки и мобильную ширину. **DoD:** нет фиктивных production-данных и критичных UX-блокеров.

## Что не делаю

- Не пишу SQL и не подключаю БД из браузерного JavaScript.
- Не считаю frontend-механизм безопасности заменой серверной авторизации.
- Не меняю backend-контроллеры, router и миграции.
- Не заявляю интеграцию завершённой до проверки реального backend endpoint.

## Первые действия

1. Репозиторий создан и проверен: public, default branch `main`.
2. Стартовый пакет и исходное задание закрепляются в репозитории; `develop` — интеграционная ветка.
3. Следующий frontend deliverable — `feature/ui-01-design-system`: структура шаблонов, общий layout и базовые компоненты.
4. Production views не должны притворяться подключёнными к backend; контрактные fixtures допустимы только в прототипах и тестах.
5. Новые маршруты и view variables согласовываются через integration contract и GitHub Issue.

## Implementation status — 2026-11-12

### UI-01 in progress
Working branch: feature/ui-01-design-system  
Draft PR: https://github.com/MaxSC2/FixPoint/pull/8  
Issue: https://github.com/MaxSC2/FixPoint/issues/7

Implemented in this branch:
- Shared PHP page header/footer and flash component.
- Service card and repair status badge.
- Public home, services catalogue, service detail, about and contacts views.
- Responsive CSS design system and progressive-enhancement mobile navigation.
- View template contract and UI QA checklist.
- Reference library policy and moodboard brief.

Not yet complete:
- PNG/JPG reference pack is still pending; no binary reference images are committed.
- PHP lint has not been executed against a clean checkout.
- Desktop/mobile browser screenshots and rendered visual QA are pending.
- Backend ViewRenderer integration awaits TASK-001.

Do not close UI-01 until those checks are completed and the PR is reviewed.

## Implementation status — 2026-11-13

### Integrated into develop
- **UI-01 / UI-02:** shared shell, Repair Dossier design direction, public pages and local SVG hero illustration were merged with PR #11.
- **Auth UI:** login and registration views use POST + CSRF, server-provided validation state, safe old input and password visibility enhancement.
- **Customer UI:** dashboard, request creation, scoped list, detail, history, visible comments and estimate decision UI are present in develop after PR #13 / TASK-003 integration.
- **Admin UI:** dashboard, request registry, assignment form, user/service/category views and database-backed statistics are present in develop.
- **Technician UI templates:** queue and request detail templates are present. The final technician workflow is still dependent on the corresponding backend routes and service contract; do not treat a rendered view as proof that those actions are integrated.

### UI-06 — QA and form resilience
Working branch: `feature/ui-06-form-resilience`

This branch fixes two real validation-retry problems:
- Customer comment text is restored after a validation redirect and remains HTML-escaped.
- Technician diagnosis text, estimate and estimated-ready timestamp prefer submitted old input over previously persisted values after validation errors.

Regression coverage renders both views through the real ViewRenderer with invalid submissions and verifies safe output. Customer-facing comments remain filtered to exclude internal notes.

### Remaining gates
- Confirm TASK-004 technician routes and mutations once the backend implementation is available in the integration branch.
- Perform real browser visual QA at desktop, tablet and mobile widths. CI render tests and HTTP smoke tests do not replace visual inspection.
- Capture review screenshots and attach them to the UI integration PR before considering UI-06 complete.

