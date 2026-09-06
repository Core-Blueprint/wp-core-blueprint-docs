# Changelog

## 1.0.0-rc1

First public release candidate.

- Added native `cb_doc` content with Gutenberg, revisions, comments, custom fields and menu ordering.
- Added hierarchical Doc Categories and Doc Tags.
- Added registered native documentation metadata and the WP-native Doc Details editing UI.
- Added builder-neutral frontend data, query, search and condition contracts.
- Added builder-agnostic list, navigation, live search, breadcrumb and metadata shortcodes.
- Added relevance-ordered progressive Docs search with a read-only REST endpoint, debounced live results, keyboard navigation and normal GET fallback.
- Added an optional Bricks adapter for Dynamic Data, custom Queries, Conditions, group ordering and document context.
- Added a dedicated Bricks Docs Search element that delegates to the builder-neutral search component while leaving generic layout and presentation to Bricks.
- Added Golden Core Admin `Overview → General → Integrations` information architecture while keeping content management WordPress-native.
- Added configurable Docs URL base with safe nested paths and deferred one-time rewrite flushing.
- Added Core Blueprint Base public API/contract dependency guards, ExtensionRegistry integration and health status.
- Added canonical Governance lifecycle and settings events with autosave/revision noise filtering.
- Added six launch locales: Dutch, German, French, Spanish, Italian and Portuguese.
- Added source conformance, localization synchronization and reproducible release packaging tooling.

Internal `0.1.0-rc*` builds were pre-public development iterations and are intentionally consolidated into this first public `1.0.0-rc1` line.
