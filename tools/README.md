# Release tooling

## Purpose

`tools/build-release` creates the canonical installable Core Blueprint Docs release package. It is fail-closed: version drift, incomplete localization, syntax errors, conformance failures, package-boundary leaks or checksum failures stop the build.

## Canonical local check

Run the complete repository-owned closure gate with:

```bash
bash tools/check
```

This gate validates source syntax, canonical localization, product conformance, focused smoke contracts and deterministic release packaging. It is the preferred local check before a release branch or pull request is considered stable.

## Requirements

- Bash
- PHP CLI 8.4+
- Python 3
- WP-CLI with `wp i18n`
- GNU gettext (`msgfmt`; `msgmerge` and `msgattrib` are also required when updating catalogs)
- `zip` / `unzip`
- `sha256sum`

## Canonical localization workflow

English source is authoritative. The repository owns the release catalogs in `languages/`.

Use the canonical operator entrypoints only:

```bash
tools/i18n/update
tools/i18n/check
```

`tools/i18n/update` regenerates the POT from current source, synchronizes the six reviewed PO catalogs, removes obsolete entries, validates placeholders and completeness, and rebuilds committed MO artifacts for Docs.

`tools/i18n/check` is read-only. It proves source/POT equality, required locale coverage, current metadata, no fuzzy or untranslated release strings, placeholder validity, shared Core Blueprint translations and reproducible MO artifacts.

There is no separate compatibility sync command, translation-map overlay, xgettext workflow, polib authority or alternate catalog generator. Reviewed PO files remain translation source; POT and MO files are generated/reproducible artifacts under the canonical Core Blueprint i18n contract.

## Release build

From the repository root:

```bash
bash tools/build-release
```

The release builder runs `tools/i18n/check` before copying catalogs into the package. It never repairs or mutates localization during packaging. A stale or incomplete catalog therefore fails before a release ZIP is accepted.

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

1. verifies required command-line tools;
2. requires public version `1.0.0-rc1` and checks plugin-header/runtime/readme/README/changelog consistency;
3. lints all PHP source with PHP 8.4+;
4. runs `tools/conformance.php`;
5. runs canonical `tools/i18n/check`;
6. stages only the production package boundary and committed verified catalogs;
7. creates the ZIP with canonical `core-blueprint-docs/` root;
8. rejects repository-only paths in the archive;
9. writes a SHA256 checksum next to the ZIP.

Any failed prerequisite or validation exits non-zero. A package from a failed run is not a valid release artifact.

## Maintenance

When runtime paths change, update the explicit copy list and package leak assertions together. When user-facing strings change, run `tools/i18n/update`, review every changed translation, then require `tools/i18n/check` to pass. Do not introduce a second localization authority or compatibility entrypoint before launch.
