#!/usr/bin/env python3
"""Validate the local Module 4 transcript package.

The package is produced from YouTube auto-captions (yt-dlp 2026.10.26) and is
stored under ``assignment/subtitles``. This validator checks structure and
internal consistency only. It does not prove that a human watched the videos,
nor that machine-recognised speech is semantically correct.

Usage:
    python scripts/assignment/validate_transcripts.py \
        --dir assignment/subtitles \
        --docx "assignment/Модуль 4.docx" \
        --json-out artifacts/module4/transcript-validation.json \
        --md-out artifacts/module4/transcript-validation.md
"""
from __future__ import annotations

import argparse
import hashlib
import html
import json
import re
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

SCHEMA_VERSION = 1
DEFAULT_EXPECTED_COUNT = 31
TIMESTAMP_RE = re.compile(
    r"^(\d{2}):(\d{2}):(\d{2})\.(\d{3})\s+-->\s+"
    r"(\d{2}):(\d{2}):(\d{2})\.(\d{3})(?:\s+.*)?$"
)
ID_RE = re.compile(r"^[A-Za-z0-9_-]{6,20}$")
TAG_RE = re.compile(r"<[^>]+>")
SPACE_RE = re.compile(r"\s+")


def timestamp_seconds(groups: Iterable[str]) -> float:
    hours, minutes, seconds, millis = (int(value) for value in groups)
    return hours * 3600 + minutes * 60 + seconds + millis / 1000


def normalize_text(value: str) -> str:
    value = html.unescape(value)
    value = TAG_RE.sub("", value)
    return SPACE_RE.sub(" ", value).strip().lower()


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def word_tokens(value: str) -> list[str]:
    return re.findall(r"\S+", value)


def parse_vtt(path: Path) -> dict[str, Any]:
    """Parse a WEBVTT file and return cues plus structural issues."""
    result: dict[str, Any] = {
        "ok": False,
        "cues": [],
        "errors": [],
        "warnings": [],
        "empty_cues": 0,
    }
    try:
        raw = path.read_text(encoding="utf-8-sig")
    except OSError as error:
        result["errors"].append(f"cannot read VTT: {error}")
        return result

    lines = raw.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    if not lines or not lines[0].lstrip().startswith("WEBVTT"):
        result["errors"].append("first line is not WEBVTT")
        return result

    cues: list[dict[str, Any]] = []
    current: dict[str, Any] | None = None
    previous_start = -1.0
    previous_end = -1.0

    def finish_cue() -> None:
        nonlocal current
        if current is None:
            return
        text = normalize_text(" ".join(current["text"]))
        if text == "":
            result["empty_cues"] += 1
        cues.append(
            {
                "index": len(cues) + 1,
                "start": current["start"],
                "end": current["end"],
                "text": text,
            }
        )
        current = None

    for line_number, line in enumerate(lines[1:], start=2):
        stripped = line.strip()
        match = TIMESTAMP_RE.match(stripped)
        if match:
            finish_cue()
            groups = match.groups()
            start = timestamp_seconds(groups[0:4])
            end = timestamp_seconds(groups[4:8])
            if end <= start:
                result["errors"].append(
                    f"line {line_number}: cue end is not after start ({stripped})"
                )
            if start + 0.001 < previous_start:
                result["errors"].append(
                    f"line {line_number}: cue start goes backwards ({stripped})"
                )
            if start + 0.001 < previous_end:
                result["errors"].append(
                    f"line {line_number}: cue overlaps the previous cue ({stripped})"
                )
            previous_start, previous_end = start, end
            current = {"start": start, "end": end, "text": []}
            continue

        if line == "":
            if current is not None:
                finish_cue()
            continue

        if current is not None:
            current["text"].append(stripped)
            continue

        if stripped.startswith(("NOTE", "Kind:", "Language:")):
            continue

        result["warnings"].append(
            f"line {line_number}: text outside a cue was ignored ({stripped[:80]!r})"
        )

    finish_cue()

    if not cues:
        result["errors"].append("VTT has no cues")
    if cues:
        result["first_cue"] = cues[0]
        result["last_cue"] = cues[-1]
        result["duration"] = round(cues[-1]["end"], 3)
    result["cues"] = cues
    result["ok"] = not result["errors"]
    return result


