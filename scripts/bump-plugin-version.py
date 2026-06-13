#!/usr/bin/env python3
"""Bump the unoSignature plugin header version."""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path


def bump_patch(version: str) -> str:
    parts = version.split(".")
    if len(parts) != 3 or not all(part.isdigit() for part in parts):
        raise ValueError(f"Unsupported version format: {version}")

    major, minor, patch = (int(part) for part in parts)
    return f"{major}.{minor}.{patch + 1}"


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: bump-plugin-version.py <plugin-file>", file=sys.stderr)
        return 2

    plugin_file = Path(sys.argv[1])
    text = plugin_file.read_text()

    header_match = re.search(r"^ \* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$", text, re.MULTILINE)
    const_match = re.search(r"define\('UNOSIGNATURE_VERSION',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\);", text)
    if not header_match or not const_match:
        raise RuntimeError("Could not find plugin header and UNOSIGNATURE_VERSION.")

    if header_match.group(1) != const_match.group(1):
        raise RuntimeError("Plugin header version and UNOSIGNATURE_VERSION differ.")

    old_version = header_match.group(1)
    new_version = bump_patch(old_version)

    text = re.sub(
        r"(^ \* Version:\s*)([0-9]+\.[0-9]+\.[0-9]+)(\s*$)",
        rf"\g<1>{new_version}\g<3>",
        text,
        count=1,
        flags=re.MULTILINE,
    )
    text = re.sub(
        r"define\('UNOSIGNATURE_VERSION',\s*'[0-9]+\.[0-9]+\.[0-9]+'\);",
        f"define('UNOSIGNATURE_VERSION', '{new_version}');",
        text,
        count=1,
    )
    plugin_file.write_text(text)

    print(new_version)
    github_output = os.environ.get("GITHUB_OUTPUT")
    if github_output:
        with open(github_output, "a", encoding="utf-8") as output:
            output.write(f"version={new_version}\n")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
