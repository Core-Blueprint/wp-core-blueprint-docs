# Changelog

## 0.1.0-rc1.1

- Added a Core Admin Docs settings page.
- Added configurable Docs URL base with `docs` as the safe default.
- Added support for nested rewrite bases such as `knowledge/docs`.
- Added deferred one-time rewrite flushing only when the URL base actually changes.
- Added `docs.settings.updated` Governance auditing for URL-base changes.
- Extended source conformance checks for settings and Core Admin contracts.

## 0.1.0-rc1

- Initial Core Blueprint Docs release candidate.
- Added native `cb_doc` post type with Gutenberg, revisions, comments, custom fields and menu ordering.
- Added hierarchical Doc Categories and Doc Tags.
- Added five registered documentation meta fields and WP-native Doc Details editing UI.
- Added minimal builder-agnostic list, navigation, search, breadcrumb and metadata shortcodes.
- Added Core Blueprint Base dependency gate, extension registration and health status.
- Added canonical Governance lifecycle events with autosave/revision noise filtering.
- Added release packaging and source conformance tooling.
