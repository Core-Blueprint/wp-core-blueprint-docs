# Release tooling

## Purpose

`tools/build-release` creates a canonical installable WordPress ZIP whose single root folder is always `core-blueprint-docs/`.

## Requirements

- Bash
- PHP CLI 8.4+
- `rsync`
- `zip`

## Usage

From the repository root:

```bash
bash tools/build-release
```

## Output

The script recreates `build/` and writes:

```text
build/core-blueprint-docs-<version>.zip
```

The ZIP contains exactly one plugin root folder:

```text
core-blueprint-docs/
```

The folder is never renamed to a branch, version or GitHub archive name.

## Validation and failure behavior

Before zipping, the script:

1. verifies required command-line tools;
2. stages the canonical plugin root;
3. runs PHP syntax validation over every staged PHP file;
4. runs `tools/conformance.php` against the staged source.

Any failed prerequisite or validation exits non-zero and no release should be treated as valid.

## Maintenance

When runtime files or source-only files are added, review the `rsync` exclusions. The canonical plugin slug and entry filename must stay synchronized with the plugin header and repository release process.
