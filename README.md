# Core Blueprint Docs

Core Blueprint Docs is a lightweight, builder-agnostic documentation extension for the Core Blueprint WordPress suite.

It provides a native WordPress documentation content model that can be edited with Gutenberg and consumed by shortcodes, themes, normal WordPress code, REST consumers and optional builder adapters.

## v1.0.0-rc1 scope

- Native public `cb_doc` post type.
- Configurable Docs URL base with `docs` as the default.
- Golden Core Admin page with `Overview → General → Integrations`.
- Gutenberg and standard WordPress support for title, content, excerpt, author, featured image, revisions, custom fields, comments and menu order.
- Hierarchical `cb_doc_category` taxonomy with managed sibling ordering.
- Visual Documentation Organizer for category and article order using the public Base Reorder Foundation.
- Non-hierarchical `cb_doc_tag` taxonomy.
- Registered native post meta:
  - `cb_docs_subtitle`
  - `cb_docs_status` (`current` or `deprecated`)
  - `cb_docs_version`
  - `cb_docs_last_reviewed`
  - `cb_docs_featured`
- WP-native Doc Details metabox for documentation fields.
- Builder-neutral frontend data, query, search and condition contracts.
- Relevance-ordered live Docs search with a read-only REST endpoint and normal GET fallback.
- Builder-agnostic shortcodes.
- Optional Bricks adapter for Dynamic Data, custom Queries, Conditions and a dedicated Docs Search element.
- Hard dependency on the Core Blueprint Base public API `1.1` and the public Base contracts Docs consumes.
- Canonical Core Blueprint ExtensionRegistry and health registration.
- Canonical Governance events through `EventRegistry` and `Audit::record()`.
- No direct Core Blueprint Access dependency.
- No custom database tables or proprietary field storage.
- No search analytics, search-history storage or user tracking.

## Admin workflow

Core Blueprint owns the shared Core Admin presentation. Docs owns its domain semantics and keeps operational content management WordPress-native.

Under **Core Blueprint → Docs**:

- **Overview** shows Docs, draft, category and tag metrics plus the shortcode reference.
- **General** owns site-wide Docs configuration such as the public URL base.
- **Integrations** reports optional integration readiness through the Base `IntegrationGrid` contract.

Actual Docs, Categories and Tags remain on their normal WordPress content screens. **Docs → Organizer** adds a focused structure view for ordering documentation sets, sections and articles without replacing normal WordPress editing.

## Permalinks

The default archive is `/docs/` and individual documents use `/docs/{doc-slug}/`.

Under **Core Blueprint → Docs → General**, administrators can change the URL base to values such as:

- `documentation`
- `handleiding`
- `knowledge-base`
- `knowledge/docs`

The value is normalized into safe WordPress slug segments. Empty or invalid input falls back to `docs`.

Changing the URL base marks rewrite rules dirty. Docs waits until the next `init`, after `cb_doc` has been registered with the new base, flushes rewrite rules once and removes the dirty marker. Rewrite rules are never flushed on every request.

Changing the URL base changes public archive and single URLs. Existing external links may therefore need redirects.

## Search architecture

`CB\Docs\Frontend\Search` is the canonical bounded search provider. Search queries delegate to the public Docs query contract and use WordPress relevance ordering while keeping normal WordPress filters enabled so compatible access policy remains in the query path.

The frontend search component is progressive enhancement:

- `[cb_docs_search]` renders a normal GET search form and server-side results.
- When JavaScript is available, debounced live requests use the public read-only Docs search REST endpoint.
- Live responses contain only result data needed by the search UI, not full document content.
- Stale requests are aborted when the query changes.
- Arrow keys, Enter and Escape support keyboard navigation.
- If JavaScript or the endpoint is unavailable, the GET form remains functional.

Docs does not record search terms, IP addresses, user agents, search histories or analytics events.

## Builder architecture

Docs remains builder-neutral. The canonical frontend contracts live outside any builder adapter and own data projection, querying, search, conditions and access-aware document resolution.

The Bricks integration is optional and thin. When Bricks is active, Docs exposes:

- Core Blueprint Docs Dynamic Data tags;
- Docs document and search Queries;
- Docs Conditions;
- a dedicated **Docs Search** element backed by the same builder-neutral search component as the shortcode;
- Core Blueprint group ordering and Bricks loop/document context support.

The Bricks adapter delegates to the builder-neutral Docs providers. It does not own storage, authorization, business logic or independent query policy. Sites without Bricks continue to use the same Docs domain through WordPress, themes, shortcodes or future builder adapters.

Core Blueprint Docs intentionally does not duplicate generic builder features. Grids, cards, taxonomy presentation, table-of-contents layouts and other general composition remain the responsibility of Bricks or the active theme. Dedicated builder elements are reserved for Docs-specific behavior.

See [`docs/BRICKS.md`](docs/BRICKS.md) and [`docs/INTEGRATION-API.md`](docs/INTEGRATION-API.md) for repository-side integration documentation.

## Core Blueprint Access

Docs does not call or depend on Core Blueprint Access.

Core Blueprint Access discovers public WordPress post types generically. Because `cb_doc` is a normal public post type, it becomes available in Access content-type configuration. The administrator decides whether Docs belongs to the protected Access scope.

Docs queries keep WordPress filters enabled so external access policy can participate through normal WordPress contracts.

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
- `docs.structure.updated`

Autosaves, revisions and auto-drafts are excluded. Multiple document-field changes in one request collapse into a single `docs.document.updated` record with a `changed_fields` list. URL-base changes are recorded as settings Governance events.

Search requests do not create Governance events or analytics records.

## Requirements

- WordPress 7.0+
- PHP 8.4+
- Core Blueprint Base with Core API `1.1` or a compatible newer `1.x` minor and the public Base contracts Docs consumes

If Base is missing or incompatible, Docs remains inert. Interactive activation is refused rather than creating a standalone fallback runtime.

## Development checks

Run the canonical local validation gate from the repository root:

```bash
bash tools/check
```

The gate validates PHP and JavaScript syntax, localization, product conformance, focused smoke contracts and deterministic release packaging.

When translatable runtime strings change, run `tools/i18n/update`, review the catalog changes, then run `bash tools/check` again.

Release tooling is documented in [`tools/README.md`](tools/README.md).