def duplicate_line_ratio(text: str) -> float:
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    if len(lines) < 5:
        return 0.0
    return 1 - len(set(lines)) / len(lines)


def top_shingle_repeats(text: str, width: int = 10) -> int:
    words = normalize_text(text).split()
    if len(words) < width * 2:
        return 0
    shingles = Counter(tuple(words[index : index + width]) for index in range(len(words) - width + 1))
    return shingles.most_common(1)[0][1] if shingles else 0


def cue_coverage(cues: list[dict[str, Any]], text: str, sample_limit: int = 200) -> float:
    normalized = normalize_text(text)
    candidates = [cue["text"] for cue in cues if len(cue["text"]) >= 20]
    if not candidates:
        return 1.0
    if len(candidates) > sample_limit:
        step = len(candidates) / sample_limit
        candidates = [candidates[min(int(index * step), len(candidates) - 1)] for index in range(sample_limit)]
    matched = sum(1 for cue in candidates if cue in normalized)
    return matched / len(candidates)


def video_id_from_url(url: str) -> str | None:
    from urllib.parse import parse_qs, urlparse

    parsed = urlparse(url)
    host = (parsed.hostname or "").lower()
    if host == "youtu.be":
        candidate = parsed.path.strip("/").split("/")[0]
        return candidate or None
    if host in {"youtube.com", "www.youtube.com", "m.youtube.com"}:
        if parsed.path.startswith("/watch"):
            candidate = parse_qs(parsed.query).get("v", [""])[0]
            return candidate or None
        for prefix in ("/embed/", "/shorts/", "/live/"):
            if parsed.path.startswith(prefix):
                candidate = parsed.path[len(prefix) :].split("/")[0]
                return candidate or None
    return None


def docx_video_ids(docx_path: Path) -> list[str]:
    """Extract unique YouTube IDs from the DOCX through extract_module4."""
    import sys

    script_dir = str(Path(__file__).resolve().parent)
    if script_dir not in sys.path:
        sys.path.insert(0, script_dir)
    from extract_module4 import VIDEO_HOSTS, extract_docx  # noqa: PLC0415

    _, links = extract_docx(docx_path)
    ids: list[str] = []
    for item in links:
        from urllib.parse import urlparse

        host = (urlparse(item["url"]).hostname or "").lower()
        if host not in VIDEO_HOSTS:
            continue
        video_id = video_id_from_url(item["url"])
        if video_id and video_id not in ids:
            ids.append(video_id)
    return ids


