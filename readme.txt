=== Core Blueprint Docs ===
Contributors: coreblueprint
Tags: documentation, knowledge base, docs, gutenberg, builder
Requires at least: 7.0
Requires PHP: 8.4
Stable tag: 1.0.0-rc1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight builder-agnostic documentation using native WordPress content, taxonomies and metadata.

== Description ==

Core Blueprint Docs provides a ready-made native WordPress documentation model so sites can start authoring immediately without manually configuring a custom post type, taxonomies and common documentation fields.

Docs are normal WordPress content and can be edited in Gutenberg, consumed through builder-neutral frontend contracts and used by optional builder adapters. Bricks is the first supported adapter and is never required for Docs to function.

Docs Search provides relevance-ordered live documentation search with keyboard support and a normal GET fallback. The same search component is available through `[cb_docs_search]` and the optional Bricks Docs Search element. Docs does not store search analytics or search histories.

The Docs URL base is configurable under Core Blueprint > Docs > General. The default is `docs`; alternatives such as `documentation`, `handleiding` and nested paths such as `knowledge/docs` are supported. Simple URL structure remains the backward-compatible default. Category hierarchy mode can place category archives, tags and canonical document paths under the same Docs namespace after a route-readiness check.

Docs > Organizer provides a visual structure view for ordering Doc Categories and articles while normal WordPress screens remain canonical for editing content and taxonomy details.

Core Blueprint Base with Core API 1.1 is required.

== Installation ==

1. Install and activate a compatible Core Blueprint Base version.
2. Upload the canonical `core-blueprint-docs` plugin folder or release ZIP.
3. Activate Core Blueprint Docs.
4. Open Docs in WordPress admin and start authoring.
5. Optionally open Core Blueprint > Docs to review Overview, General and Integrations.

== Shortcodes ==

* `[cb_docs_list]`
* `[cb_docs_navigation]`
* `[cb_docs_search]`
* `[cb_docs_breadcrumbs]`
* `[cb_docs_meta]`

== Changelog ==

= 1.0.0-rc1 =
* First public release candidate.
* Added native Docs content, taxonomies, metadata, shortcodes and Governance lifecycle auditing.
* Added live relevance-ordered Docs search with progressive GET fallback and an optional Bricks Docs Search element.
* Added Golden Core Admin Overview, General and Integrations information architecture.
* Added a visual Documentation Organizer using the public Core Blueprint Reorder Foundation.
* Added builder-neutral data, query and condition contracts with an optional Bricks adapter.
* Added configurable Docs URL base with Simple and readiness-gated Category hierarchy URL structures, canonical fail-safe document routes and deterministic legacy redirects.
* Added reproducible conformance, localization and release packaging tooling.
