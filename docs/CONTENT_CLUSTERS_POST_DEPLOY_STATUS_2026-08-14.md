# Content Clusters / Percorsi — Post-Deploy Status — 2026-08-14

This document separates three states that must not be conflated:

1. **Repository state** — what exists in `main`.
2. **Production state** — what the completed deploy reported as online.
3. **Editorial activation state** — what editors have intentionally activated as public Percorsi.

## 1. Repository state

Authoritative repository baseline for this review:

`main @ 4afdb310d085e0b4e92b6f54fbe1d2bfff54dd9e`

No unexpected commit was present when this post-deploy repository audit began.

### Phase status

| Phase | Repository status | Notes |
|---|---|---|
| Phase 1A | COMPLETE | Content Cluster schema/models, membership/pillar invariants and admin foundation. |
| Phase 1B | COMPLETE | Public Percorsi index/detail, publication filtering, sitemap/SEO foundation. |
| Phase 1C | COMPLETE | Article continuation, ordering, previous/next, progress and second-reading foundation. |
| Phase 2A | COMPLETE | Editorial activation/admin workflow and large-catalog handling. |
| Phase 2B | COMPLETE | Suggestion lifecycle, canonical evidence, confidence/reasons, accept/reject/stale semantics. |
| Phase 2C | COMPLETE | Automatic scoped refresh after relevant editorial changes; no automatic assignment. |

The Content Clusters stack is merged into `main`. The newsletter tracking resilience fix is also present in the same deployed release line.

### Repository behavior verified in this audit

- `/admin/percorsi` is an authenticated/editor-only management surface.
- `/percorsi` and `/percorsi/{slug}` are separate public routes.
- Public Percorsi expose only active clusters.
- Draft/scheduled/review articles do not enter the public Percorso sequence.
- Pillar is nullable and a non-public pillar is not exposed publicly.
- Manual order drives public ordering and article previous/next navigation.
- Suggestion acceptance remains explicit; normal article edits do not auto-assign membership.
- Phase 2C refresh is scoped rather than a global regenerate on every save.
- Sitemap eligibility requires an active cluster with at least one public article.
- The canonical initial mapping versions four candidate first Percorsi with five articles each.

## 2. Production state

**Source:** completed deploy report supplied to this repository-only mission. Production was not accessed or queried while preparing this document.

Reported deployed release:

`4afdb310d085e0b4e92b6f54fbe1d2bfff54dd9e`

Reported production facts after deploy:

- Content Clusters / Percorsi through Phase 2C are online.
- Newsletter resilience fix is online.
- Four Content Cluster migrations were applied successfully.
- `/` returned 200.
- `/notizie` returned 200.
- `/percorsi` returned 200.
- `/sitemap.xml` returned 200.
- `/feed.xml` returned 200.
- Active ContentCluster count was `0` immediately after deploy.
- No membership was automatically created by the deploy.
- Observed newsletter errors were historical/pre-deploy.
- No new immediate post-deploy application errors were reported.

Reported pre-deploy backups:

- Database: `kairus-pre-content-clusters-20260814-094509.sql.gz`
- Application: `deploy_backups/pre-4afdb310-20260814-094627`

These production observations are recorded as deploy evidence, not independently re-verified here because this mission explicitly prohibited production access.

## 3. Editorial activation state

Current reported state:

`initial active paths = 0`

Therefore the feature is technically deployed but the first editorial activation is still pending. This is intentional: deployment did not create memberships, select pillars or activate public Percorsi automatically.

The next step is human-reviewed editorial activation, not another runtime implementation phase.

Canonical initial candidates:

1. IA spiegata — `ia-spiegata`
2. Spazio — `spazio`
3. Scienza quotidiana — `scienza-quotidiana`
4. Energia e batterie — `energia-batterie`

The detailed initial mapping and human-review checklist are in:

`docs/CONTENT_CLUSTERS_FIRST_EDITORIAL_ACTIVATION.md`

The editor-facing operating guide is in:

`docs/CONTENT_CLUSTERS_EDITORIAL_QUICKSTART.md`

## 4. Backfill readiness

`app/Console/Commands/BackfillInitialContentClusters.php` already has a safe preview-by-default contract.

Key properties:

- no `--apply` means dry-run/preview;
- new mapped Percorsi are created inactive when apply is explicitly chosen;
- missing articles are reported;
- non-published articles are blocked unless an explicit apply-only override is supplied;
- article publication state is never changed;
- cluster activation is never automatic;
- existing cluster/membership state is handled idempotently;
- each mapped Percorso apply is transactional;
- rerun is safe/convergent.

Operational note: the transaction boundary is per Percorso rather than the whole four-Percorso batch. A later Percorso can fail after an earlier one succeeded. This is not a data-loss issue because the operation is idempotent, but the recommended first-use policy is to resolve all preview `MISSING/BLOCKED` lines before any separately authorized apply.

No production backfill was executed in this mission.

## 5. Suggestion quality / Phase 2B–2C reality

Current canonical evidence behavior:

