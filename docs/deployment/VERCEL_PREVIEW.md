# FixPoint: Vercel Preview Deployment

This branch is an isolated deployment track. It is based on the integrated application snapshot
`d9c25853579434633a3e384b3b4d2d9446467ff4` and must not be merged into `main` as part of
the hosting experiment.

## Runtime

- Vercel Container Runtime, using the repository-root `Dockerfile`.
- PHP 8.3 + Apache; Apache listens on port 80 and serves `public/`.
- Vercel terminates public HTTPS. Do not add Caddy to the Vercel container.
- The container filesystem is ephemeral. Do not use it for user uploads or durable sessions.
- MySQL must be an external, persistent MySQL 8 service reachable from the Vercel container.

## Required environment variables

Set these on the Vercel Preview environment only:

| Variable | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | The assigned HTTPS preview URL |
| `APP_TIMEZONE` | `Asia/Almaty` |
| `DB_HOST` | External MySQL hostname |
| `DB_PORT` | Provider's MySQL port |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Restricted application user |
| `DB_PASSWORD` | Secret from the database provider |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE` | `true` |
| `SESSION_SAMESITE` | `Lax` |
| `RUN_MIGRATIONS` | `false` |
| `SEED_DATABASE` | `false` |

Never put real credentials in this repository. Do not enable the local demo seeder on a public
deployment: it creates known demo accounts and refuses production by default.

## Database preparation

1. Provision a persistent MySQL 8 database outside the application container.
2. Create a dedicated application user with access only to the FixPoint database.
3. Apply the checked-in SQL migrations once, from a trusted migration runner using the same
   database credentials. Do not run schema migrations concurrently from each container start.
4. Confirm that `app_sessions` exists before enabling `SESSION_DRIVER=database`.
5. Keep database backups and retention at the database provider.

The migration `database/migrations/009_create_app_sessions.sql` adds server-side session storage.
The PHP session handler uses MySQL advisory locks to serialize requests for the same session ID.

## Deployment safety

- Do not connect this experiment to the repository's current production branch.
- Do not deploy the repository's `main` branch; it is not the integrated application.
- Deploy only the dedicated `deploy/vercel-preview-2026-11-19` branch as a Preview.
- Keep Vercel Production environment variables unset until a separate production decision.
- Do not run `database/seed.php --force` against a public or production database.
- Validate health endpoint, authentication, role boundaries, CSRF, session persistence across
  separate requests, and database behavior before sharing the preview URL.

## Known deployment dependency

The application is not independently deployable until an external MySQL provider is selected
and provisioned. Vercel does not supply the MySQL service required by this application. The
hosting branch can be prepared and linted without credentials, but a meaningful end-to-end
preview requires a reachable database.
