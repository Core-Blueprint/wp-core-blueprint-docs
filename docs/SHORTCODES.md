# Shortcodes

The shortcode layer is intentionally small. Builders may ignore it and consume the native WordPress model directly.

## `[cb_docs_list]`

Lists published docs ordered by native `menu_order` and then title.

Attributes:

- `category="slug"` — optional Doc Category slug.
- `tag="slug"` — optional Doc Tag slug.
- `limit="20"` — 1–100, default 20.
- `excerpt="true"` — show or hide excerpts.

## `[cb_docs_navigation]`

Renders the hierarchical Doc Category tree and the published docs directly assigned to each category.

Attribute:

- `category="slug"` — optional category whose children become the navigation root.

## `[cb_docs_search]`

Renders a GET search form and scoped documentation results.

Attributes:

- `placeholder="..."`
- `limit="20"` — 1–100, default 20.

The query parameter is `cb_docs_q`.

## `[cb_docs_breadcrumbs]`

Renders archive/category/single breadcrumbs for Docs contexts.

A doc can have multiple categories. For a deterministic single breadcrumb path, Docs selects the assigned category with the deepest hierarchy; ties use the lowest term ID.

## `[cb_docs_meta]`

Renders basic documentation metadata for the current doc.

Optional attribute:

- `id="123"` — render metadata for a specific Doc ID.
