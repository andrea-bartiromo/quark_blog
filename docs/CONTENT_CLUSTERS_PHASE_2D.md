# Content Clusters Phase 2D — Design Only

## Status

Design-only planning document. No Phase 2D runtime behavior is implemented here.

Phase 2D extends the Phase 2C automatic suggestion refresh with richer evidence and quality controls while preserving a human-confirmation default. Automatic systems may detect, score, explain, prioritize, and refresh suggestions, but they must not silently assign memberships, primary paths, pillars, ordering, or publication state.

## Goals

Phase 2D should improve suggestion quality, editorial usefulness, explainability, calibration, and operational scalability without weakening the Phase 2B/2C state machine or publication boundaries.

Success means editors receive fewer low-value suggestions, stronger evidence for high-value suggestions, deterministic explanations, measurable quality feedback, and bounded processing at catalogs ranging from 1k to 50k articles.

## Non-goals

Phase 2D does not introduce autonomous membership acceptance, automatic primary/pillar assignment, automatic publication, opaque model-only decisions, production backfills, or a global full-catalog regeneration on ordinary article edits.

## Evidence model

### Internal-link evidence

Use first-party link graph signals as evidence that two articles or an article and a path are editorially related. Candidate signals include inbound and outbound links, reciprocal links, anchor-topic overlap, repeated co-linking from authoritative articles, pillar proximity, and link freshness.

Internal-link evidence must remain explainable. A suggestion should identify which links or aggregate graph signals contributed, not merely expose an unexplained score.

### Semantic and topic evidence

Introduce semantic/topic similarity as an additive signal rather than a sole acceptance criterion. Candidate inputs include normalized title, excerpt, category, tags or structured editorial metadata already present in the repository, plus embeddings or topic vectors only when their storage, privacy, versioning, and reproducibility contracts are explicit.

Semantic evidence should be versioned so a model or embedding-version change can invalidate or stale prior evidence deterministically.

### Editorial completeness

Estimate whether a Content Cluster is missing expected coverage. Completeness may consider topic facets, content types, funnel/editorial intent, chronology, beginner-to-advanced progression, and explicit editorial requirements.

Completeness signals should prioritize suggestions that close a meaningful gap rather than simply adding more similar articles.

### Orphan prioritization

Prioritize otherwise relevant articles that have weak discoverability: no Content Cluster membership, few meaningful internal links, no inbound links from pillar/high-authority pages, or no coherent path placement.

Orphan status is a prioritization input, not sufficient evidence by itself.

## Confidence model

### Calibration

Retain deterministic evidence components and define a versioned confidence contract. Each evidence family should contribute through an explicit, inspectable rule or calibrated model with documented thresholds.

Confidence calibration should be evaluated against editor outcomes. Suggested bands:

- high confidence: strong multi-signal evidence, suitable for prominent editorial review;
- medium confidence: plausible but incomplete evidence;
- low confidence: discovery-only candidates, hidden from default queues unless editors opt in.

Do not equate confidence with permission to auto-accept. Human confirmation remains the default at every score.

### Evidence combination

Avoid naive score inflation from correlated signals. For example, category match and semantic similarity may describe the same underlying relationship. The design should either cap correlated contributions or explicitly model evidence families.

Evidence hashes must include the versioned inputs that affect the decision so state transitions remain deterministic when evidence changes.

## Suggestion quality measurement

Measure quality using editor-facing outcomes rather than raw suggestion volume. Candidate metrics:

- acceptance rate by confidence band and evidence family;
- rejection rate and rejection reason where available;
- stale-before-review rate;
- time to decision;
- duplicate or conflict rate;
- proportion of accepted suggestions that improve path completeness;
- precision sampled through editorial audit;
- coverage of orphan/high-priority articles.

Do not optimize acceptance rate in isolation; excessive conservatism can raise acceptance rate while missing valuable suggestions.

## Analytics feedback loop

Use privacy-minimal, non-personal event metadata consistent with the existing analytics contract. Feedback may include suggestion version, confidence band, evidence-family flags, outcome, age at review, and coarse catalog/path size buckets.

Do not store article body text, editor-entered private text, unnecessary user identifiers, or embedding payloads in analytics events.

Analytics informs calibration and product evaluation; it must not silently mutate memberships or editorial state.

## Path quality metrics

Define path-level quality indicators that can be computed deterministically and explained to editors. Candidate metrics include:

- coverage/completeness score;
- orphan count;
- duplicate-topic density;
- internal-link connectivity;
- pillar support coverage;
- sequence continuity;
- stale/inactive-content ratio;
- concentration risk where too many articles depend on one category or signal.

Metrics should distinguish measurement from action. A low score creates review opportunities, not automatic destructive edits.

## Explainability contract

Every suggestion exposed to an editor should include:

