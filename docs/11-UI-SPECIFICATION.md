# UI-спецификация FixPoint

## Направление дизайна
Современный сервисный центр, спокойный технологичный интерфейс: светлый фон, тёмный текст, синий акцент, ясные статусы и заметные действия. Номер и статус заявки всегда видны; мобильная версия проектируется отдельно.

## Design tokens
| Token | Value | Usage |
|---|---|---|
| --color-ink | #172033 | Main text |
| --color-muted | #687386 | Secondary |
| --color-primary | #3157D5 | Actions/links |
| --color-primary-dark | #2444B3 | Hover |
| --color-bg | #F5F7FB | App background |
| --color-surface | #FFFFFF | Cards |
| --color-border | #E4E9F1 | Borders |
| --color-success | #16845B | Success |
| --color-warning | #B7791F | Waiting |
| --color-danger | #C2414B | Errors |
| --radius-card | 18px | Cards |
| --radius-control | 10px | Inputs/buttons |
| --content-width | 1180px | Public content |

Fonts: system stack Inter/ui-sans/system-ui; no CDN dependency.

## Reusable components
Header, footer, button variants (primary/secondary/ghost/danger), labeled input/textarea/select, field errors, status badge, card/stat-card, empty-state, flash alert, responsive table, breadcrumb, pagination, request timeline and confirmation dialog. All interactive controls need visible keyboard focus. Color is never the only status signal.

## Pages
### Public
Home (hero + CTA, benefits, popular services, process steps, contact info), catalogue (categories/search/service cards/estimated prices), service detail, about, contacts, login and registration.

### Client workspace
Dashboard, request list, create request form (device type/brand/model/problem/contact/optional service), request details with status progress, diagnosis, estimate approval, history and comments, profile.

### Technician workspace
Assigned request queue and filters, device/problem details, diagnosis form, estimate/ETA, permitted status transitions, history/comments.

### Admin
KPI from real DB values, request list/filter/technician assignment, CRUD users/services/categories, statistics from actual DB.

## Responsive breakpoints
- ≥1024 px: full nav, desktop work tables and side nav.
- 768–1023 px: compact grids, tables may become cards.
- <768 px: single column, mobile navigation, full-width actions, no horizontal scrolling.
- Minimum test width: 360px.

## Content integrity
No lorem ipsum or fake testimonials passed as real. Prices only from DB and shown as estimate where applicable. KPI from DB, mocked values explicitly labelled in prototype. No technician internal notes shown to clients. Do not promise exact completion dates before diagnosis. Every list has normal, empty and error states; loading state when JS-driven.