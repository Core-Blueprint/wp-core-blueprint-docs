# Release tooling

## Purpose

`tools/build-release` creates the canonical installable Core Blueprint Docs release package. It is fail-closed: version drift, stale localization catalogs, syntax errors, conformance failures, package-boundary leaks or checksum failures stop the build.

## Requirements

- Bash
- PHP CLI 8.4+
- Python 3
- GNU gettext (`xgettext`)
- Python package `polib==1.2.0`
- `git`
- `zip` / `unzip`
- `sha256sum`

## Usage

From the repository root:

```bash
python3 tools/sync-i18n.py
bash tools/build-release
```

The build command reruns localization synchronization itself and fails if that process would change committed catalog files.

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
- `languages/`
- `src/`

Repository-only paths such as `.github/`, `tools/`, `tests/`, `docs/` and `build/` are excluded and explicitly rejected if they leak into the ZIP.

## Validation and failure behavior

Before a ZIP is accepted, the builder:

1. verifies all required command-line tools and Python dependencies;
2. requires public version `1.0.0-rc1` and checks plugin-header/runtime/readme/README/changelog consistency;
3. synchronizes POT/PO/MO catalogs and rejects uncommitted localization drift;
4. lints all PHP source with PHP 8.4+;
5. runs `tools/conformance.php`;
6. stages only the production package boundary;
7. creates the ZIP with canonical `core-blueprint-docs/` root;
8. rejects repository-only paths in the archive;
9. writes a SHA256 checksum next to the ZIP.

Any failed prerequisite or validation exits non-zero. A package from a failed run is not a valid release artifact.

## Maintenance

When runtime paths change, update the explicit copy list and package leak assertions together. When user-facing strings change, update the translation map and regenerate all six launch-locale catalogs before building. The canonical plugin slug, entry filename and public release version must remain synchronized across runtime and release metadata.
