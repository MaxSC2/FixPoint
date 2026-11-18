"""Unit tests for the Module 4 transcript validator."""
from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from validate_transcripts import parse_vtt, validate_package  # noqa: E402

VALID_VTT = """WEBVTT

00:00:00.000 --> 00:00:04.000
Привет мир, это проверка структуры локального пакета транскриптов.

00:00:04.000 --> 00:00:08.000
Второй блок текста нужен для проверки таймкодов и покрытия.

00:00:08.000 --> 00:00:12.000
Третий блок завершает небольшой, но достаточный тестовый пример.
"""

VALID_TXT = (
    "Привет мир, это проверка структуры локального пакета транскриптов.\n"
    "Второй блок текста нужен для проверки таймкодов и покрытия.\n"
    "Третий блок завершает небольшой, но достаточный тестовый пример.\n"
)


class ValidatorTestCase(unittest.TestCase):
    def setUp(self) -> None:
        self._temp = tempfile.TemporaryDirectory()
        self.directory = Path(self._temp.name)
        self.name = "task01-demo [abcdefghijk]"
        self.entry = {
            "id": "abcdefghijk",
            "name": self.name,
            "title": "Demo",
            "task": "1",
            "sub": "demo",
            "dur": 12,
            "words": len(VALID_TXT.split()),
            "file": f"{self.name}.ru.txt",
        }
        self.write("vtt", VALID_VTT)
        self.write("txt", VALID_TXT)
        self.write_index([self.entry])

    def tearDown(self) -> None:
        self._temp.cleanup()

    def write(self, kind: str, content: str) -> Path:
        path = self.directory / f"{self.name}.ru.{kind}"
        path.write_text(content, encoding="utf-8")
        return path

    def write_index(self, entries: list[dict]) -> None:
        (self.directory / "_index.json").write_text(
            json.dumps(entries, ensure_ascii=False), encoding="utf-8"
        )

    def validate(self, **kwargs):
        kwargs.setdefault("expected_count", 1)
        return validate_package(self.directory, **kwargs)

    def checks(self, report: dict) -> set[str]:
        return {item["check"] for item in report["errors"]}

    def test_valid_package_passes(self) -> None:
        report = self.validate()
        self.assertTrue(report["summary"]["ok"], report["errors"])
        self.assertEqual(1, report["summary"]["vtt_present"])
        self.assertEqual(1, report["summary"]["txt_present"])
        self.assertEqual(3, report["summary"]["total_cues"])
        self.assertEqual("pass", report["files"][0]["status"])

    def test_missing_transcript_fails(self) -> None:
        (self.directory / f"{self.name}.ru.txt").unlink()
        report = self.validate()
        self.assertFalse(report["summary"]["ok"])
        self.assertIn("txt", self.checks(report))

    def test_empty_vtt_fails(self) -> None:
        self.write("vtt", "")
        report = self.validate()
        self.assertFalse(report["summary"]["ok"])
        self.assertIn("vtt", self.checks(report))

    def test_reverse_timestamps_fail(self) -> None:
        self.write(
            "vtt",
            "WEBVTT\n\n00:00:03.000 --> 00:00:04.000\nПервый.\n\n"
            "00:00:01.000 --> 00:00:02.000\nВторой.\n",
        )
        report = self.validate()
        self.assertFalse(report["summary"]["ok"])
        messages = " ".join(item["message"] for item in report["errors"])
        self.assertIn("goes backwards", messages)

    def test_word_count_mismatch_fails(self) -> None:
        self.entry["words"] = 1000
        self.write_index([self.entry])
        report = self.validate()
        self.assertFalse(report["summary"]["ok"])
        self.assertIn("words", self.checks(report))

    def test_duplicate_heavy_text_fails(self) -> None:
        repeated = "Одинаковая строка текста для проверки повторов.\n" * 40
        path = self.write("txt", repeated)
        self.entry["words"] = len(path.read_text(encoding="utf-8").split())
        self.write_index([self.entry])
        report = self.validate()
        self.assertFalse(report["summary"]["ok"])
        self.assertIn("duplicates", self.checks(report))

    def test_docx_id_mismatch_fails(self) -> None:
        report = self.validate(expected_ids=["zzzzzzzzzzz"])
        self.assertFalse(report["summary"]["ok"])
        self.assertIn("docx", self.checks(report))

    def test_index_count_mismatch_fails(self) -> None:
        report = self.validate(expected_count=31)
        self.assertFalse(report["summary"]["ok"])
        self.assertTrue(
            any("expected 31" in item["message"] for item in report["errors"])
        )

    def test_parse_vtt_reports_cue_count_and_duration(self) -> None:
        parsed = parse_vtt(self.directory / f"{self.name}.ru.vtt")
        self.assertTrue(parsed["ok"])
        self.assertEqual(3, len(parsed["cues"]))
        self.assertAlmostEqual(12.0, parsed["duration"], places=3)


if __name__ == "__main__":
    unittest.main()
