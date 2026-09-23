# Architecture

## Product boundary

Core Blueprint Docs is a ready-made documentation content model, not a second content-model framework.

It owns one WordPress post type, two WordPress taxonomies and five registered post-meta fields. Customer content remains in standard WordPress storage.

## Base boundary

Docs requires Core Blueprint Base and consumes only public Base contracts:

- `CB_CORE_API_VERSION`
- `CB\Core\ExtensionRegistry`
- `CB\Core\Admin\SettingsRegistry`
- `CB\Core\UI\Assets`
- `CB\Core\UI\Card`
- `CB\Core\UI\Notice`
- `CB\Core\UI\IntegrationGrid`
- `CB\Core\Governance\EventRegistry`
- `CB\Core\Governance\Audit`

Docs requires Core API `1.1+` because the Documentation Organizer consumes the public Reorder Foundation. Docs configuration is contributed to the Core Blueprint Settings Hub through `SettingsRegistry`. The operational Documentation Organizer remains under the native Docs content menu and opts into the public Base Reorder Foundation through `CB\Core\UI\Assets::enqueue_reorder()` and the `@cb-core/reorder` module contract.

Base owns generic reorder interaction, focus, accessibility, pending state and rollback presentation. Docs owns documentation structure, authorization, WordPress persistence, stale-state protection and semantic audit events.

Docs does not use Base-private assets, repositories, `PageBase` or AuditLog internals.

## Content Models boundary

The optional Base Content Models module is not a Docs runtime dependency. Disabling Content Models must not disable the Docs content type or its fields.

Docs therefore registers its own fixed schema directly with the WordPress registration APIs.

## Organizer and structure boundary

Docs keeps WordPress as the canonical datastore. The Organizer is a management layer over the existing `cb_doc` post type and hierarchical `cb_doc_category` taxonomy, not a second content system.

Document order uses native `menu_order`. Category sibling order uses registered `cb_docs_order` term metadata. A deterministic structure revision covers only structural state so concurrent Organizer mutations can fail closed without treating ordinary content edits as structural conflicts.

The Organizer resolves Doc Category assignments hierarchy-aware. A document may be assigned to multiple categories when those terms all lie on one ancestor-to-descendant path; the deepest assigned term is the canonical structural location. Documents with no category are shown as Unassigned. Assignments across separate taxonomy branches are shown as Needs review and are never silently rewritten until an administrator chooses one structural location.

Category hierarchy remains managed through WordPress taxonomy management. Organizer v1 reorders category siblings but does not reparent categories by drag.

Organizer category disclosure is presentation-only state. Top-level categories default to expanded and nested categories default to collapsed. Per-category expand/collapse choices are stored in browser-local storage under a per-user key and are never written to WordPress content, options or user metadata. Expand all and Collapse all operate on the same local presentation state.

## Permalink boundary

Docs owns one public permalink domain with two explicit modes.

`Simple` is the backward-compatible default. `Category hierarchy` is opt-in and places category archives, tags and canonical document paths under the configured Docs URL base.

The permalink domain is split into four responsibilities:

- `Permalinks\\RouteIndex` is a pure route planner and collision/readiness authority.
- `Permalinks\\Catalog` projects native WordPress Docs/category state into the route planner and delegates structural category selection to `Structure\\StructuralCategory`.
- `Permalinks\\CanonicalPath` is the single URL-construction boundary for archive, category, tag and document links.
- `Permalinks\\Router` owns hierarchy rewrite registration, request resolution and deterministic legacy redirects.

The hierarchy router reserves `tag` and `document` as first path segments. Unsafe or unresolved document hierarchy routes use the deterministic `/{base}/document/{slug}/` fail-safe. Category hierarchy activation is blocked when the route index reports an unresolvable namespace conflict.

The stored Docs base is normalized as one or more safe WordPress slug segments. Changing the base or URL structure marks rewrite rules dirty. On the next `init`, the post type/taxonomies and hierarchy router register the new route contract first; the deferred flush then refreshes rewrite rules once and removes the marker.

Category slug, parent and document-assignment changes do not flush rewrite rules. The route catalog resolves current taxonomy state dynamically and invalidates its in-request cache on relevant content/taxonomy mutations.

Breadcrumbs, normal WordPress links, shortcodes and builder adapters must not implement independent permalink rules. They consume WordPress links or the canonical Docs permalink boundary.

The normative route contract, collision policy and legacy redirect rules are documented in `PERMALINKS.md`.

Activation and deactivation remain explicit rewrite-maintenance points. No unconditional request-time rewrite flushing is allowed.

## Adjacent navigation boundary

Previous/next document navigation is owned at the native WordPress adjacent-post layer, not by a builder adapter. For `cb_doc`, Docs maps the WordPress adjacent boundary and ordering from `post_date + ID` to Organizer `menu_order + ID` while preserving the existing WordPress WHERE suffix.

Candidates are restricted to documents whose canonical structural category resolves to the same Organizer location through `Structure\\StructuralCategory`. Unassigned or cross-branch ambiguous documents fail closed and expose no adjacent document.

Before candidate IDs reach the native adjacent-post SQL, Docs runs one bounded sibling-set query through the normal filtered read boundary with `perm=readable` and `suppress_filters=false`. This keeps compatible access-control policy in the path without adding a direct Core Blueprint Access dependency. WordPress then still applies its own post-status, private-post, same-term and excluded-term constraints.

Non-Docs post types are never modified. Themes and builders that call WordPress `get_previous_post()` / `get_next_post()` inherit the behavior automatically.

## Access boundary

Docs has no direct dependency on Core Blueprint Access. Access may attach its taxonomy to `cb_doc` through its generic public-post-type scope.

## Builder and presentation boundary

WordPress data and the builder-neutral Docs frontend contracts are the primary interface. Shortcodes are a minimal fallback/presentation API and intentionally produce small semantic markup with `cb-docs-*` classes. Docs does not take over theme templates.

Docs ships an optional Bricks adapter under `src/Integration/Builders/`. The adapter exposes Docs data, queries and conditions to Bricks, but delegates storage, access-aware reads, query policy and condition semantics to the builder-neutral Docs frontend layer. Bricks is never required for Docs to function and future builder adapters must be addable without redesigning the Docs domain.
