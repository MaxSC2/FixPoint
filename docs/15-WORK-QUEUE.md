# FixPoint Work Queue

Updated: 2026-11-12

## Repository workflow

- `main`: stable submission branch; no direct work.
- `develop`: integration target.
- `feature/001-backend-foundation`: coding agent, Issue #6.
- `feature/ui-01-design-system`: ChatGPT, Issue #7.

Both streams may work in parallel. They must not edit the same files. Integration happens through pull requests into `develop`; neither stream merges its own PR.

## Workstream A — Backend (coding agent)

Issue: https://github.com/MaxSC2/FixPoint/issues/6

Deliver a runnable PHP 8.3+/MySQL 8 foundation: bootstrap, router, environment config, PDO, migrations, seed, Docker Compose, security primitives, smoke tests, CI, and clean-clone instructions.

Read first: `AGENT_HANDOFF.md`, `TEAM_WORK_SPLIT.md`, `docs/10-INTEGRATION-CONTRACT.md`, `docs/12-AGENT-TASK-001-BACKEND-FOUNDATION.md`.

Do not modify views, assets, design docs, or the integration contract without prior agreement.

## Workstream B — UI and product architecture (ChatGPT)

Issue: https://github.com/MaxSC2/FixPoint/issues/7

Deliver the design system, reference inventory, asset guide, responsive application shell, public pages, template contracts, and visual QA.

Owned paths:
- `app/Views/**`
- `public/assets/**`
- `design/**`
- `docs/11-UI-SPECIFICATION.md`
- `docs/13-CHATGPT-IMPLEMENTATION-MAP.md`

Do not modify controllers, services, repositories, security, database, Docker, or CI.

## Shared contract

The API/route and view-data contract is `docs/10-INTEGRATION-CONTRACT.md`. If implementation exposes a mismatch, raise it in the relevant issue before changing the contract.

## Review gates

Every PR must include:
1. Scope and changed-file list.
2. Exact commands executed.
3. Test results, not just a statement that tests pass.
4. Screenshots for visible UI changes.
5. Known gaps and deferred work.
6. Confirmation that secrets and local environment files were not committed.

No feature is considered complete merely because its page renders. Mutations must be validated and persisted by the backend.
