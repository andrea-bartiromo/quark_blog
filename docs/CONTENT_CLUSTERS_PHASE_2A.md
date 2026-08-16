# Content Clusters Phase 2A — editorial activation

Phase 2A is repository-only tooling. It does not activate, deploy, migrate, or backfill production data.

## Dependency

`#184 → #185 → #186 → Phase 2A`

## Health model

`ContentClusterHealth` reports independent editorial signals rather than one opaque boolean: active state, pillar presence/publication, total/published counts, primary coverage, ordering validity, public sequence presence, scheduled count, and findings. Findings include `EMPTY`, `NO_PILLAR`, `NO_PUBLIC_ARTICLES`, `PRIMARY_GAPS`, and `ORDERING_ISSUE`; a clean cluster is `HEALTHY`. `INCOMPLETE` remains available as a generic future aggregate status.

Orphan reporting distinguishes articles with no cluster, no primary, inactive-only coverage, published articles without an active path, and scheduled articles without an active path. These are editorial signals, not automatic errors.

## Admin surface

The existing Percorsi index shows health, published/total counts, pillar state, primary coverage, warnings, and aggregate orphan counts. Edit pages show non-blocking warnings. The UI uses text in addition to status styling, native links/controls, and semantic headings; meaning is never color-only.

The index eager-loads memberships and pillar records for the current page before evaluation. Health evaluation does not issue per-cluster/per-article queries once those relations are loaded.

## Versioned initial mapping

`config/content-clusters-initial.php` is the explicit mapping source. It uses slugs, never numeric IDs or fuzzy title matching. The four initial paths are IA spiegata, Spazio, Scienza quotidiana, and Energia e batterie. Energy intentionally has no pillar because the current candidate set does not justify silently choosing one.

The mapping is deliberately conservative: secondary/contextual articles may be members without becoming primary. The command treats any slug absent from the current database as `MISSING`; it never guesses an alternative. This also makes the dry run the authoritative environment-specific validation that the versioned slug candidates still exist before any local/test apply.

## Backfill command

`php artisan content-clusters:backfill-initial` is always a dry run. It prints cluster create/update intent, membership, position, primary, pillar, missing items, skips, and conflicts. Dry-run mode performs no writes.

`php artisan content-clusters:backfill-initial --apply` is an explicit write mode intended only for local/test environments. It creates missing clusters inactive, reuses existing clusters by slug, adds/updates only mapped memberships, preserves unrelated memberships/clusters, and is safe to rerun. It does not overwrite editorial descriptions or activate clusters.

A mapped primary is skipped if the article already has a different primary. Missing articles are reported and skipped. Missing pillars are not set. A pillar is set only when the article exists and is an applicable mapped member. Mapped writes go through `ContentClusterMembershipService::applyMapped()` so article locking and primary/pillar safety remain centralized.

## Public regression boundary

Phase 2A changes no public route, public controller, public Blade template, sitemap, structured data, or article-continuation behavior. Existing `/percorsi`, `/percorsi/{slug}`, and `Continua il percorso` regression suites remain the public contract.

## Analytics contract — design only

Future centralized analytics may consume `path_view`, `path_next_click`, `path_previous_click`, `path_view_all_click`, `second_reading`, and a `path_completion` proxy. No production dashboard or production-data query belongs in Phase 2A.

## Suggested membership — design only

A future suggestion may say “questo articolo potrebbe appartenere a Percorso X”. Candidate evidence may combine category, existing internal links, concepts, and explicit manual mappings. Suggestions are never auto-assigned: an editor confirms them. Confidence must remain explainable and must not become editorial authority.

## Large-catalog admin — Phase 2B

Phase 1A already fixed `max_input_vars` by submitting metadata only for selected memberships. A larger catalog should evolve toward server-side search/select membership, filters, pagination, and lazy loading. Phase 2A intentionally does not add a second membership UI implementation while the current catalog remains manageable.

## Phase 2B design

Phase 2B can add repository-side contracts for second-reading rate, cluster CTR, next/previous/view-all click rates, orphan coverage, suggestion confidence, and admin reporting. It must use synthetic/test fixtures until production analytics access is separately authorized.
