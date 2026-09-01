# Core Blueprint Docs

Core Blueprint Docs is a lightweight, builder-agnostic documentation extension for the Core Blueprint WordPress suite.

It exists to remove setup work: activate the plugin and immediately get a native WordPress documentation content model that can be edited with Gutenberg and queried or templated by Bricks, another builder, a theme, REST consumers or normal WordPress code.

## v0.1.0-rc1.1 scope

- Native public `cb_doc` post type.
- Configurable Docs URL base with `docs` as the default.
- Core Admin settings page for the URL base.
- Gutenberg and standard WordPress support for title, content, excerpt, author, featured image, revisions, custom fields, comments and menu order.
- Hierarchical `cb_doc_category` taxonomy.
- Non-hierarchical `cb_doc_tag` taxonomy.
- Registered native post meta:
  - `cb_docs_subtitle`
  - `cb_docs_status` (`current` or `deprecated`)
  - `cb_docs_version`
  - `cb_docs_last_reviewed`
  - `cb_docs_featured`
- WP-native Doc Details metabox for those standard documentation fields.
- Minimal builder-agnostic shortcodes.
- Hard dependency on the Core Blueprint Base public API `1.0`.
- Canonical Core Blueprint ExtensionRegistry and health registration.
- Canonical Governance events through `EventRegistry` and `Audit::record()`.
- No direct Core Blueprint Access dependency.
- No Bricks-specific runtime integration.
- No custom database tables or proprietary field storage.

## Permalinks

The default archive is `/docs/` and individual documents use `/docs/{doc-slug}/`.

Under **Core Blueprint → Docs**, administrators can change the URL base to values such as:

- `documentation`
- `handleiding`
- `knowledge-base`
- `knowledge/docs`

The value is normalized into safe WordPress slug segments. Empty or invalid input falls back to `docs`.

Changing the URL base marks rewrite rules dirty. Docs waits until the next `init`, after `cb_doc` has been registered with the new base, flushes rewrite rules once and removes the dirty marker. Rewrite rules are never flushed on every request.

Changing the URL base changes public archive and single URLs. Existing external links may therefore need redirects.

## Builder workflow

The intended builder workflow is deliberately native:

1. Query the `cb_doc` post type.
2. Query `cb_doc_category` and `cb_doc_tag` where needed.
3. Read normal WordPress fields and the registered `cb_docs_*` post meta.
4. Build archive and single templates in the builder of choice.

Bricks and other builders do not need a Core Blueprint Docs adapter when they can consume normal WordPress post types, taxonomies and meta.

## Core Blueprint Access

Docs does not call or depend on Core Blueprint Access.

Core Blueprint Access discovers public WordPress post types generically. Because `cb_doc` is a normal public post type, it becomes available in Access' content-type configuration. The administrator decides whether Docs belongs to the protected Access scope.

Shortcode queries keep WordPress filters enabled, so Access can apply its normal query policy when Docs is enrolled in Access.

## Shortcodes

- `[cb_docs_list]`
- `[cb_docs_navigation]`
- `[cb_docs_search]`
- `[cb_docs_breadcrumbs]`
- `[cb_docs_meta]`

See [`docs/SHORTCODES.md`](docs/SHORTCODES.md).

## Governance events

- `docs.document.created`
- `docs.document.published`
- `docs.document.updated`
- `docs.document.trashed`
- `docs.document.restored`
- `docs.document.deleted`
- `docs.settings.updated`

Autosaves, revisions and auto-drafts are excluded. Multiple document-field changes in one request collapse into a single `docs.document.updated` record with a `changed_fields` list. URL-base changes are recorded as settings governance events.

## Requirements

- WordPress 7.0+
- PHP 8.4+
- Core Blueprint Base with Core API `1.0` or a compatible newer `1.x` minor

If Base is missing or incompatible, Docs remains inert. Interactive activation is refused rather than creating a standalone fallback runtime.

## Development checks

```bash
php tools/conformance.php
find . -type f -name '*.php' -not -path './build/*' -print0 | xargs -0 -n1 php -l
```

Build instructions live in [`tools/README.md`](tools/README.md).
