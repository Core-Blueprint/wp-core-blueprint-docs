#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path
from shutil import which

import polib

ROOT = Path(__file__).resolve().parents[1]
LANG_DIR = ROOT / "languages"
ENTRY_FILE = ROOT / "core-blueprint-docs.php"
DOMAIN = "core-blueprint-docs"
LOCALES = ("nl_NL", "de_DE", "fr_FR", "es_ES", "it_IT", "pt_PT")
TRANSLATION_GLOB = "i18n-translations*.json"


def plugin_version() -> str:
    content = ENTRY_FILE.read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*([^\r\n]+)", content, re.MULTILINE)
    if not match:
        raise RuntimeError("Unable to determine Docs plugin version from plugin header.")
    return match.group(1).strip()


def run(*args: str) -> None:
    subprocess.run(args, cwd=ROOT, check=True)


def php_sources() -> list[str]:
    files: list[str] = []
    for path in ROOT.rglob("*.php"):
        relative = path.relative_to(ROOT)
        if relative.parts[0] in {"tests", "tools", ".github", "build"}:
            continue
        files.append(str(relative))
    return sorted(files)


def make_pot(version: str, output_dir: Path) -> Path:
    sources = php_sources()
    if not sources:
        raise RuntimeError("No PHP sources found for translation extraction.")

    output_dir.mkdir(parents=True, exist_ok=True)
    pot_path = output_dir / f"{DOMAIN}.pot"
    output_arg = str(pot_path.relative_to(ROOT)) if pot_path.is_relative_to(ROOT) else str(pot_path)

    run(
        "xgettext",
        "--language=PHP",
        "--from-code=UTF-8",
        "--add-comments=translators",
        "--sort-output",
        "--keyword=__",
        "--keyword=_e",
        "--keyword=esc_html__",
        "--keyword=esc_html_e",
        "--keyword=esc_attr__",
        "--keyword=esc_attr_e",
        "--keyword=_x:1,2c",
        "--keyword=_ex:1,2c",
        "--keyword=esc_html_x:1,2c",
        "--keyword=esc_attr_x:1,2c",
        "--keyword=_n:1,2",
        "--keyword=_nx:1,2,4c",
        "--package-name=Core Blueprint Docs",
        f"--package-version={version}",
        "--output",
        output_arg,
        *sources,
    )

    pot = polib.pofile(str(pot_path))
    pot.metadata["Project-Id-Version"] = f"Core Blueprint Docs {version}"
    pot.metadata["Content-Type"] = "text/plain; charset=UTF-8"
    pot.metadata["Content-Transfer-Encoding"] = "8bit"
    pot.metadata.pop("POT-Creation-Date", None)
    pot.save(str(pot_path))
    return pot_path


def load_translation_map() -> dict[str, dict[str, str]]:
    merged: dict[str, dict[str, str]] = {}
    paths = sorted((ROOT / "tools").glob(TRANSLATION_GLOB))
    if not paths:
        raise RuntimeError("No Docs translation maps were found.")

    for path in paths:
        data = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            raise RuntimeError(f"Translation map {path.name} must be a JSON object keyed by English msgid.")
        for msgid, localized in data.items():
            if not isinstance(localized, dict):
                raise RuntimeError(f"Translation entry {msgid!r} in {path.name} must be a locale map.")
            target = merged.setdefault(msgid, {})
            for locale, value in localized.items():
                if locale not in LOCALES:
                    raise RuntimeError(f"Unknown locale {locale!r} in {path.name} for {msgid!r}.")
                if not isinstance(value, str) or not value.strip():
                    raise RuntimeError(f"Empty/non-string translation in {path.name} for {msgid!r} / {locale}.")
                if locale in target and target[locale] != value:
                    raise RuntimeError(f"Conflicting translation for {msgid!r} / {locale} between translation maps.")
                target[locale] = value
    return merged


def sync_locale(
    locale: str,
    version: str,
    translations: dict[str, dict[str, str]],
    pot_path: Path,
    output_dir: Path,
) -> list[str]:
    source_po = LANG_DIR / f"{DOMAIN}-{locale}.po"
    output_po = output_dir / f"{DOMAIN}-{locale}.po"
    output_mo = output_dir / f"{DOMAIN}-{locale}.mo"
    if not source_po.is_file():
        raise RuntimeError(f"Missing source catalog: {source_po.name}")

    po = polib.pofile(str(source_po))
    pot = polib.pofile(str(pot_path))
    po.merge(pot)

    for entry in list(po):
        if entry.obsolete:
            po.remove(entry)

    po.metadata["Project-Id-Version"] = f"Core Blueprint Docs {version}"
    po.metadata["Language"] = locale
    po.metadata["Content-Type"] = "text/plain; charset=UTF-8"
    po.metadata["Content-Transfer-Encoding"] = "8bit"

    for entry in po:
        if entry.msgid_plural:
            continue
        localized = translations.get(entry.msgid, {}).get(locale)
        if localized is not None:
            entry.msgstr = localized

    missing: list[str] = []
    for entry in po:
        if entry.msgid_plural:
            if not entry.msgstr_plural or any(not value.strip() for value in entry.msgstr_plural.values()):
                missing.append(entry.msgid)
        elif not entry.msgstr.strip():
            missing.append(entry.msgid)

    po.save(str(output_po))
    if not missing:
        po.save_as_mofile(str(output_mo))
    return missing


def resolve_output_dir(raw: str) -> Path:
    output = Path(raw)
    if not output.is_absolute():
        output = ROOT / output
    return output.resolve()


def main() -> int:
    parser = argparse.ArgumentParser(description="Generate complete Core Blueprint Docs gettext catalogs.")
    parser.add_argument(
        "--output-dir",
        default="build/i18n",
        help="Directory for generated POT/PO/MO catalogs (default: build/i18n).",
    )
    args = parser.parse_args()

    if not which("xgettext"):
        print("Missing required command: xgettext", file=sys.stderr)
        return 2

    output_dir = resolve_output_dir(args.output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)

    version = plugin_version()
    pot_path = make_pot(version, output_dir)
    translations = load_translation_map()

    all_missing: dict[str, list[str]] = {}
    for locale in LOCALES:
        missing = sync_locale(locale, version, translations, pot_path, output_dir)
        if missing:
            all_missing[locale] = missing

    if all_missing:
        print("Translation completeness check failed:", file=sys.stderr)
        for locale, msgids in all_missing.items():
            print(f"\n[{locale}]", file=sys.stderr)
            for msgid in sorted(set(msgids)):
                print(f"- {msgid}", file=sys.stderr)
        return 1

    print(f"Docs i18n sync: PASS ({version}) -> {output_dir}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
