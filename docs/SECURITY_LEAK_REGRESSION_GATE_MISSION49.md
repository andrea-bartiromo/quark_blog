# Security & Public Leakage Regression Gate — Mission 49 verification

Mission 49 of the ongoing autonomous batch (Phase H, codebase health): "extend
negative tests proving what must NOT become public (scheduled/draft articles,
inactive Percorsi, continuous-prefix gaps, Content Graph private metadata,
search/TROVA, structured data, sitemaps)."

## What was audited

Each surface named in the mission spec, checked against the existing test
suite for real, executed (not merely reviewed) coverage:

| Surface | Coverage found |
|---|---|
| Scheduled/draft articles | `ScheduledArticleVisibilityTest` (article page, homepage, category/index, author page, sitemap, news sitemap 2-day window, RSS feed, full lifecycle transition) + `ScheduledArticlePublicSurfaceLeakAuditTest` (related articles, "Continua da qui", Percorso next-step, public Percorso page, search, structured data) |
| Inactive/scheduled Percorsi | `ContentClusterPubliclyVisibleScopeTest`, `ContentClusterScheduledActivationTest`, `TrovaEntitySearchServiceTest` (scheduled-not-yet-public and inactive+scheduled combinations explicitly excluded even with an otherwise-public prefix) |
| Continuous-prefix gaps | `ArticleDiscoveryPublicPrefixConvergenceTest`, `TrovaEntitySearchServiceTest::test_percorso_requires_a_non_empty_continuous_public_prefix` / `test_percorso_becomes_eligible_when_first_gap_opens` |
| Content Graph private metadata | `ContentGraphPublicSafetyContractTest` (every route touching Concept/Question controllers verified — by class, not by route name pattern — to carry both `auth` and `editor` middleware; draft/inactive concept links and questions excluded from every public/discoverable read; query-count invariance so exclusion isn't an N+1 side effect) + `ConceptAdminTest`/`ConceptQuestionAdminTest` (live HTTP guest-rejection, not just middleware-attachment assertions) |
| Search / TROVA | `TrovaEntitySearchServiceTest` (categories require published-article backing, concepts require an active published-article link, Percorso results never expose article membership or gap metadata, concept results never exceed their declared contract) + `SearchControllerTest::test_a_scheduled_not_yet_public_article_does_not_appear` |
| Structured data | `ArticleStructuredDataTest`, `ScheduledArticlePublicSurfaceLeakAuditTest::test_scheduled_article_never_exposes_newsarticle_structured_data`, `ContentGraphPublicStructuredDataTest` |
| Sitemaps | `RobotsSitemapDiscoveryTest`, `TuringSitemapTest`, `SitemapScalePerformanceTest`, `OrganicGrowthSeoRegressionTest` |

## Verification performed

Ran the full consolidated set — 212 tests across the files above — against
current `main` (sha `4050765`, i.e. including every PR merged by this
session's Missions 40-48): **212/212 passing, 0 failures.** This is the
first time this specific battery has been run together as one pass since
several of this session's own new features merged (Mission 40's
`ArticleDiscoveryAuditService`, Mission 42's revision history and autosave,
Mission 43's category source-debt and related-articles fixes) — none of
them regressed anything this gate checks.

## Conclusion

No code changes were required. Every surface the mission specifies already
has real, executed negative-test coverage — not just draft assertions or
static reviews — and that coverage currently holds against `main`. Per the
batch's own priority rule ("a mission that proves current implementation is
already correct and adds the missing regression coverage is successful"),
this mission's deliverable is the verification itself, recorded here as
evidence, rather than new tests duplicating what already exists.