def validate_file(entry: dict[str, Any], directory: Path) -> dict[str, Any]:
    video_id = str(entry.get("id", ""))
    name = str(entry.get("name", ""))
    issues: list[dict[str, str]] = []
    warnings: list[dict[str, str]] = []

    def issue(check: str, message: str, severity: str = "error") -> None:
        target = issues if severity == "error" else warnings
        target.append({"id": video_id, "check": check, "message": message})

    vtt_path = directory / f"{name}.ru.vtt"
    txt_path = directory / f"{name}.ru.txt"
    row: dict[str, Any] = {
        "id": video_id,
        "task": str(entry.get("task", "")),
        "name": name,
        "index_words": int(entry.get("words", 0)),
        "index_duration": int(entry.get("dur", 0)),
        "errors": issues,
        "warnings": warnings,
    }

    if not ID_RE.match(video_id):
        issue("id", f"video id has an unexpected format: {video_id!r}")
    if name == "":
        issue("name", "index entry has no name")
    if str(entry.get("file", "")) != txt_path.name:
        issue("index", f"index file field does not match {txt_path.name}")

    for kind, path in (("vtt", vtt_path), ("txt", txt_path)):
        if not path.exists():
            issue(kind, f"missing file: {path.name}")
            row[kind] = {"exists": False}
            continue
        size = path.stat().st_size
        row[kind] = {"exists": True, "bytes": size, "sha256": sha256(path)}
        if size == 0:
            issue(kind, f"empty file: {path.name}")

    if not txt_path.exists() or row["txt"].get("bytes", 0) == 0:
        row["status"] = "fail"
        return row

    text = txt_path.read_text(encoding="utf-8-sig")
    tokens = word_tokens(text)
    actual_words = len(tokens)
    row["txt"].update(
        {
            "words": actual_words,
            "chars": len(text),
            "lines": len([line for line in text.splitlines() if line.strip()]),
        }
    )

    index_words = row["index_words"]
    if actual_words == 0:
        issue("txt", "TXT has no words")
    elif actual_words < 12:
        issue("txt", f"TXT is suspiciously short: {actual_words} words")
    elif index_words >= 60 and actual_words < index_words * 0.5:
        issue("txt", f"TXT is less than half of the indexed word count: {actual_words} vs {index_words}")
    if index_words > 0:
        ratio = actual_words / index_words
        row["word_ratio"] = round(ratio, 3)
        if ratio < 0.8 or ratio > 1.35:
            issue(
                "words",
                f"TXT words {actual_words} differ from index {index_words} (ratio {ratio:.3f})",
            )
        if abs(actual_words - index_words) > max(40, index_words * 0.2):
            issue(
                "words",
                f"TXT words differ from index by more than tolerance: {actual_words} vs {index_words}",
            )

    duplicate_ratio = duplicate_line_ratio(text)
    row["duplicate_line_ratio"] = round(duplicate_ratio, 4)
    if duplicate_ratio > 0.10:
        issue("duplicates", f"duplicate lines ratio is {duplicate_ratio:.1%}")

    top_repeats = top_shingle_repeats(text)
    row["top_shingle_repeats"] = top_repeats
    if top_repeats > 3:
        issue("duplicates", f"a 10-word phrase repeats {top_repeats} times")

    vtt = parse_vtt(vtt_path) if vtt_path.exists() else {"ok": False, "cues": [], "errors": ["missing VTT"]}
    row["vtt"].update(
        {
            "cues": len(vtt.get("cues", [])),
            "empty_cues": vtt.get("empty_cues", 0),
            "duration": vtt.get("duration"),
            "parse_ok": bool(vtt.get("ok")),
        }
    )
    if vtt.get("cues") and vtt.get("empty_cues", 0) / len(vtt["cues"]) > 0.15:
        warnings.append(
            {
                "id": video_id,
                "check": "vtt",
                "message": f"{vtt['empty_cues']} of {len(vtt['cues'])} cues have no text",
            }
        )
    for message in vtt.get("errors", []):
        issue("vtt", message)
    for message in vtt.get("warnings", []):
        warnings.append({"id": video_id, "check": "vtt", "message": message})

    if vtt.get("cues"):
        coverage = cue_coverage(vtt["cues"], text)
        row["cue_coverage"] = round(coverage, 4)
        if coverage < 0.95:
            issue("traceability", f"only {coverage:.1%} of sampled VTT cues are found in TXT")
        last_end = float(vtt["duration"])
        index_duration = row["index_duration"]
        row["last_cue_end"] = last_end
        if index_duration > 0:
            delta = abs(last_end - index_duration)
            row["duration_delta"] = round(last_end - index_duration, 3)
            if delta > max(10.0, index_duration * 0.05):
                warnings.append(
                    {
                        "id": video_id,
                        "check": "duration",
                        "message": f"last cue ends {delta:.1f}s away from index duration {index_duration}s",
                    }
                )

    row["status"] = "fail" if issues else ("warn" if warnings else "pass")
    return row


