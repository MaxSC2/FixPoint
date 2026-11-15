#!/usr/bin/env python3
"""Extract Module 4 DOCX text, embedded hyperlinks and video subtitle availability."""
from __future__ import annotations
import argparse, json, re, subprocess, sys, zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

NS = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main", "r": "http://schemas.openxmlformats.org/officeDocument/2006/relationships"}
URL_RE = re.compile(r"https?://[^\s<>]+", re.IGNORECASE)
VIDEO_DOMAINS = ("youtube.com", "youtu.be", "vimeo.com")

def extract_docx(path: Path):
    with zipfile.ZipFile(path) as archive:
        document = ET.fromstring(archive.read("word/document.xml"))
        try: relationships = ET.fromstring(archive.read("word/_rels/document.xml.rels"))
        except KeyError: relationships = ET.Element('Relationships')
    rels = {item.attrib.get('Id'): item.attrib.get('Target', '') for item in relationships}
    paragraphs, links = [], []
    for paragraph in document.findall(".//w:p", NS):
        parts, paragraph_links = [], []
        for node in paragraph.iter():
            if node.tag.endswith('}t') and node.text: parts.append(node.text)
            elif node.tag.endswith("}br"): parts.append("\n")
            elif node.tag.endswith("}hyperlink"):
                rel_id = node.attrib.get("{" + NS["r"] + "}id")
                target = rels.get(rel_id, '')
                if target:
                    item = {'relationship': rel_id, 'url': target}
                    paragraph_links.append(item)
                    links.append(item)
        paragraph_text = ''.join(parts).strip()
        if paragraph_text or paragraph_links:
            paragraphs.append({'text': paragraph_text, 'links': paragraph_links})
            for url in URL_RE.findall(paragraph_text):
                links.append({'relationship': None, 'url': url.rstrip('.,);]}>')})
    unique, seen = [], set()
    for item in links:
        if item['url'] not in seen:
            seen.add(item['url'])
            unique.append(item)
    return paragraphs, unique

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--check-video-captions', action='store_true')
    args = parser.parse_args()
    source, output = Path(args.input), Path(args.output)
    output.mkdir(parents=True, exist_ok=True)
    paragraphs, links = extract_docx(source)
    payload = {'paragraphs': paragraphs, 'links': links}
    (output / 'module4-extracted.json').write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding='utf-8')
    lines = ['# Извлечённый текст исходного задания Module 4', '', 'Источник: ' + source.name, '', '## Текст документа', '']
    lines.extend(item['text'] for item in paragraphs if item['text'])
    lines.extend(['', '## Встроенные ссылки', ''])
    lines.extend('- ' + item['url'] for item in links)
    (output / 'module4-extracted.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
    videos = [item['url'] for item in links if any(domain in item['url'].lower() for domain in VIDEO_DOMAINS)]
    caption_results = []
    if args.check_video_captions:
        for url in videos:
            try:
                result = subprocess.run([sys.executable, '-m', 'yt_dlp', '--list-subs', '--skip-download', '--no-warnings', url], capture_output=True, text=True, timeout=90, check=False)
                caption_results.append({'url': url, 'returncode': result.returncode, 'output': (result.stdout + '\n' + result.stderr)[-12000:]})
            except (subprocess.TimeoutExpired, OSError) as error:
                caption_results.append({'url': url, 'error': str(error)})
        (output / 'video-caption-availability.json').write_text(json.dumps(caption_results, ensure_ascii=False, indent=2), encoding='utf-8')
    summary = ['# Module 4 — исходные ссылки и субтитры', '', '- Абзацев: ' + str(len(paragraphs)), '- Уникальных ссылок: ' + str(len(links)), '- Видео-ссылок: ' + str(len(videos)), '', '## Наличие субтитров', '']
    if not videos:
        summary.append('Видео-ссылки в DOCX не обнаружены. Все найденные URL перечислены в module4-extracted.md.')
    elif not args.check_video_captions:
        summary.append('Проверка субтитров не запускалась.')
    else:
        for item in caption_results:
            summary.extend(['### ' + item['url'], '', 'Результат yt-dlp --list-subs:', item.get('output', item.get('error', 'Нет результата'))[-3500:], ''])
    (output / 'module4-video-study.md').write_text('\n'.join(summary) + '\n', encoding='utf-8')
    print('Extracted ' + str(len(paragraphs)) + ' paragraphs, ' + str(len(links)) + ' links, ' + str(len(videos)) + ' video links.')
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
