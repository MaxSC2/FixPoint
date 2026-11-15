#!/usr/bin/env python3
"""Extract Module 4 DOCX text, embedded links and subtitle-check status."""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
import zipfile
from collections import Counter
from pathlib import Path
from urllib.parse import urlparse
from xml.etree import ElementTree as ET

NS = {
    "w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main",
    "r": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
}
URL_RE = re.compile(r"https?://[^\s<>]+", re.IGNORECASE)
VIDEO_HOSTS = {"youtube.com", "www.youtube.com", "youtu.be", "vimeo.com", "www.vimeo.com"}


def extract_docx(path: Path):
    with zipfile.ZipFile(path) as archive:
        document = ET.fromstring(archive.read("word/document.xml"))
        try:
            relationships = ET.fromstring(archive.read("word/_rels/document.xml.rels"))
        except KeyError:
            relationships = ET.Element("Relationships")

    rels = {item.attrib.get("Id"): item.attrib.get("Target", "") for item in relationships}
    paragraphs, links = [], []

    for paragraph in document.findall(".//w:p", NS):
        parts, paragraph_links = [], []
        for node in paragraph.iter():
            if node.tag.endswith("}t") and node.text:
                parts.append(node.text)
            elif node.tag.endswith("}br"):
                parts.append("\n")
            elif node.tag.endswith("}hyperlink"):
                rel_id = node.attrib.get("{" + NS["r"] + "}id")
                target = rels.get(rel_id, "")
                if target:
                    item = {"relationship": rel_id, "url": target}
                    paragraph_links.append(item)
                    links.append(item)

        paragraph_text = "".join(parts).strip()
        if paragraph_text or paragraph_links:
            paragraphs.append({"text": paragraph_text, "links": paragraph_links})

            # Hyperlink relationships are authoritative. Scanning their visible
            # anchor text as raw URLs produced truncated duplicate URLs before.
            if not paragraph_links:
                for url in URL_RE.findall(paragraph_text):
                    links.append({"relationship": None, "url": url.rstrip(".,);]}>")})

    unique, seen = [], set()
    for item in links:
        url = item["url"]
        if url not in seen:
            seen.add(url)
            unique.append(item)

    return paragraphs, unique


def classify_caption_result(returncode: int, output: str) -> str:
    lowered = output.lower()
    if "sign in to confirm you're not a bot" in lowered or "confirm you’re not a bot" in lowered:
        return "blocked_by_youtube"
    if returncode != 0:
        return "error"
    if "available subtitles" in lowered or "available automatic captions" in lowered:
        return "available"
    if "no subtitles" in lowered or "no automatic captions" in lowered:
        return "none_reported"
    return "checked_without_track_list"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--check-video-captions", action="store_true")
    parser.add_argument("--caption-timeout", type=int, default=45)
    args = parser.parse_args()

    source, output = Path(args.input), Path(args.output)
    output.mkdir(parents=True, exist_ok=True)
    paragraphs, links = extract_docx(source)

    payload = {"paragraphs": paragraphs, "links": links}
    (output / "module4-extracted.json").write_text(
        json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    lines = [
        "# Извлечённый текст исходного задания Module 4",
        "",
        "Источник: " + source.name,
        "",
        "## Текст документа",
        "",
    ]
    lines.extend(item["text"] for item in paragraphs if item["text"])
    lines.extend(["", "## Встроенные ссылки", ""])
    lines.extend("- " + item["url"] for item in links)
    (output / "module4-extracted.md").write_text("\n".join(lines) + "\n", encoding="utf-8")

    videos = []
    for item in links:
        host = (urlparse(item["url"]).hostname or "").lower()
        if host in VIDEO_HOSTS:
            videos.append(item["url"])

    caption_results = []
    if args.check_video_captions:
        for url in videos:
            try:
                result = subprocess.run(
                    [
                        sys.executable, "-m", "yt_dlp", "--list-subs",
                        "--skip-download", "--no-warnings", url,
                    ],
                    capture_output=True,
                    text=True,
                    timeout=args.caption_timeout,
                    check=False,
                )
                combined = result.stdout + "\n" + result.stderr
                caption_results.append({
                    "url": url,
                    "returncode": result.returncode,
                    "status": classify_caption_result(result.returncode, combined),
                    "output": combined[-12000:],
                })
            except (subprocess.TimeoutExpired, OSError) as error:
                caption_results.append({
                    "url": url,
                    "status": "error",
                    "error": str(error),
                })

        (output / "video-caption-availability.json").write_text(
            json.dumps(caption_results, ensure_ascii=False, indent=2), encoding="utf-8"
        )

    statuses = Counter(item.get("status", "not_checked") for item in caption_results)
    summary = [
        "# Module 4 — исходные ссылки и статус проверки субтитров",
        "",
        "- Абзацев DOCX: " + str(len(paragraphs)),
        "- Уникальных вложенных ссылок: " + str(len(links)),
        "- Видео-ссылок: " + str(len(videos)),
        "",
        "## Важное ограничение",
        "",
    ]

    if not videos:
        summary.append("Видео-ссылки в DOCX не обнаружены.")
    elif not args.check_video_captions:
        summary.append("Проверка субтитров не запускалась.")
    else:
        summary.append("Статистика проверки: " + ", ".join(
            f"{status}={count}" for status, count in sorted(statuses.items())
        ) + ".")
        if statuses.get("blocked_by_youtube", 0):
            summary.append(
                "YouTube потребовал авторизацию и заблокировал автоматический запрос. "
                "Для этих роликов наличие субтитров НЕ установлено: blocked не означает none."
            )
        if statuses.get("available", 0):
            summary.append("Для части роликов yt-dlp показал доступные дорожки; см. JSON.")
        if statuses.get("none_reported", 0):
            summary.append("Отсутствие дорожек отмечено только там, где сервис ответил без блокировки.")

    summary.extend(["", "## Результат по каждой ссылке", ""])
    if args.check_video_captions:
        for item in caption_results:
            summary.append(f"- {item['status']}: {item['url']}")
    else:
        summary.extend("- не проверено: " + url for url in videos)

    (output / "module4-video-study.md").write_text(
        "\n".join(summary) + "\n", encoding="utf-8"
    )

    print(
        f"Extracted {len(paragraphs)} paragraphs, {len(links)} unique links, "
        f"{len(videos)} video links."
    )
    if statuses.get("blocked_by_youtube", 0):
        print(
            f"WARNING: subtitle status is unverified for "
            f"{statuses['blocked_by_youtube']} videos because YouTube blocked automated access."
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