1. the target article and Content Cluster;
2. confidence and confidence band;
3. evidence families used;
4. concise human-readable reasons;
5. evidence/model version;
6. why the suggestion changed or became stale when applicable;
7. conflict warnings, including existing primary membership;
8. whether the suggestion is new, refreshed, rejected-with-changed-evidence, or otherwise transitioned by the state machine.

Avoid explanations generated solely from free-form model text when the underlying evidence can be rendered deterministically.

## State machine compatibility

Phase 2D must preserve the Phase 2B/2C contract:

- pending + same evidence remains pending;
- rejected + same evidence remains rejected;
- rejected + changed evidence becomes stale before reconsideration;
- accepted is never reopened automatically;
- existing membership prevents duplicate pending suggestions;
- inactive clusters have no actionable suggestion;
- disappeared evidence stales pending/rejected suggestions;
- primary conflicts remain fail-safe.

New evidence families extend the evidence hash/version; they do not create parallel state machines.

## Incremental refresh architecture

Ordinary Article changes remain article-scoped. Membership changes remain cluster/category-scoped or narrower where the new evidence permits it. Internal-link changes should refresh only affected source/target neighborhoods. Semantic-vector updates should refresh only articles whose versioned representation changed and their bounded candidate set.

A manual/batch reconciliation command may exist for recovery, re-indexing, or evidence-version migrations, but ordinary writes must not trigger full-catalog regeneration.

## Scale design

### ~1k articles

Synchronous after-commit refresh can remain practical for tightly bounded operations. Candidate generation may query active clusters and small local graph neighborhoods directly, with query-count regression tests.

### ~10k articles

Use precomputed indexes for semantic candidates, internal-link aggregates, and category/path statistics. Incremental refresh should operate on bounded candidate IDs. Batch reconciliation should chunk work, checkpoint progress, and be idempotent.

### ~50k articles

Separate evidence computation from editorial writes. Use versioned derived indexes/materialized aggregates and queueable repository-only jobs for expensive candidate generation. Enforce hard candidate caps per article, bounded memory, backpressure, retry/idempotency keys, and observable progress.

At this scale, no algorithm may perform articles × clusters scans on ordinary saves. Performance tests should include representative sparse and dense link graphs and worst-case category distributions.

## N+1 and query budget

Define explicit query budgets for article refresh, membership refresh, and review/acceptance. Bulk evidence lookups should use grouped queries or precomputed aggregates. UI lists must paginate and must not render full-catalog metadata into the page.

Query budgets should be asserted in regression tests for representative catalog sizes rather than inferred from wall-clock timing alone.

## Security and privacy

Phase 2D must not add public write routes or widen editor/admin authorization. CSRF and existing authorization boundaries remain unchanged. Any external semantic service would require a separate security/privacy decision before implementation; no article content or user data should leave the application by default.

No secret, production endpoint, or production-only assumption belongs in the evidence contract.

## Failure isolation

Suggestion computation remains secondary to editorial writes. After-commit refresh should fail open for the primary write, report failures observably, and allow deterministic retry/reconciliation. Fail-open must not mean silent corruption: partial derived state should be detectable by evidence version, timestamps, job status, or reconciliation diagnostics.

## Accessibility and responsive admin UX

Any future Phase 2D UI should preserve keyboard operation, semantic controls, visible focus, readable evidence ordering, and non-color-only confidence/status indicators. Explanations and confidence details must remain usable on narrow screens without embedding large hidden catalogs in the DOM.

## Validation strategy

Before implementation can be considered complete, add focused tests for each new evidence family, canonical evidence hashing/versioning, state-machine transitions, conflicts, no-auto-assignment, failure isolation, privacy-safe analytics, query budgets, and 1k/10k/50k scale characteristics using deterministic fixtures or synthetic benchmarks.

Maintain the existing full PHP, Browser, Pint/diff, MariaDB fresh and incremental migration gates on one final SHA.

## Rollout plan

1. Define versioned evidence schemas and quality metrics.
2. Add offline/diagnostic computation for new signals with no suggestion behavior change.
3. Validate signal precision against a curated editorial sample.
4. Introduce additive suggestion evidence behind an explicit configuration/version boundary.
5. Calibrate confidence bands from editorial outcomes.
6. Add path-quality dashboards only after metrics are stable and explainable.
7. Scale derived indexes and reconciliation workflows as catalog size requires.

Each stage remains reversible and repository-first. Production backfills, external services, or irreversible operational changes require separate authorization.

## Decision gate for implementation

Phase 2D implementation should not begin until reviewers agree on:

- evidence families and their allowed inputs;
- confidence/calibration approach;
- evidence-version/hash contract;
- analytics/privacy schema;
- path-quality definitions;
- query budgets and scale targets;
- failure/retry behavior;
- human-confirmation policy.

Human confirmation is the default and is intentionally retained as a product safety boundary.