def validate_package(
    directory: Path,
    *,
    index_path: Path | None = None,
    docx_path: Path | None = None,
    expected_count: int = DEFAULT_EXPECTED_COUNT,
    expected_ids: list[str] | None = None,
) -> dict[str, Any]:
    """Validate a transcript package and return a JSON-serialisable report."""
    directory = Path(directory)
    index_path = Path(index_path) if index_path else directory / "_index.json"
    report: dict[str, Any] = {
        "schema_version": SCHEMA_VERSION,
        "generated_at": datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
        "tool": {"name": "validate_transcripts.py", "version": "1.0"},
        "method": (
            "YouTube auto-captions downloaded with yt-dlp 2026.10.26; "
            "TXT built from the last rolling caption line with de-duplication"
        ),
        "package_dir": str(directory),
        "index_path": str(index_path),
        "docx_path": str(docx_path) if docx_path else None,
        "expected_count": expected_count,
        "summary": {},
        "files": [],
        "errors": [],
        "warnings": [],
    }

    if not index_path.exists():
        report["errors"].append({"check": "index", "message": f"index not found: {index_path}"})
        report["summary"] = {"ok": False, "errors": 1, "warnings": 0}
        return report

    try:
        index = json.loads(index_path.read_text(encoding="utf-8-sig"))
    except (OSError, json.JSONDecodeError) as error:
        report["errors"].append({"check": "index", "message": f"cannot read index: {error}"})
        report["summary"] = {"ok": False, "errors": 1, "warnings": 0}
        return report

    if not isinstance(index, list):
        report["errors"].append({"check": "index", "message": "index root is not a list"})
        report["summary"] = {"ok": False, "errors": 1, "warnings": 0}
        return report

    if len(index) != expected_count:
        report["errors"].append(
            {
                "check": "index",
                "message": f"index has {len(index)} entries, expected {expected_count}",
            }
        )

    ids = [str(entry.get("id", "")) for entry in index if isinstance(entry, dict)]
    duplicates = sorted({video_id for video_id in ids if ids.count(video_id) > 1})
    if duplicates:
        report["errors"].append(
            {"check": "ids", "message": "duplicate ids in index: " + ", ".join(duplicates)}
        )

    if expected_ids is not None:
        expected_set, actual_set = set(expected_ids), set(ids)
        missing = sorted(expected_set - actual_set)
        extra = sorted(actual_set - expected_set)
        if missing:
            report["errors"].append(
                {"check": "docx", "message": "ids missing from index: " + ", ".join(missing)}
            )
        if extra:
            report["errors"].append(
                {"check": "docx", "message": "ids not present in DOCX: " + ", ".join(extra)}
            )

    for entry in index:
        if not isinstance(entry, dict):
            report["errors"].append({"check": "index", "message": f"entry is not an object: {entry!r}"})
            continue
        report["files"].append(validate_file(entry, directory))

    errors = list(report["errors"])
    warnings: list[dict[str, str]] = []
    for row in report["files"]:
        errors.extend(row["errors"])
        warnings.extend(row["warnings"])

    total_words = sum(row.get("txt", {}).get("words", 0) for row in report["files"])
    total_cues = sum(row.get("vtt", {}).get("cues", 0) for row in report["files"])
    checks = {
        "index_entries": len(index),
        "vtt_present": sum(1 for row in report["files"] if row.get("vtt", {}).get("exists")),
        "txt_present": sum(1 for row in report["files"] if row.get("txt", {}).get("exists")),
        "vtt_parsed": sum(1 for row in report["files"] if row.get("vtt", {}).get("parse_ok")),
        "files_passed": sum(1 for row in report["files"] if row.get("status") == "pass"),
        "files_warned": sum(1 for row in report["files"] if row.get("status") == "warn"),
        "files_failed": sum(1 for row in report["files"] if row.get("status") == "fail"),
        "total_cues": total_cues,
        "total_words": total_words,
        "index_words_total": sum(row.get("index_words", 0) for row in report["files"]),
    }
    if expected_ids is not None:
        checks["docx_unique_ids"] = len(expected_ids)
        checks["ids_match_docx"] = set(expected_ids) == set(ids)

    report["errors"] = errors
    report["warnings"] = warnings
    report["summary"] = {
        "ok": not errors,
        **checks,
        "errors": len(errors),
        "warnings": len(warnings),
    }
    return report


