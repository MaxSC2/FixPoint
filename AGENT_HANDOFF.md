# Передача задачи coding agent — FixPoint

Репозиторий: https://github.com/MaxSC2/FixPoint

Работай как backend-разработчик FixPoint. Сначала прочитай:
1. `README.md`
2. `TEAM_WORK_SPLIT.md`
3. `docs/00-PROJECT-PASSPORT.md`
4. `docs/01-TECHNICAL-SPECIFICATION.md`
5. `docs/04-DATABASE-DESIGN.md`
6. `docs/05-ARCHITECTURE.md`
7. `docs/09-DEVELOPMENT-RULES.md`
8. `docs/10-INTEGRATION-CONTRACT.md`
9. `docs/12-AGENT-TASK-001-BACKEND-FOUNDATION.md`

## Текущая задача

**TASK-001: Backend foundation.** Создай чистый запускаемый PHP 8.3 + MySQL 8 каркас, PDO, env config, router/front controller, общие helpers, миграции и seed, Docker Compose, smoke tests и пошаговый README.

## Git-процесс

- Работай в `feature/001-backend-foundation`, созданной от `develop`.
- PR направляй в `develop`.
- Не меняй `main` и `develop` напрямую.
- Не открывай PR до прохождения локальных проверок и подготовки фактического отчёта.

## Жёсткие границы

- Не редактируй `app/Views/**`, `public/assets/**`, `prototype/**`.
- Не переписывай требования и интеграционный контракт без отдельного согласования.
- Не копируй исходники SkinTrade. FixPoint — новый проект.
- Не делай фальшивых обработчиков и заглушек «успех».
- Не добавляй крупные framework/SPA без обоснования.
- Не коммить секреты и `.env`.
- Не используй прямой SQL в контроллерах.
- Не обновляй статус заявки напрямую из контроллера.

## Ожидаемый отчёт

1. Ветка и commit SHA.
2. Изменённые/созданные файлы.
3. Команды запуска и тестирования.
4. Фактический вывод проверок.
5. Что намеренно не реализовано.
6. Открытые вопросы.
7. Подтверждение, что frontend-owned paths не изменялись.

Не начинай TASK-002 (авторизация) до ревью и принятия TASK-001. Карточка TASK-001 создана в GitHub Issues.