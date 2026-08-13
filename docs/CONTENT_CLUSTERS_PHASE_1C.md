# Content Clusters Phase 1C — article continuation contract

## Placement and responsibility

`Continua il percorso` is rendered after the article body and before newsletter/related-content modules. It is structural navigation, not a replacement for contextual links inside the article body.

Internal Linking V2 remains responsible for editorially reviewed contextual suggestions. Phase 1C does not auto-insert previous/next links into article bodies and does not change Internal Linking scoring. Cluster-affinity scoring is intentionally deferred because adding it safely would require batch candidate membership loading inside the existing ranking pipeline; a per-candidate lookup would introduce N+1 behavior and a larger refactor would broaden Phase 1C unnecessarily.

## Public navigation contract

The box selects an active primary Content Cluster when one exists. If an article has no primary membership, it uses a deterministic fallback among active memberships: cluster `sort_order`, then name, then id. It never selects an inactive cluster.

Progress and adjacent links are calculated from the cluster's manual pivot ordering after applying the same `Article::published()` contract used by public Percorso pages. Draft, review and future scheduled articles do not affect X/Y and cannot appear as previous/next targets.

## Analytics foundation

The repository currently controls GA4 loading centrally through `AnalyticsExclusionService`, but it does not expose a reusable frontend event-dispatch API. Phase 1C therefore does not add direct `gtag()` calls.

The rendered box exposes non-personal data attributes for a future central analytics adapter:

- `data-path-slug`
- `data-article-id`
- `data-path-position`
- `data-path-total`
- `data-path-event` on navigation links

Candidate event contract:

- `path_box_view`
- `path_previous_click`
- `path_next_click`
- `path_view_all_click`

Allowed event metadata: `path_slug`, `article_id`, `position`, `total`. No user identifiers or personal data are required.

## Second-reading metric

A future analytics implementation should measure the sequence `article A -> path_next_click/path_previous_click -> article B` as a second-reading transition. Phase 1C deliberately does not create a dashboard or persistence layer for this metric.

## SEO and structured data

The box uses ordinary crawlable anchor links. It does not alter the article canonical, title, description, Article structured data or legacy `rel=prev/next` metadata. No new structured-data relationship between Article and Content Cluster is emitted in Phase 1C.

## Phase 2 technical plan

Phase 2 remains repository/design work until separately authorized. Priorities:

1. editorial health/completeness: clusters with missing pillar, too few public members, duplicated or missing ordering;
2. coverage: percentage/count of published articles with primary and secondary cluster membership, without inventing production values;
3. orphan discovery: published articles with no Content Cluster membership;
4. suggested memberships: optional editor-reviewed suggestions, never automatic production writes;
5. cluster performance: consume the analytics event contract once a central event API exists;
6. search/discovery: evaluate exposing Percorsi as a distinct search/discovery surface only after real usage evidence;
7. structured data: add only schema supported by the public content model and search-engine guidance, avoiding speculative markup;
8. large-catalog admin UX: evolve from the current catalog table toward searchable/paginated membership selection while preserving the selected-only submission contract.

Any initial editorial activation/backfill should use explicit article slugs, be deterministic and idempotent, default to dry-run, require an explicit apply flag, print cluster/article/position/primary/pillar decisions, and avoid fuzzy title matching. No production backfill is part of Phase 1C.
