# FixPoint: публичное демо

Цель: одна HTTPS-ссылка для ПК и телефона. После запуска VPS ноутбук владельца можно выключить.

Версия приложения: c6860b8b61e4dcf299184b0902f2b519a8c1a860. Бизнес-логику и миграции не менять.

## Архитектура и безопасность

Интернет TCP 80/443 -> Caddy -> приватная Docker-сеть -> app / init / MySQL.
Наружу выставлены только 80/443 Caddy. Apache доступен только на 127.0.0.1:8080; MySQL не имеет опубликованного порта; Adminer отсутствует. DB_ROOT_PASSWORD получает только MySQL. SEED_PASSWORD получает только одноразовый init, а постоянный app не получает root- или seed-пароль.

## Oracle Cloud Always Free

Официальные условия: https://www.oracle.com/cloud/free/ и https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm.
Ampere A1: до 2 OCPU / 12 GB RAM, storage до 200 GB в home region. Это не SLA: возможны Out of host capacity и отзыв простаивающей VM. Карта требуется для проверки личности; возможен временный authorization hold. Не включать Pay As You Go. Создавать только ресурсы Always Free Eligible. При нехватке capacity остановиться.

## Подготовка владельцем

1. Создать аккаунт и выбрать home region.
2. Создать VM.Standard.A1.Flex, Ubuntu 24.04 ARM64, 2 OCPU / 12 GB, boot volume 50 GB. Проверить Always Free Eligible.
3. Создать VCN, Internet Gateway, public subnet и маршрут 0.0.0.0/0 к Internet Gateway.
4. Добавить SSH public key, private key хранить только локально, назначить public IPv4.
5. В NSG/UFW разрешить TCP 22 только со своего IP, 80/443 публично. Не открывать 3306, 8080, 8096.
6. Установить Docker Engine и Compose plugin: https://docs.docker.com/engine/install/ubuntu/ .
7. Создать DNS A-запись DuckDNS или своего домена на public IPv4.

## Развёртывание

Клонировать ветку feature/public-demo-oracle-2026-11-18, скопировать deploy/env.demo.example в .env, установить права 600, сгенерировать разные DB_PASSWORD и DB_ROOT_PASSWORD через openssl rand -hex 24, SEED_PASSWORD через openssl rand -hex 16. Заменить DEMO_DOMAIN, APP_URL и все CHANGE_ME.

Обязательно: APP_ENV=staging, APP_DEBUG=false, APP_PORT=127.0.0.1:8080, SESSION_SECURE=true. Запустить ./scripts/deploy.sh. Скрипт проверяет Compose, запускает MySQL, одноразовый init, app и Caddy, затем проверяет HTTPS /health. Он не выполняет git pull и не удаляет volumes.

## Приёмка и backup

С ПК и телефона проверить /, /services, /login, /health, TLS, три роли и полный цикл заявки. Убедиться, что снаружи доступны только 80/443 и в environment app нет DB_ROOT_PASSWORD/SEED_PASSWORD.

Бэкап: ./scripts/backup.sh (14 дней, права 600, SHA-256). Восстановление перезаписывает БД и требует ввода RESTORE: ./scripts/restore.sh /path/to/dump.sql.gz.

Учебное демо с фиктивными данными, не production для реальных клиентов. Не запускать docker compose down -v в штатном процессе.