def render_markdown(report: dict[str, Any]) -> str:
    summary = report["summary"]
    lines = [
        "# Module 4 — структурная проверка локальных транскриптов",
        "",
        f"- Сгенерировано: {report['generated_at']}",
        f"- Каталог: `{report['package_dir']}`",
        f"- Индекс: `{report['index_path']}`",
        f"- DOCX: `{report['docx_path']}`" if report.get("docx_path") else "- DOCX: не проверялся",
        f"- Метод: {report['method']}",
        f"- Результат: **{'PASS' if summary.get('ok') else 'FAIL'}**",
        "",
        "| Показатель | Значение |",
        "|---|---:|",
    ]
    for key in (
        "index_entries",
        "vtt_present",
        "txt_present",
        "vtt_parsed",
        "files_passed",
        "files_warned",
        "files_failed",
        "total_cues",
        "total_words",
        "index_words_total",
        "docx_unique_ids",
        "errors",
        "warnings",
    ):
        if key in summary:
            lines.append(f"| {key} | {summary[key]} |")
    lines.extend(
        [
            "",
            "> Проверка структурная: она подтверждает наличие, разбор, порядок таймкодов, "
            "трассируемость TXT к VTT и согласованность объёма. Она не доказывает, что видео "
            "просмотрено человеком, и не проверяет семантическую точность машинного распознавания.",
            "",
            "| ID | VTT | Реплик | TXT слов | Индекс слов | Покрытие | Дублей | Статус |",
            "|---|---:|---:|---:|---:|---:|---:|---|",
        ]
    )
    for row in report["files"]:
        vtt = row.get("vtt", {})
        txt = row.get("txt", {})
        coverage = row.get("cue_coverage")
        lines.append(
            "| `{id}` | {vtt} | {cues} | {words} | {index_words} | {coverage} | {dupes} | {status} |".format(
                id=row["id"],
                vtt="да" if vtt.get("exists") else "нет",
                cues=vtt.get("cues", 0),
                words=txt.get("words", 0),
                index_words=row.get("index_words", 0),
                coverage=f"{coverage:.1%}" if coverage is not None else "—",
                dupes=f"{row.get('duplicate_line_ratio', 0):.1%}",
                status=row.get("status", "fail"),
            )
        )
    if report["errors"]:
        lines.extend(["", "## Ошибки", ""])
        lines.extend(
            f"- `{item.get('id', '—')}` / {item.get('check', '—')}: {item.get('message', '')}"
            for item in report["errors"]
        )
    if report["warnings"]:
        lines.extend(["", "## Предупреждения", ""])
        lines.extend(
            f"- `{item.get('id', '—')}` / {item.get('check', '—')}: {item.get('message', '')}"
            for item in report["warnings"]
        )
    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dir", required=True, help="transcript package directory")
    parser.add_argument("--index", help="path to _index.json (default: <dir>/_index.json)")
    parser.add_argument("--docx", help="original Module 4 DOCX for ID comparison")
    parser.add_argument("--expected-count", type=int, default=DEFAULT_EXPECTED_COUNT)
    parser.add_argument("--json-out", help="write the full report as JSON")
    parser.add_argument("--md-out", help="write a readable Markdown report")
    args = parser.parse_args()

    expected_ids = None
    if args.docx:
        docx_path = Path(args.docx)
        if not docx_path.exists():
            parser.error(f"DOCX not found: {docx_path}")
        expected_ids = docx_video_ids(docx_path)

    report = validate_package(
        Path(args.dir),
        index_path=Path(args.index) if args.index else None,
        docx_path=Path(args.docx) if args.docx else None,
        expected_count=args.expected_count,
        expected_ids=expected_ids,
    )

    if args.json_out:
        output = Path(args.json_out)
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if args.md_out:
        output = Path(args.md_out)
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_text(render_markdown(report), encoding="utf-8")

    summary = report["summary"]
    print(
        "Transcript package: {status} — {vtt}/{expected} VTT, {txt}/{expected} TXT, "
        "{cues} cues, {words} words, {errors} errors, {warnings} warnings".format(
            status="PASS" if summary.get("ok") else "FAIL",
            vtt=summary.get("vtt_present", 0),
            txt=summary.get("txt_present", 0),
            expected=report["expected_count"],
            cues=summary.get("total_cues", 0),
            words=summary.get("total_words", 0),
            errors=summary.get("errors", 0),
            warnings=summary.get("warnings", 0),
        )
    )
    if expected_ids is not None:
        print(
            "DOCX IDs: {count} unique; index match: {match}".format(
                count=len(expected_ids),
                match="yes" if summary.get("ids_match_docx") else "no",
            )
        )
    for item in report["errors"][:20]:
        print(f"ERROR {item.get('id', '—')} / {item.get('check', '—')}: {item.get('message', '')}")
    return 0 if summary.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main())
