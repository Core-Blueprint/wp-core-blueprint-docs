# Architecture

## Product boundary

Core Blueprint Docs is a ready-made documentation content model, not a second content-model framework.

It owns one WordPress post type, two WordPress taxonomies and five registered post-meta fields. Customer content remains in standard WordPress storage.

## Base boundary

Docs requires Core Blueprint Base and consumes only public Base contracts:

- `CB_CORE_API_VERSION`
- `CB\Core\ExtensionRegistry`
- `CB\Core\Admin\PageRegistry`
- `CB\Core\Admin\Page`
- `CB\Core\UI\Card`
- `CB\Core\UI\Notice`
- `CB\Core\UI\IntegrationGrid`
- `CB\Core\Governance\EventRegistry`
- `CB\Core\Governance\Audit`

The Docs Core Admin page is registered through `PageRegistry` and declares semantic Base components instead of private asset handles. Base owns shared presentation; Docs owns its information architecture and domain semantics.

Docs does not use Base-private assets, repositories, `PageBase` or AuditLog internals.

## Content Models boundary

The optional Base Content Models module is not a Docs runtime dependency. Disabling Content Models must not disable the Docs content type or its fields.

Docs therefore registers its own fixed schema directly with the WordPress registration APIs.

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
