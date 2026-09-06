# Release tooling

## Purpose

`tools/build-release` creates the canonical installable Core Blueprint Docs release package. It is fail-closed: version drift, incomplete localization, syntax errors, conformance failures, package-boundary leaks or checksum failures stop the build.

## Requirements

- Bash
- PHP CLI 8.4+
- Python 3
- GNU gettext (`xgettext`)
- Python package `polib==1.2.0`
- `zip` / `unzip`
- `sha256sum`

## Usage

From the repository root:

```bash
bash tools/build-release
```

For a standalone localization preview without mutating source catalogs:

```bash
python3 tools/sync-i18n.py --output-dir build/i18n-preview
```

## Localization model

The committed locale PO files provide the existing translation baseline. Repository translation maps such as `tools/i18n-translations-golden.json` provide explicit overlays for newly introduced or corrected runtime strings.

`tools/sync-i18n.py` extracts the current runtime strings, merges the baseline translations, applies the translation-map overlays, removes obsolete entries from generated output, hard-fails on any untranslated current string and compiles MO files.

Generated POT/PO/MO files are written to the selected output directory. The release builder writes them directly into the staged customer package, so release catalogs are reproducible without mutating source-controlled binary files.

## Output

For the current first public release candidate the builder writes:

```text
build/core-blueprint-docs-1.0.0-rc1.zip
build/core-blueprint-docs-1.0.0-rc1.zip.sha256
```

The ZIP has exactly one canonical plugin root:

```text
core-blueprint-docs/
```

The plugin root is never renamed to a branch, tag, version or GitHub archive name.

## Production package boundary

The installable package contains only the release-facing plugin surface:

- `core-blueprint-docs.php`
- `uninstall.php`
- `readme.txt`
- `README.md`
- `CHANGELOG.md`
- `assets/`
- generated `languages/`
- `src/`

Repository-only paths such as `.github/`, `tools/`, `tests/`, `docs/` and `build/` are excluded and explicitly rejected if they leak into the ZIP.

## Validation and failure behavior

Before a ZIP is accepted, the builder:

1. verifies all required command-line tools and Python dependencies;
2. requires public version `1.0.0-rc1` and checks plugin-header/runtime/readme/README/changelog consistency;
3. lints all PHP source with PHP 8.4+;
4. runs `tools/conformance.php`;
5. stages only the production package boundary;
6. generates current POT/PO/MO catalogs inside the staged package and requires all six launch locales to be complete;
7. creates the ZIP with canonical `core-blueprint-docs/` root;
8. rejects repository-only paths in the archive;
9. writes a SHA256 checksum next to the ZIP.

Any failed prerequisite or validation exits non-zero. A package from a failed run is not a valid release artifact.

## Maintenance

When runtime paths change, update the explicit copy list and package leak assertions together. When user-facing strings change, add or update the relevant translation-map entries so all six launch locales remain complete. The canonical plugin slug, entry filename and public release version must remain synchronized across runtime and release metadata.
