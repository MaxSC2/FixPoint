# UI-спецификация FixPoint

## Направление дизайна

Текущая визуальная концепция UI-02 — **Repair Dossier / Modern Workshop**: тёплая нейтральная основа, инженерная графика, выразительная редакционная типографика, насыщенный синий для действий и приглушённый лаймовый сигнальный акцент. Сайт должен выглядеть как продуманный сервисный продукт, а не стандартный SaaS-шаблон.

Визуальный характер: точность, прозрачность, аккуратность, доверие. Не использовать декоративные метрики, фальшивые отзывы и обещания, которые не подтверждены данными.

## Актуальные design tokens

| Token | Value | Usage |
|---|---|---|
| --color-ink | #202D30 | Main text |
| --color-ink-soft | #34474A | Secondary text |
| --color-muted | #667674 | Supporting text |
| --color-primary | #315FC1 | Actions / links |
| --color-primary-dark | #254C9E | Hover |
| --color-primary-soft | #E8EDF8 | Soft action surface |
| --color-bg | #F3F1E9 | Page canvas |
| --color-surface | #FBFAF5 | Main surface |
| --color-surface-soft | #F7F5EE | Secondary surface |
| --color-border | #DCDED5 | Dividers |
| Signal accent | #D9ED74 | Restrained highlights |
| Workshop dark | #1D3036 | Process section / footer |
| Success | #397B59 | Positive status |
| Warning | #A66A31 | Waiting / approval |
| Danger | #A84F4F | Errors / rejected |
| --radius-card | 13px | Standard card |
| --radius-control | 7px | Inputs / buttons |
| --content-width | 1260px | Main content |

CSS tokens live in `public/assets/css/app.css`. The UI remains based on PHP Views, HTML, CSS and vanilla JavaScript.

## Public pages

- Home: editorial hero with original local SVG artwork, service index, repair sequence, repair-record explainer, populated service list when database data exists, and final CTA.
- Service catalogue: GET search and category filter, database-backed service cards, honest empty state, price disclaimer.
- Service detail: description, steps, estimate caveat and application CTA.
- About: explanation of the service process, without invented credentials.
- Contacts: only configured contact values; missing settings remain explicit placeholders.
- Login/registration: compact forms with validation and feedback.

## Role workspaces

### Customer
Dashboard, own request list, request creation, request details, timeline, diagnosis, estimate approval/rejection, visible comments and history. Emphasize current status, next required action and cost decision.

### Technician
Assigned queue, device/problem summary, diagnosis, estimate, ETA, permitted status actions and work history. Optimize for scanning and fewer navigation steps.

### Admin
Operational overview, requests, assignment, users, technicians, services, categories and statistics. Prefer clear dense tables, filters and sortable headings over decorative KPI cards. Metrics must be based on actual database values.

## Repair status semantics

The exact status codes and labels are governed by `docs/10-INTEGRATION-CONTRACT.md`. Color is never the only indicator; every badge includes a text label. Customer-facing views must not expose internal comments.

## Interaction and motion

- Mobile navigation supports mouse, keyboard, Escape and focus return.
- Content reveal uses progressive enhancement through IntersectionObserver.
- With JavaScript disabled or reduced motion enabled, content remains readable and available.
- Button, link and row hover states are subtle and provide clear feedback.
- Motion must never block form submission or status understanding.

## Responsive breakpoints

- >1100 px: desktop composition.
- 901–1100 px: narrower desktop / compact spacing.
- ≤900 px: stacked primary layouts and collapsible navigation.
- ≤640 px: mobile-first single column, full-width primary form actions.
- Test widths: 1440, 1280, 1024, 768, 390 and 360 px.

## Content integrity

No fake testimonials, phone numbers, addresses, awards, turnaround promises or live-looking demo KPIs. Prices must come from service data and be labelled as indicative where applicable. Any illustrative request record must be explicitly labelled as an example. Each list needs populated, empty and error states.
## Клиентское пространство UI-03

- Обзор: приветствие по имени, последние заявки и переход к созданию обращения. Никаких выдуманных счётчиков или фиктивных «активных ремонтов».
- Мои заявки: поиск, фильтр по статусу, сортировка, пагинация и честное пустое состояние.
- Создание заявки: устройство, производитель, модель, необязательная услуга, описание симптомов и контакт.
- Карточка ремонта: текущий статус, описание, диагностика, смета, решение клиента, хронология и публичные комментарии.

Формы отправляют POST с CSRF-токеном. Клиентский JavaScript отвечает только за визуальные улучшения. Доступ к заявкам и переходы статусов проверяются сервером.
## Рабочее пространство мастера и администратора UI-04

### Мастер
- Очередь назначенных обращений с поиском, статусом, приоритетом и сортировкой.
- Карточка устройства и контекст клиента.
- Форма диагностики, сметы и ориентировочной даты готовности.
- Обновление статуса только среди разрешённых backend-переходов.
- История событий, комментарии и внутренние заметки с явным отличием от сообщений клиента.

### Администратор
- Операционный обзор с KPI только из реальных значений базы данных.
- Реестр заявок с фильтрами, приоритетом, статусом, клиентом и назначенным мастером.
- Навигация к управлению пользователями, услугами, категориями и статистикой.

Мастерские действия остаются обычными POST + CSRF. UI не определяет матрицу статусов и не заменяет RBAC. Неизвестные значения отображаются безопасно, внутренние заметки никогда не попадают в клиентские представления.
