# FixPoint — система управления заявками сервисного центра

FixPoint — учебное веб-приложение для сервисного центра по ремонту компьютеров, ноутбуков, смартфонов и другой электроники. Цель проекта — полноценная система с PHP-backend, MySQL, ролями пользователей и сквозными сценариями, а не статический макет.

## Repository state

- Repository: https://github.com/MaxSC2/FixPoint (public).
- `main`: stable baseline.
- `develop`: integration branch.
- Original assignment: [assignment/Модуль 4.docx](assignment/Модуль%204.docx).
- Full handoff bundle: [FixPoint-handoff-pack.tar.gz](FixPoint-handoff-pack.tar.gz). Extract it into the repository root with `tar -xzf FixPoint-handoff-pack.tar.gz --strip-components=1`.
- Direct team/agent map: [TEAM_WORK_SPLIT.md](TEAM_WORK_SPLIT.md), [AGENT_HANDOFF.md](AGENT_HANDOFF.md), [TASK-001](docs/12-AGENT-TASK-001-BACKEND-FOUNDATION.md), [ChatGPT implementation map](docs/13-CHATGPT-IMPLEMENTATION-MAP.md), [repository operations](docs/14-REPOSITORY-OPERATIONS.md).

The archive contains the complete starter package: project passport, technical specification, Module 4 mapping, roles and flows, database design, architecture, team plan, roadmap, acceptance criteria, development rules, backend/frontend contract, UI specification, agent task, UI prototype, SQL draft, environment example and ignore rules.

## Scope

Roles: guest, client, technician and administrator.

Main modules: public site and service catalogue; authentication; client dashboard and repair requests; technician workspace; admin CRUD; status history and comments; search/filtering/pagination; validation and security; test and deployment setup.

## Stack

PHP 8.3+, MySQL 8, PDO, HTML5, CSS3 and JavaScript. Use server-rendered PHP views and Docker Compose. Avoid introducing large frameworks without a concrete need.

## Development principle

A feature is complete only when its end-to-end workflow is verified. No fake successful form handlers, no client-only security, and no hardcoded production data. Protected actions and access to repair tickets must be enforced on the server.

See the bundled documents before implementation. Production application code is not yet present; TASK-001 establishes the backend foundation.
