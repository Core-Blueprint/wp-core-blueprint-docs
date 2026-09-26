# Core Blueprint Docs tooling

Docs uses the suite-owned canonical first-party localization workflow, one read-only product check gate and one fail-closed customer release entrypoint.

## Product checks

Run the canonical read-only quality gate with:

```bash
./tools/check
```

The gate validates release identity, PHP syntax, shipped JavaScript syntax, canonical localization, Docs conformance and every repository-owned Docs/Bricks smoke or regression test. It does not build a customer ZIP or mutate release-visible source.

## Localization

English source is authority. POT is generated from source and the six reviewed PO files are translation authority.

Use only:

```bash
./tools/i18n/update
./tools/i18n/check
```

`tools/i18n/update` is the only mutating catalog command. `tools/i18n/check` is read-only.

MO files are runtime build artifacts. They are not committed to the repository and are compiled fresh from reviewed PO files during release packaging.

Do not add product-specific translation maps, live machine translation, alternate sync scripts or any other second translation authority.

## Customer release

Build the accepted customer artifact with:

```bash
./tools/build-release
```

The builder first requires `./tools/check` to pass, then stages only the explicit runtime/public release surface, compiles locale MO files in isolated staging, validates the staged PHP and JavaScript, normalizes timestamps, creates the canonical `core-blueprint-docs/` ZIP root, rejects development-only paths and emits a SHA-256 checksum.

The builder is read-only with respect to release-visible source.

## Output

Accepted repository-local release artifacts are written only to:

```text
dist/core-blueprint-docs-1.0.0-rc1.zip
dist/core-blueprint-docs-1.0.0-rc1.zip.sha256
```

`build/` is not an accepted artifact destination. It may only be used as disposable local state if a future workflow genuinely requires it.

## Production package boundary

The installable package contains:

- `core-blueprint-docs.php`
- `uninstall.php`
- `readme.txt`
- `README.md`
- `CHANGELOG.md`
- `assets/`
- `languages/` with POT, reviewed PO and freshly compiled MO files
- `src/`

Repository-only paths such as `.git/`, `.github/`, `tools/`, `tests/`, `docs/`, `dist/`, `build/`, `.venv/`, `vendor/` and `node_modules/` are rejected if they leak into the ZIP.

A successful archive build is package evidence only. Manual WordPress/runtime field validation remains a separate release gate before merge.
