# FixPoint — execution log коррекции документации по аудиту субтитров

**Дата:** 18.11.2026  
**Ветка:** `docs/module4-audit-corrections-2026-11-17`  
**База:** `84bd094` (`qa/browser-visual-capture`), PR #21 Draft — не влит.  
**Тип изменения:** только документация и tooling. Код приложения, UI, миграции, `main` и `develop` не изменялись. Merge не выполнялся, force-push и переписывание истории отсутствуют. DOCX сохранён без изменений.

## 1. Задача

Закрыть замечания независимого аудита комментария [Issue #22, id 6956781797](https://github.com/MaxSC2/FixPoint/issues/22#issuecomment-6956781797) к коммиту субтитров `0a10dc3`:

1. не превращать темы видео задания №9 в отдельные продуктовые требования;
2. исправить утверждение «`title` из БД»;
3. разделить темы уроков и обязательные требования DOCX;
4. убрать устаревшую формулировку Source Audit, разделив live-эндпоинт и локальный пакет;
5. усилить проверку транскриптов воспроизводимым валидатором;
6. зафиксировать риск прав/ToS и оставить решение владельцу.

## 2. Изменённые файлы

| Файл | Что изменено |
|---|---|
| `docs/32-ASSIGNMENT-VIDEOS-TRANSCRIPTS-2026-11-17.md` | Переписан: требование DOCX отделено от тем уроков; задание №9 переклассифицировано; таблица мета-тегов; Source Audit из двух фактов; права; команды проверки |
| `docs/02-MODULE-4-MAPPING.md` | Задание №9, статус видео/субтитров, чек-лист и «чего не утверждать» |
| `docs/00-URGENT-MODULE-4-DEADLINE-2026-11-19.md` | Новая секция коррекции; статус субтитров; ограничения; ссылки на docs/35 |
| `docs/29-AGENT-HANDOFF-FINAL-AUDIT-2026-11-17.md` | Актуализированы субтитры, валидатор, права, задание №9 |
| `docs/33-OWNER-UX-FEEDBACK-2026-11-17.md` | Перенесён из `origin/docs/owner-ux-feedback-2026-11-17`; статусы 1–6 обновлены под `84bd094` |
| `docs/35-TRANSCRIPT-RIGHTS-2026-11-18.md` | Новый: права, ToS, список публичных файлов, варианты и рекомендация |
| `docs/36-EXECUTION-LOG-2026-11-17.md` | Этот журнал |
| `scripts/assignment/validate_transcripts.py` | Новый валидатор пакета |
| `scripts/assignment/tests/test_validate_transcripts.py` | Новый: 9 unit-тестов |
| `scripts/assignment/extract_module4.py` | `--local-transcripts`, раздельный live/local отчёт, exit 1 при провале локальной проверки |
| `.github/workflows/module4-source-audit.yml` | Тесты валидатора, запуск с `--local-transcripts`, path `assignment/subtitles/**`, timeout 20 мин |

## 3. Локальные проверки

### 3.1. Unit-тесты валидатора

```powershell
python -m unittest discover -s scripts/assignment/tests -v
```

Результат: **9 tests, OK**.

### 3.2. Валидатор на реальном пакете

```powershell
python scripts/assignment/validate_transcripts.py `
  --dir assignment/subtitles `
  --docx "assignment/Модуль 4.docx" `
  --json-out artifacts/module4/transcript-validation.json `
  --md-out artifacts/module4/transcript-validation.md
```

Результат:

```
Transcript package: PASS — 31/31 VTT, 31/31 TXT, 15411 cues, 39627 words, 0 errors, 7 warnings
DOCX IDs: 31 unique; index match: yes
```

- 24 файла `pass`, 7 `warn`, 0 `fail`.
- 7 предупреждений: пустые cue автосубтитров (>15 % реплик) в 7 VTT; текст TXT при этом присутствует.
- Слов TXT 39 627 против 39 001 в `_index.json` (ratio в допуске).

### 3.3. Единый вход extract + local

```powershell
python scripts/assignment/extract_module4.py `
  --input "assignment/Модуль 4.docx" `
  --output artifacts/module4 `
  --local-transcripts assignment/subtitles
```

Результат:

```
Extracted 56 paragraphs, 31 unique links, 31 video links.
Local transcript package: PASS — 31/31 VTT, 31/31 TXT, IDs match DOCX: yes, structure errors: 0, warnings: 7.
```

### 3.4. Что проверяет валидатор

- наличие и непустота 31 `ru.vtt` и 31 `ru.txt`;
- разбор VTT, монотонность и непересечение таймкодов;
- ID из `_index.json` и уникальные ID из DOCX;
- объём TXT против индекса (ratio 0.8–1.35 и абсолютный допуск);
- слишком короткие тексты, дубли строк, повторяющиеся 10-словные фразы;
- трассируемость TXT→VTT (покрытие ≥95 %);
- расхождение длительности и доля пустых cue — warning;
- отсутствие полного текста в отчётах (только счётчики, ID, хэши).

## 4. Source Audit: точное состояние

`Module 4 source audit` теперь обязан показывать два независимых факта:

1. **Live-эндпоинт YouTube:** `yt-dlp` может вернуть `available` или `blocked_by_youtube`; статус зависит от времени, IP и антибот-политики. `blocked` — **unverified**, а не «субтитров нет». Наличие локального файла не делает live-проверку verified. Локальный прогон 18.11.2026: `available=31`; предыдущий CI: `blocked_by_youtube`. CI-статус фиксируется в артефакте и `docs/37`.
2. **Локальный пакет:** `transcript-validation.md`/`.json` подтверждают наличие, разбор, порядок таймкодов, ID, объём и трассируемость. Это не подтверждение ручного просмотра и не семантическая проверка.

Workflow обновлён: unit-тесты → установка `yt-dlp` → extract с `--check-video-captions --local-transcripts` → upload artifacts. При провале локальной проверки шаг завершается с exit 1.

## 5. GitHub Actions

| Workflow | Run | Статус | Результат |
|---|---|---|---|
| CI | записывается в docs/37 | — | — |
| Browser QA | не триггерится | — | Изменения только `docs/**`, `scripts/**`, `.github/**`; нет `app/**`/`public/**` |
| Module 4 source audit | записывается в docs/37 | — | Live-проба + локальный пакет |

Точные run IDs и SHA корректирующего коммита фиксируются в `docs/37-...CHECKPOINT` после push.

## 6. Открытые вопросы владельцу

1. **Права на транскрипты:** оставить публично, перенести полный текст в приватное хранилище или запросить разрешение автора курса. Рекомендация — приватное хранение (`docs/35`). До решения файлы не удаляются.
2. **Merge PR #21 и синхронизация `main`:** требуют отдельного разрешения; `main` содержит 29 записей и 0 PHP-файлов приложения — release blocker.
3. **Иконки услуг:** регресс `category_slug` (P1) выявлен и задокументирован в `docs/33`, но не исправлен, потому что текущий проход запрещает UI-изменения.
4. **Ручной просмотр видео:** не подтверждён; если требуется для защиты, его нужно провести отдельно и отметить факт, а не выводить из наличия файлов.
