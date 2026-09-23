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

## Permalink boundary

`cb_doc` uses a plugin-owned configurable rewrite base. The default is `docs`.

The stored base is normalized as one or more safe WordPress slug segments, so both `documentation` and paths such as `knowledge/docs` are valid. Empty or invalid input resolves to `docs`.

Changing the base does not flush rewrite rules inside the settings save request because the post type was registered earlier in that request with the previous base. Instead Docs stores a rewrite-dirty marker. On the next `init`, after `cb_doc` has registered with the new base, Docs flushes rewrite rules once and removes the marker.

Activation and deactivation remain explicit rewrite-maintenance points. No request-time unconditional rewrite flushing is allowed.

## Access boundary

Docs has no direct dependency on Core Blueprint Access. Access may attach its taxonomy to `cb_doc` through its generic public-post-type scope.

## Builder and presentation boundary

WordPress data and the builder-neutral Docs frontend contracts are the primary interface. Shortcodes are a minimal fallback/presentation API and intentionally produce small semantic markup with `cb-docs-*` classes. Docs does not take over theme templates.

Docs ships an optional Bricks adapter under `src/Integration/Builders/`. The adapter exposes Docs data, queries and conditions to Bricks, but delegates storage, access-aware reads, query policy and condition semantics to the builder-neutral Docs frontend layer. Bricks is never required for Docs to function and future builder adapters must be addable without redesigning the Docs domain.
