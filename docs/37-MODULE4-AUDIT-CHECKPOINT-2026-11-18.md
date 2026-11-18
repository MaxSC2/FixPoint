# FixPoint — контрольная точка коррекции аудита Module 4

**Дата:** 18.11.2026  
**Ветка:** `docs/module4-audit-corrections-2026-11-17`  
**Корректирующий коммит:** `23a8544` — `docs: correct Module 4 transcript audit findings`  
**База:** `84bd094` (`qa/browser-visual-capture`)  
**QA-ветка после fast-forward:** `23a8544`  
**PR #21:** OPEN, Draft, base `develop`, не влит. `main` и `develop` не изменялись.

## 1. Что вошло в корректирующий коммит

12 файлов в корректирующем коммите `23a8544` (этот `docs/37` добавлен отдельным checkpoint-коммитом):

| Файл | Назначение |
|---|---|
| `docs/32-ASSIGNMENT-VIDEOS-TRANSCRIPTS-2026-11-17.md` | Главный документ: требование DOCX ≠ тема урока; задание №9; мета-теги; Source Audit; права |
| `docs/02-MODULE-4-MAPPING.md` | Задание №9, статус видео/субтитров, чек-лист, запреты формулировок |
| `docs/00-URGENT-MODULE-4-DEADLINE-2026-11-19.md` | Секция коррекции, актуальный статус субтитров, ограничения |
| `docs/29-AGENT-HANDOFF-FINAL-AUDIT-2026-11-17.md` | Handoff: субтитры, валидатор, права |
| `docs/33-OWNER-UX-FEEDBACK-2026-11-17.md` | Перенос шести UX-замечаний; статусы 1–6 под `84bd094` |
| `docs/35-TRANSCRIPT-RIGHTS-2026-11-18.md` | Права/ToS, публичные файлы, варианты, рекомендация |
| `docs/36-EXECUTION-LOG-2026-11-17.md` | Журнал выполнения, команды и результаты |
| `docs/37-MODULE4-AUDIT-CHECKPOINT-2026-11-18.md` | Эта контрольная точка (отдельный checkpoint-коммит) |
| `scripts/assignment/validate_transcripts.py` | Валидатор локального пакета |
| `scripts/assignment/tests/test_validate_transcripts.py` | 9 unit-тестов |
| `scripts/assignment/extract_module4.py` | `--local-transcripts`, раздельный live/local отчёт, exit 1 при провале |
| `.github/workflows/module4-source-audit.yml` | Тесты, локальная проверка, path `assignment/subtitles/**` |
| `.gitignore` | Исключены Python-кэши, `artifacts/`, `.playwright-mcp/`, локальные `qa-*.png` |

DOCX, существующие файлы субтитров, код приложения, UI и миграции не изменялись.

## 2. Результаты валидатора

Локальный прогон:

```powershell
python -m unittest discover -s scripts/assignment/tests -v
# Ran 9 tests — OK

python scripts/assignment/validate_transcripts.py `
  --dir assignment/subtitles `
  --docx "assignment/Модуль 4.docx" `
  --json-out artifacts/module4/transcript-validation.json `
  --md-out artifacts/module4/transcript-validation.md
```

```
Transcript package: PASS — 31/31 VTT, 31/31 TXT, 15411 cues, 39627 words, 0 errors, 7 warnings
DOCX IDs: 31 unique; index match: yes
```

- 24 файла `pass`, 7 `warn`, 0 `fail`.
- 7 warnings — пустые cue автосубтитров (>15 % реплик); текст TXT присутствует.
- 39 627 слов TXT против 39 001 в `_index.json`; расхождение в допуске.
- Отчёты сохранены в `artifacts/module4/transcript-validation.{json,md}` (локальные артефакты, не коммитятся).

## 3. Source Audit — точное состояние

