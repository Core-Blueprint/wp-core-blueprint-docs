# Core Blueprint Docs Integration API

This document defines the builder-neutral public read/search/condition surface for Core Blueprint Docs. Consumers such as Core Blueprint CRM, Core Blueprint Helpdesk and builder adapters should use these contracts instead of depending on Docs' internal `WP_Query` shape or admin implementation.

WordPress Admin remains the canonical complete management interface. This first public contract release is read-oriented; it does not expose generic post/content mutation.

## Authorization model

Docs remains the owner of document content, taxonomies and metadata.

Public collection queries:

- query the `cb_doc` post type with `post_status=publish`;
- use WordPress `perm=readable`;
- keep WordPress query filters enabled (`suppress_filters=false`) so compatible access-control extensions can restrict results;
- project every returned object through the same protected-content boundary before returning it.

Exact reads are resolved through a filtered WordPress query and protected-content handling. Missing and unauthorized exact reads intentionally return the same `docs_document_not_found` error so callers cannot infer document existence from an authorization failure.

A CRM/Docs relationship is not read authorization. Consumers must always resolve the document again through this Docs API before presenting it to a user.

## Public document data

Use `CB\Docs\Frontend\Data\Document`.

```php
$document = \CB\Docs\Frontend\Data\Document::get( 123 );
$current  = \CB\Docs\Frontend\Data\Document::current();
$title    = \CB\Docs\Frontend\Data\Document::value( 'title', 123 );
```

The projection contains:

- `id`
- `title`
- `permalink`
- `excerpt`
- `content` — raw authored WordPress content, only after the document read gate passes; consumers own output rendering/escaping
- `categories` — term projections with `id`, `name`, `slug`
- `tags` — term projections with `id`, `name`, `slug`
- `subtitle`
- `documentation_status` — `current` or `deprecated`
- `version`
- `last_reviewed`
- `featured`

`fields()` returns the documented field identifiers.

## Public document query

Use `CB\Docs\Frontend\Queries\Documents::query()`.

```php
$result = \CB\Docs\Frontend\Queries\Documents::query( [
    'category'    => 'getting-started',
    'tag'         => 'wordpress',
    'search'      => 'backup',
    'include_ids' => [ 10, 20, 30 ],
    'page'        => 1,
    'per_page'    => 20,
] );
```

Supported arguments:

- `category` — category slug
- `tag` — tag slug
- `search` — WordPress content search text
- `include_ids` — optional document ID allow-list
- `page` — minimum `1`
- `per_page` — bounded to `1..100`, default `20`

The return shape is:

```php
[
    'items'       => [ /* public document projections */ ],
    'page'        => 1,
    'per_page'    => 20,
    'total'       => 42,
    'total_pages' => 3,
]
```

The older `CB\Docs\Frontend\Queries::docs()` helper remains available for Docs' own native shortcodes. Cross-extension consumers should use the projection API above rather than relying on raw `WP_Query` objects.

## Public search

Use `CB\Docs\Frontend\Search::documents()` for bounded article lookup suitable for Helpdesk article search, CRM relation pickers and builder autocomplete/query adapters.

```php
$result = \CB\Docs\Frontend\Search::documents(
    'migration',
    20,
    [ 'category' => 'operations' ]
);
```

Search limits are bounded to `1..50`. An empty search term returns an empty result set and never falls back to an unfiltered catalogue query.

## Builder-neutral conditions

Use `CB\Docs\Frontend\Conditions\Documents`.

Available first-release conditions:

```php
\CB\Docs\Frontend\Conditions\Documents::is_current();
\CB\Docs\Frontend\Conditions\Documents::in_category( 'operations' );
\CB\Docs\Frontend\Conditions\Documents::has_tag( 'backup' );
\CB\Docs\Frontend\Conditions\Documents::user_can_read();
```

`in_category()`, `has_tag()` and `user_can_read()` accept an optional document ID. Without one they resolve the current queried/global WordPress document context.

Conditions are display/query helpers only. They do not grant access and never replace server-side authorization.

## Write contracts

Phase 3 intentionally does **not** expose unrestricted create/update/content actions. Safe metadata/taxonomy actions may be added later behind explicit authorization and canonical application services. Generic block/content editing through builder forms remains outside this contract.

## Inactive integrations

Docs does not depend on CRM, Helpdesk or a page builder. Consumers must feature-detect Docs before loading optional adapter code. Deactivating a consumer must not affect Docs content, and deactivating Docs must not cause a consumer to call these classes unguarded.
