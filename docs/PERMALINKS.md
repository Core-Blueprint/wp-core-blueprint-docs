# Docs Public URL Contract

Status: hierarchy-mode implementation contract.

## Modes

Core Blueprint Docs exposes two supported public URL structures.

### Simple

Simple is the backward-compatible default for existing and new installations.

- Docs archive: `/{base}/`
- Doc: `/{base}/{document-slug}/`
- Doc Category: `/docs-category/{hierarchical-category-path}/`
- Doc Tag: `/docs-tag/{tag-slug}/`

Changing neither the plugin version nor installing an update silently opts a site into hierarchy mode.

### Category hierarchy

The configured Docs URL base becomes the namespace for all public documentation URLs.

- Docs archive: `/{base}/`
- Category: `/{base}/{hierarchical-category-path}/`
- Doc Tag: `/{base}/tag/{tag-slug}/`
- Safe Doc: `/{base}/{hierarchical-category-path}/{document-slug}/`
- Fail-safe Doc: `/{base}/document/{document-slug}/`

`tag` and `document` are reserved as first path segments for hierarchy mode.

The exact Docs URL bases `docs-category` and `docs-tag` remain valid in Simple mode but are rejected for Category hierarchy because they overlap the legacy redirect namespaces.

## Structural source of truth

Document category resolution uses `Structure\\StructuralCategory::resolve()`.

If multiple assigned Doc Categories all lie on one ancestor-to-descendant path,
the deepest assigned term is the structural category. If assignments span
multiple branches, the document is ambiguous and must not receive a guessed
hierarchy path.

Organizer, breadcrumbs, canonical links and public routes must use the same
structural resolver.

## Fail-safe document route

A document uses `/{base}/document/{slug}/` when no safe hierarchy canonical
path can be proven. This includes:

- no structural category;
- ambiguous structural category;
- hierarchy path collision with a category.

The fail-safe is deterministic and never relies on alphabetical or database
return order.

## Public namespace ownership

The configured Docs base never overrides an existing site namespace.

Before a permalink base is saved, Docs rejects:

- WordPress-reserved roots such as `wp-admin`, `wp-json`, `wp-content` and `wp-includes`;
- an existing WordPress Page at the same path;
- an evident public post-type archive using the same base;
- an evident public taxonomy rewrite using the same base.

The previous Docs setting is retained when a candidate conflicts. Docs never
chooses `docs-2` or another automatic replacement slug.

At runtime, an existing WordPress Page or reserved WordPress root has priority.
Docs pauses archive/single pretty routes under the configured base, and hierarchy
routing/canonical redirects fail closed until the namespace becomes available.
The content model and wp-admin remain available.

## Route collisions

Hierarchy activation is blocked when the route index contains a conflict that
cannot be handled safely:

- top-level category slug `tag` or `document`;
- duplicate category paths;
- duplicate public document canonical/fail-safe paths;
- duplicate routable document slugs that would make a legacy Simple URL ambiguous;
- a legacy Simple document URL that would become a category URL.

A category/document collision at a proposed hierarchy document path is not
blocking because the document receives the fail-safe `document/` route.

## Readiness

Before Category hierarchy can be enabled, Docs computes a route-readiness
snapshot. Warnings may be served safely through fail-safe document routes.
Blocking conflicts prevent activation.

Readiness distinguishes at least:

- unresolved/unassigned documents;
- ambiguous document assignments;
- category/document hierarchy collisions;
- reserved top-level category slugs;
- duplicate category paths;
- duplicate document routes;
- legacy Simple URL collisions.

## Legacy redirects

When hierarchy mode is active, legacy public routes may 301 redirect only when
the old request resolves to exactly one current object and the current canonical
destination is safe.

Supported legacy sources:

- `/{base}/{document-slug}/` from Simple mode;
- `/docs-category/{hierarchical-category-path}/`;
- `/docs-tag/{tag-slug}/`.

No heuristic redirect is emitted for an ambiguous legacy request.

## Rewrite refresh

Rewrite rules are flushed only when:

- Docs URL base changes;
- Document URL structure changes; or
- the runtime namespace state changes between available and blocked.

Category slug, hierarchy or assignment changes are resolved dynamically and do
not trigger rewrite flushing.

## Integration boundary

Bricks, shortcodes and templates must not implement permalink logic. They use
normal WordPress links or the canonical Docs permalink service.