Run [44283729513](https://github.com/MaxSC2/FixPoint/actions/runs/44283729513), шаги:

| Шаг | Результат |
|---|---|
| Unit-тесты валидатора | ✅ success |
| Extract DOCX + live probe + local validation | ✅ success |
| Upload artifacts | ✅ success |

Лог:

```
Extracted 56 paragraphs, 31 unique links, 31 video links.
Local transcript package: PASS — 31/31 VTT, 31/31 TXT, IDs match DOCX: yes, structure errors: 0, warnings: 7.
External YouTube probe: 31 videos returned blocked_by_youtube. This describes the live endpoint only and does not describe the local transcript package.
```

Это два независимых факта:

1. **Live YouTube в CI:** 31 × `blocked_by_youtube` → unverified. Не «субтитров нет».
2. **Локальный пакет:** найден и структурно провалидирован.

Локальный прогон 18.11.2026, наоборот, получил `available=31` — статус live-эндпоинта зависит от IP/времени. Ручной просмотр 31 видео человеком не подтверждён и не заявляется.

## 4. GitHub Actions на `23a8544`

| Workflow | Run | Статус | Результат |
|---|---|---|---|
| CI | [44283729740](https://github.com/MaxSC2/FixPoint/actions/runs/44283729740) | ✅ PASS | Lint 138; 190 tests, 0 failed, 1954 assertions; smoke 30/30; Docker Compose runtime success |
| Browser Visual QA | [44283729544](https://github.com/MaxSC2/FixPoint/actions/runs/44283729544) | ✅ PASS | 159 captures; 0 HTTP errors; 0 horizontal overflow; 0 browser errors; 0 fatal errors; 0 accessibility violations; artifact `fixpoint-visual-review-97` |
| Module 4 source audit | [44283729513](https://github.com/MaxSC2/FixPoint/actions/runs/44283729513) | ✅ PASS | 56 абзацев DOCX; 31 видео; локальный пакет PASS; live probe blocked |

### 4.1. Проверка checkpoint-коммита `7ce3f50`

Тот же набор проверок на `7ce3f50` (добавлены только `docs/36`, `docs/37` и ссылка в `docs/00`):

| Workflow | Run | Статус | Результат |
|---|---|---|---|
| CI | [44285284398](https://github.com/MaxSC2/FixPoint/actions/runs/44285284398) | ✅ PASS | Lint 138; 190 tests, 0 failed, 1954 assertions; smoke 30/30 |
| Browser Visual QA | [44285284383](https://github.com/MaxSC2/FixPoint/actions/runs/44285284383) | ✅ PASS | 159 captures; 0 HTTP/overflow/browser/fatal/accessibility errors |
| Module 4 source audit | [44285284460](https://github.com/MaxSC2/FixPoint/actions/runs/44285284460) | ✅ PASS | Локальный пакет PASS 31/31; live probe 31 × `blocked_by_youtube` |

## 5. Статусы шести UX-замечаний

| № | Замечание | Статус на `84bd094` |
|---|---|---|
| 1 | Мобильная шапка, «Войти» по центру | ✅ исправлено (`cdf65d1`); зазор 7 px на 360/390, 12 px на 768/900 |
| 2 | Одинаковые иконки услуг | ❌ регресс P1: `service-card.php` читает `$service['category_slug']`, а `CatalogService::mapService()` хранит slug в `$service['category']['slug']`; все 10 карточек `--general` |
| 3 | Карточка реагирует только на «Подробнее» | ✅ исправлено (`117a21d`); карточка — цельная `<a>`, вложенных ссылок 0 |
| 4 | Главный баннер похож на макет | ⚪ открыто, не менялось |
| 5 | Не хватает иллюстраций/тепла | ⚪ открыто, после сдачи |
| 6 | Визуал «О сервисе» заходит на крошки | ✅ исправлено (`117a21d`); на 390 px зазор 24 px, на 1440 px — разные колонки |

## 6. Открытые вопросы и следующий шаг

1. **Права на транскрипты.** Решение владельца: оставить публично / перенести полный текст в приватное хранилище / запросить разрешение автора курса. Рекомендация — приватное хранение (`docs/35`). До решения файлы не удалять, историю не переписывать.
2. **Merge PR #21 и `main`.** Требуется отдельное разрешение. `main` = `d1300d1`: 29 записей, 0 PHP-файлов приложения — release blocker.
3. **Иконки услуг (P1).** Исправить в отдельном UI-коммите: использовать `$service['category']['slug']` или добавить top-level `category_slug` в `mapService()`, затем проверить 10 карточек и Browser QA. В текущий documentation-only проход не входит.
4. **Ручной просмотр видео.** Не подтверждён. Если нужен для защиты — провести отдельно и зафиксировать факт, не выводя его из наличия файлов.
5. **PR #23 / #25.** Не сливать в текущую QA-ветку без отдельного плана (расхождение с базой).

## 7. Следующему агенту

- Не менять `main`/`develop`, не делать force-push, не переписывать историю.
- Для проверки субтитров использовать `scripts/assignment/validate_transcripts.py` и `docs/36`; не заявлять ручной просмотр.
- Перед любым UI-фиксом иконок прочитать `docs/33` пункт 2 и `CatalogService::mapService()`.
- Точка входа по сдаче — `docs/00-URGENT-MODULE-4-DEADLINE-2026-11-19.md`.
