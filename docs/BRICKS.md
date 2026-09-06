# Bricks adapter

Core Blueprint Docs remains builder-agnostic. Bricks is an optional adapter that boots only when Bricks is available; Docs continues to work through WordPress Admin, native WordPress content, shortcodes, and its public frontend contracts when Bricks is inactive.

## Dynamic Data

Group: **Core Blueprint Docs**

- `{cb_docs_id}` — Doc ID.
- `{cb_docs_title}` — Doc title.
- `{cb_docs_url}` — Doc permalink.
- `{cb_docs_excerpt}` — Doc excerpt.
- `{cb_docs_content}` — native Doc content value from the public Docs data contract.
- `{cb_docs_categories}` — comma-separated category names.
- `{cb_docs_tags}` — comma-separated tag names.
- `{cb_docs_subtitle}` — subtitle.
- `{cb_docs_status}` — documentation status.
- `{cb_docs_version}` — optional documentation/product version.
- `{cb_docs_last_reviewed}` — last-reviewed date.
- `{cb_docs_featured}` — `1` when featured, otherwise empty.

The adapter resolves the current Bricks loop object first. It supports both projected Docs query results and native `cb_doc` post-loop objects. Outside a loop it falls back to the public current-document contract. Unauthorized or missing documents resolve to empty Dynamic Data values.

All Core Blueprint Dynamic Data groups are normalized into one contiguous block while preserving the relative order of Core Blueprint groups and unrelated Bricks groups.

## Query Loop types

### Docs: Documents

Object type: `cb_docs_documents`

Returns projected, readable published Docs through `CB\Docs\Frontend\Queries\Documents`. Result limits remain bounded by the public provider. When Bricks supplies standard count/page settings, the adapter forwards them to the public Docs query.

The adapter also accepts category, tag, search and include-ID values when those keys are present in the Bricks query settings. Authorization and filtering remain owned by the public Docs provider rather than Bricks.

### Docs: Search Results

Object type: `cb_docs_search_results`

Uses the public `CB\Docs\Frontend\Search` provider and the existing public Docs search parameter `cb_docs_q`. An empty search term returns no documents rather than the full Docs catalog. Results are bounded to the public search maximum.

## Element Conditions

Group: **Core Blueprint Docs**

- **Current item is a Doc**
- **User can read current Doc**
- **Doc category** — accepts category slug or term ID; supports `is` / `is not`.
- **Doc tag** — accepts tag slug or term ID; supports `is` / `is not`.

Conditions are display logic only. They never grant access. The adapter calls the builder-neutral Docs condition/read contracts, and protected or unreadable documents fail closed.

## Form Actions

Core Blueprint Docs `v1.0.0-rc1` does **not** register a Docs write Form Action. The current public Docs contracts expose safe reads/search/conditions but no canonical frontend mutation service. Adding direct post/meta writes inside the Bricks adapter would duplicate WordPress/Docs authorization and mutation semantics and would violate the suite rule that builder actions must call canonical builder-neutral services.

WordPress Admin therefore remains the canonical Docs editing interface. A future Form Action may be added only after an explicit, authorization-aware Docs application action exists.

## Dependency boundary

No Bricks class or hook is referenced by Docs domain/frontend code. Bricks references are confined to `src/Integration/Builders/` and are inert when Bricks is unavailable.
