# FixPoint public-demo infrastructure

Это отдельная инфраструктурная подготовка для публичного демонстрационного развёртывания FixPoint на Oracle Cloud Always Free или совместимом VPS.

- Application base: `c6860b8b61e4dcf299184b0902f2b519a8c1a860`
- Production stack: Docker Compose + PHP 8.3/Apache + MySQL 8 + Caddy 2
- Public ingress: TCP 80/443 только через Caddy
- MySQL: только private Docker network; порт не опубликован
- App container: только allowlisted environment variables; DB root secret не передаётся приложению
- Data volume: `db_data`

See [`docs/19-DEMO-DEPLOYMENT.md`](docs/19-DEMO-DEPLOYMENT.md) for the full deployment runbook.

No cloud resources are created by this repository. Account verification, instance creation, firewall rules, DNS, and final external-device acceptance testing require the account owner.
