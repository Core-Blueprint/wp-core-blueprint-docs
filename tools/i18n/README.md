# First-party i18n reference implementation

This directory is the canonical implementation owned by the Core Blueprint First-Party Starter. The Engineering Handbook owns the policy; the Starter owns the executable reference implementation.

## Ownership rule

First-party product repositories copy these files byte-for-byte from the Starter:

- `catalog.py`
- `reference.json`
- `check-reference`
- `update`
- `check`

Products own only `config.json` plus narrowly bounded runtime adapters that are explicitly allowed by the Golden Standard. Do not patch `catalog.py` inside a product repository. If a new capability is required, change the Starter first, bump `I18N_TOOLING_VERSION`, refresh `reference.json`, review the Starter change, and only then propagate the new canonical implementation.

`config.json.example` documents the allowed product-specific configuration surface. Copy it to `tools/i18n/config.json` during the identity pass and replace every placeholder value.

## Canonical commands

```bash
tools/i18n/check-reference
tools/i18n/update
tools/i18n/check
```

`check-reference` requires only Python and detects implementation drift without needing WP-CLI or GNU gettext.

`update` is the only canonical mutating catalog command. It stages the complete update transactionally, extracts a fresh POT from source with WordPress i18n tooling, merges the reviewed PO sources, removes obsolete entries, applies the suite-owned canonical dependency translations, validates formats and regenerates configured runtime artifacts. Tracked catalogs are replaced only after every configured locale passes, so a failed update leaves the committed catalog set unchanged.

`check` is read-only. It proves source/POT/PO parity, reviewed translation completeness, canonical dependency-copy translations, and reproducibility of any committed MO or static WordPress `*.l10n.php` artifacts.

## Required release toolchain

Catalog update/check requires:

- Python 3;
- WP-CLI with the `wp i18n` commands;
- GNU gettext `msgmerge`, `msgattrib` and `msgfmt`;
- Git.

A release cannot be declared localization-PASS merely because `check-reference` passes. The product's real `tools/i18n/check` must pass in the supported release environment.

## Translation authority

The hierarchy is fixed:

1. English source strings are product source authority.
2. POT is generated from source.
3. PO is the reviewed translation authority.
4. MO, JS JSON and static `*.l10n.php` files are generated runtime artifacts.

A PO-backed runtime adapter may remain product-specific when it contains no independent translations and is regression-tested. Live machine translation is never a catalog authority.

WordPress plugin metadata remains part of the normal POT surface. For non-linguistic identity fields such as Plugin Name, Plugin URI, Author and Author URI, `update` may fill an empty translation with the source value as a safe identity default. Existing reviewed translations are never overwritten. Linguistic metadata such as Description remains subject to the normal completeness gate and must be reviewed like any other translatable string.
