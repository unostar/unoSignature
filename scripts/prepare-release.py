#!/usr/bin/env python3
"""Set plugin version from the top CHANGELOG entry and decide whether to release."""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path


def read_version(plugin_file: Path) -> str:
	text = plugin_file.read_text(encoding="utf-8")
	header_match = re.search(r"^ \* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$", text, re.MULTILINE)
	const_match = re.search(r"define\('UNOSIGNATURE_VERSION',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\);", text)
	if not header_match or not const_match:
		raise RuntimeError("Could not find plugin header and UNOSIGNATURE_VERSION.")
	if header_match.group(1) != const_match.group(1):
		raise RuntimeError("Plugin header version and UNOSIGNATURE_VERSION differ.")
	return header_match.group(1)


def write_version(plugin_file: Path, version: str) -> None:
	text = plugin_file.read_text(encoding="utf-8")
	text = re.sub(
		r"(^ \* Version:\s*)([0-9]+\.[0-9]+\.[0-9]+)(\s*$)",
		rf"\g<1>{version}\g<3>",
		text,
		count=1,
		flags=re.MULTILINE,
	)
	text = re.sub(
		r"define\('UNOSIGNATURE_VERSION',\s*'[0-9]+\.[0-9]+\.[0-9]+'\);",
		f"define('UNOSIGNATURE_VERSION', '{version}');",
		text,
		count=1,
	)
	plugin_file.write_text(text, encoding="utf-8")


def read_changelog_version(changelog_file: Path) -> str | None:
	text = changelog_file.read_text(encoding="utf-8")
	match = re.search(r"(?m)^##\s+([0-9]+\.[0-9]+\.[0-9]+)\s*$", text)
	if not match:
		return None
	return match.group(1)


def version_tuple(version: str) -> tuple[int, int, int]:
	parts = version.split(".")
	if len(parts) != 3 or not all(part.isdigit() for part in parts):
		raise ValueError(f"Unsupported version format: {version}")
	return int(parts[0]), int(parts[1]), int(parts[2])


def main() -> int:
	if len(sys.argv) != 3:
		print("Usage: prepare-release.py <plugin-file> <changelog-file>", file=sys.stderr)
		return 2

	plugin_file = Path(sys.argv[1])
	changelog_file = Path(sys.argv[2])
	if not plugin_file.is_file():
		raise FileNotFoundError(f"Plugin file not found: {plugin_file}")
	if not changelog_file.is_file():
		raise FileNotFoundError(f"Changelog not found: {changelog_file}")

	current_version = read_version(plugin_file)
	changelog_version = read_changelog_version(changelog_file)
	if changelog_version is None:
		print("skip")
		return 0

	if version_tuple(changelog_version) <= version_tuple(current_version):
		print("skip")
		return 0

	write_version(plugin_file, changelog_version)

	github_output = os.environ.get("GITHUB_OUTPUT")
	if github_output:
		with open(github_output, "a", encoding="utf-8") as output:
			output.write(f"version={changelog_version}\n")
			output.write("skip=false\n")

	print(changelog_version)
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
