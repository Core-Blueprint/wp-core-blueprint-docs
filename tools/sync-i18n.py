#!/usr/bin/env python3
from __future__ import annotations

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
POT = LANG_DIR / f"{DOMAIN}.pot"
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


def make_pot(version: str) -> None:
    sources = php_sources()
    if not sources:
        raise RuntimeError("No PHP sources found for translation extraction.")

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
        str(POT.relative_to(ROOT)),
        *sources,
    )

    pot = polib.pofile(str(POT))
    pot.metadata["Project-Id-Version"] = f"Core Blueprint Docs {version}"
    pot.metadata["Content-Type"] = "text/plain; charset=UTF-8"
    pot.metadata["Content-Transfer-Encoding"] = "8bit"
    pot.metadata.pop("POT-Creation-Date", None)
    pot.save(str(POT))


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


def sync_locale(locale: str, version: str, translations: dict[str, dict[str, str]]) -> list[str]:
    po_path = LANG_DIR / f"{DOMAIN}-{locale}.po"
    mo_path = LANG_DIR / f"{DOMAIN}-{locale}.mo"
    if not po_path.is_file():
        raise RuntimeError(f"Missing source catalog: {po_path.name}")

    po = polib.pofile(str(po_path))
    pot = polib.pofile(str(POT))
    po.merge(pot)
    po.metadata["Project-Id-Version"] = f"Core Blueprint Docs {version}"
    po.metadata["Language"] = locale
    po.metadata["Content-Type"] = "text/plain; charset=UTF-8"
    po.metadata["Content-Transfer-Encoding"] = "8bit"

    for entry in po:
        if entry.obsolete or entry.msgid_plural:
            continue
        localized = translations.get(entry.msgid, {}).get(locale)
        if localized is not None:
            entry.msgstr = localized

    missing: list[str] = []
    for entry in po:
        if entry.obsolete:
            continue
        if entry.msgid_plural:
            if not entry.msgstr_plural or any(not value.strip() for value in entry.msgstr_plural.values()):
                missing.append(entry.msgid)
        elif not entry.msgstr.strip():
            missing.append(entry.msgid)

    po.save(str(po_path))
    if not missing:
        po.save_as_mofile(str(mo_path))
    return missing


def main() -> int:
    if not which("xgettext"):
        print("Missing required command: xgettext", file=sys.stderr)
        return 2

    version = plugin_version()
    make_pot(version)
    translations = load_translation_map()

    all_missing: dict[str, list[str]] = {}
    for locale in LOCALES:
        missing = sync_locale(locale, version, translations)
        if missing:
            all_missing[locale] = missing

    if all_missing:
        print("Translation completeness check failed:", file=sys.stderr)
        for locale, msgids in all_missing.items():
            print(f"\n[{locale}]", file=sys.stderr)
            for msgid in sorted(set(msgids)):
                print(f"- {msgid}", file=sys.stderr)
        return 1

    print(f"Docs i18n sync: PASS ({version})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
