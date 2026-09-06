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

Renders the canonical Core Blueprint Docs search component.

With JavaScript available, the component performs debounced live search through the public read-only Docs search endpoint. Results use the builder-neutral Docs search provider and therefore keep normal WordPress query filters and compatible access policy in the query path.

Without JavaScript, or when the live endpoint is unavailable, the same form remains a normal GET search using the `cb_docs_q` query parameter. Server-rendered fallback results use the same canonical search provider.

Attributes:

- `placeholder="..."` — search input placeholder.
- `limit="20"` — 1–50, default 20.
- `min_chars="2"` — 1–10, default 2; minimum input length before live search starts.
- `excerpt="true"` — show or hide result excerpts.
- `show_category="true"` — show or hide the first result category.
- `category="slug"` — optional category scope.
- `tag="slug"` — optional tag scope.

Search requests are not analytics events and Docs does not store search terms, IP addresses, user agents or search histories.

## `[cb_docs_breadcrumbs]`

Renders archive/category/single breadcrumbs for Docs contexts.

A doc can have multiple categories. For a deterministic single breadcrumb path, Docs selects the assigned category with the deepest hierarchy; ties use the lowest term ID.

## `[cb_docs_meta]`

Renders basic documentation metadata for the current doc.

Optional attribute:

- `id="123"` — render metadata for a specific Doc ID.