- exact versioned mapping → confidence `100`;
- category-only evidence requires at least two confirmed editorial memberships in the relevant category and currently produces confidence `65`;
- pending/rejected/stale/accepted states remain explicit;
- rejected evidence is not silently reopened until evidence changes;
- accepted membership is idempotent;
- stale evidence is revalidated before acceptance;
- normal saves do not invoke global regeneration;
- automatic refresh never means automatic membership.

### Confidence interpretation for the observation phase

- **90–100:** strong evidence, still human-reviewed.
- **70–89:** useful review band; current canonical rules need not produce a score here.
- **<70:** supporting evidence only; category-only `65` belongs here.

`NO_PRODUCT_THRESHOLD_DEFINED`

No automatic acceptance threshold should be invented before real editorial acceptance/rejection data exists.

## 6. Analytics readiness for first activation

The current Percorsi analytics foundation can observe:

- `path_view`;
- next click;
- previous click;
- view-all click;
- `second_reading` within the same path/session.

The foundation uses allowlisted metadata and local `CustomEvent` dispatch; continuation state uses session storage. It does not require an external network dependency to keep navigation working, and failures are designed not to break the user journey.

The first editorial activation should therefore be used to learn:

- whether readers open the Percorso page;
- whether they continue forward/backward between articles;
- whether they return to “view all”;
- whether a first article leads to a second reading in the same Percorso/session.

No advanced analytics dashboard is required before gathering this baseline.

## 7. SEO / discovery reality

When a Percorso is activated:

- its detail becomes publicly reachable;
- only published articles are rendered;
- canonical/OG/structured-data support exists in the public surface;
- inactive Percorsi remain 404;
- the sitemap includes a Percorso only when it is active **and** has at least one public article.

### Current empty-active policy

`is_active=true` + `0 published articles` can still return a valid Percorso detail page while remaining excluded from the sitemap.

This is a **non-blocking documented product policy**, not something to change silently in a post-deploy stabilization mission. Operationally: do not activate an empty Percorso during first use.

## 8. First-use UX assessment

Result: `PASS_WITH_NON_BLOCKING_NOTES`.

The admin already supports:

- zero clusters;
- inactive clusters;
- no suggestions;
- pending suggestions;
- nullable pillar;
- empty membership;
- membership containing draft/scheduled articles without public leakage.

No first-use blocker was found that justifies a runtime code change. The main first-use risk is editorial, not technical: activating a Percorso before verifying public members/metadata.

## 9. Roadmap reality update

The technical roadmap must now reflect that Phases 1A, 1B, 1C, 2A, 2B and 2C are no longer future work. They are complete in the repository, integrated into `main`, deployed, and technically verified.

The remaining near-term state is **editorial learning**:

- start with zero active Percorsi;
- activate deliberately;
- observe path engagement/second-reading behavior;
- collect acceptance/rejection experience from suggestions;
- avoid deeper automatic editorial decisions until that evidence exists.

Phase 2D remains design-only and should not be promoted to runtime work from this status update.

## 10. Recommended next engineering priorities

The immediate product priority is to observe real Percorsi behavior before building deeper automation. With that constraint:

### NEXT_P0 — Growth/Search Console + Percorsi observation baseline

Reason: the first public Percorsi need a measurement loop around discovery, indexation, impressions/clicks and the existing second-reading/navigation events. This work should focus on observation/instrumentation hygiene rather than changing recommendation logic.

### NEXT_P1 — Responsive Images S2 + Core Web Vitals

Reason: image/performance improvements benefit article pages and the new Percorsi surfaces broadly, do not depend on uncertain editorial automation assumptions, and can improve search/user experience while the first Percorsi accumulate evidence.

### NEXT_P2 — WCAG hardening

Reason: accessibility should be improved before adding more interaction complexity. Existing Percorsi UX can be observed first while targeted accessibility work proceeds as a cross-cutting quality investment.

### Explicitly deferred pending real usage evidence

- **Phase 2D suggestion-quality runtime:** keep design-only until real accept/reject/stale/second-reading data exists.
- **Content Graph V1:** do not build a deeper graph before learning whether the curated paths produce meaningful navigation behavior.
- **Newsletter 2.0:** newsletter resilience is stabilized; new capabilities should wait until the current production fix has accumulated normal traffic evidence and Percorsi learning is underway.
- **Broader growth automation:** Search Console/discovery observation can start, but automated growth decisions should wait for data.

## 11. Non-blocking open notes

1. Current repository data cannot prove that all 20 canonical initial article slugs still exist/published in production. Human/admin verification is required before membership/activation.
2. The canonical Energia mapping includes `perche-calze-legate-ioni-litio`; verify that this unusual slug points to the intended article before use.
3. Active-empty Percorso details are 200 but sitemap-excluded; do not activate empty Percorsi unless product later changes that policy deliberately.
4. There is no formal product confidence threshold for automatic suggestion acceptance.

## 12. Current readiness conclusion

`READY_FOR_FIRST_EDITORIAL_ACTIVATION=YES`

Meaning: the repository/admin/public foundations are ready for a **human-reviewed first activation session**. It does not mean automatic activation, automatic backfill, or unattended membership creation is authorized.
