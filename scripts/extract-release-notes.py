#!/usr/bin/env python3
"""Extract GitHub release notes for a version from CHANGELOG.md."""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path


def extract_release_notes(version: str, changelog: Path) -> str:
	text = changelog.read_text(encoding="utf-8")
	pattern = rf"(?ms)^## {re.escape(version)}\s*\n(.*?)(?=^## |\Z)"
	match = re.search(pattern, text)
	if not match:
		raise SystemExit(f"Missing CHANGELOG section for version {version}.")

	return match.group(1).strip()


def main() -> int:
	if len(sys.argv) != 3:
		print("Usage: extract-release-notes.py <version> <changelog-file>", file=sys.stderr)
		return 2

	version = sys.argv[1]
	changelog = Path(sys.argv[2])
	if not changelog.is_file():
		print(f"Changelog not found: {changelog}", file=sys.stderr)
		return 1

	notes = extract_release_notes(version, changelog)
	print(notes)

	github_output = os.environ.get("GITHUB_OUTPUT")
	if github_output:
		with open(github_output, "a", encoding="utf-8") as output:
			output.write("notes<<EOF\n")
			output.write(notes)
			output.write("\nEOF\n")

	return 0


if __name__ == "__main__":
	raise SystemExit(main())
