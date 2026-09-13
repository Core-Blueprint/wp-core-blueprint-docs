#!/usr/bin/env python3
from __future__ import annotations

import argparse
from pathlib import Path
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
DOMAIN = "core-blueprint-docs"
LOCALES = ("nl_NL", "de_DE", "fr_FR", "es_ES", "it_IT", "pt_PT")


def main() -> int:
    parser = argparse.ArgumentParser(description="Compatibility wrapper for the canonical Core Blueprint Docs i18n workflow.")
    parser.add_argument("--output-dir", default="build/i18n")
    args = parser.parse_args()

    subprocess.run([str(ROOT / "tools" / "i18n" / "update")], cwd=ROOT, check=True)

    output = Path(args.output_dir)
    if not output.is_absolute():
        output = ROOT / output
    output.mkdir(parents=True, exist_ok=True)

    language_dir = ROOT / "languages"
    shutil.copy2(language_dir / f"{DOMAIN}.pot", output / f"{DOMAIN}.pot")
    for locale in LOCALES:
        for suffix in ("po", "mo"):
            source = language_dir / f"{DOMAIN}-{locale}.{suffix}"
            shutil.copy2(source, output / source.name)

    print(f"Docs i18n compatibility sync: PASS -> {output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
