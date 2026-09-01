# Architecture

## Product boundary

Core Blueprint Docs is a ready-made documentation content model, not a second content-model framework.

It owns one WordPress post type, two WordPress taxonomies and five registered post-meta fields. Customer content remains in standard WordPress storage.

## Base boundary

Docs requires Core Blueprint Base and consumes only public Base contracts:

- `CB_CORE_API_VERSION`
- `CB\Core\ExtensionRegistry`
- `CB\Core\Governance\EventRegistry`
- `CB\Core\Governance\Audit`

Docs does not use Base-private assets, repositories or AuditLog internals.

## Content Models boundary

The optional Base Content Models module is not a Docs runtime dependency. Disabling Content Models must not disable the Docs content type or its fields.

Docs therefore registers its own fixed schema directly with the WordPress registration APIs.

## Access boundary

Docs has no direct dependency on Core Blueprint Access. Access may attach its taxonomy to `cb_doc` through its generic public-post-type scope.

## Presentation boundary

WordPress data is the primary interface. The shortcodes are a minimal fallback/presentation API and intentionally produce small semantic markup with `cb-docs-*` classes. Docs does not take over theme templates and does not ship a builder-specific integration.
