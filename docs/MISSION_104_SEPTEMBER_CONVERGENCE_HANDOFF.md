# Mission 104 — September convergence handoff

## Gate outcome

`BLOCKED_PENDING_STACK_MERGE_AND_EXECUTABLE_RELEASE_EVIDENCE`

- **FINAL_MAIN_SHA (observed):** `e84249bf5dace3694404464301bb0fa870f576e6`
- **Final proposed stack before this handoff:** `ae80e974dfd09118018e052f2a7faec7756026b5` (Mission 103)
- Repository comparison: 61 commits / 50 changed files ahead of main before Mission 104.
- No deploy or production mutation was performed.
- Mission PRs are intentional review items, not accidental drafts. Their open state means work is **proposed**, not merged or online.

## Missions 55–104 outcome / PR map

| Missions | Outcome | Evidence |
|---|---|---|
| 55 | baseline complete | PR #441 |
| 56–63 | diagnostics/admin integration complete | PRs #442–#449 |
| 64 | blocked release gate documented | PR #450 |
| 65 | stale issue reconciled | #256 closed with evidence |
| 66 | metric semantics audited | PR #451 |
| 67 | implemented | PR #456 |
| 68–71 | implemented | PRs #453, #454, #452, #455 |
| 72–75 | implemented | PRs #457–#460 |
| 76 | blocked with production facts | #249 remains open |
| 77–81 | implemented | PRs #461–#465 |
| 82 | blocked with environment evidence | PR #466 |
| 83 | no actionable owned bottleneck | PR #467 |
| 84 | browser gate code added; execution pending | PR #468 |
| 85 | social current-main audit complete | PR #469 |
| 86–90 | social ledger/event/queue/payload/Facebook foundation | PRs #470–#474 |
| 91 | blocked with external Instagram facts | PR #475 |
| 92–93 | admin status/manual retry implemented | PRs #476–#477 |
| 94 | attribution foundation ready; external setup remains | PR #478 |
| 95 | placement-only source decision | PR #479 |
| 96–97 | source persistence/reporting implemented | PRs #481–#482 |
| 98 | Communication 2.0 convergence audited | PR #483 |
| 99 | verified already present | PR #484 |
| 100 | idempotency certified with explicit ambiguous window | PR #485 |
| 101 | truthful metrics foundation documented | PR #486 |
| 102 | explainable Second Read Radar provider implemented | PR #487 |
| 103 | graceful insufficient attribution provider implemented | PR #488 |
| 104 | handoff/roadmap rebaseline | this PR |

## Test evidence

Available evidence is static and test-code evidence, not a green release certification:

- GitHub compare completed against the observed main SHA.
- Prior Node syntax checks passed where recorded.
- Focused PHPUnit, full release gate, Pint and browser matrix could not be executed in the available workspace because PHP/vendor/Playwright runtime is unavailable.
- GitHub Actions is an acknowledged external blocker through 2026-09-01; no green CI result is claimed.
- Browser matrix still required at 390, 768 and 1440 px for public article/continuation, newsletter CTAs/report, admin article social status/retry, Communication campaign Test Send, and Radar/Editorial Operations.

## Migrations introduced by the current proposed stack

1. `2026_08_27_150900_create_social_publications_table.php` — additive ledger, no backfill.
2. `2026_08_27_153500_add_source_to_newsletter_table.php` — nullable indexed bounded source, legacy rows remain null.

Both require backup, staging execution, MariaDB verification, rollback rehearsal and explicit production authorization. Neither was executed in production.

## Production-readiness blockers

- merge/review the ordered PR stack and resolve conflicts;
- run PHPUnit/Pint/local release gate on the exact final merged SHA;
- run critical browser matrix;
- verify MariaDB migrations and concurrency suites;
- obtain Meta Facebook/Instagram account, permission, token and media facts;
- perform non-production provider E2E and reconciliation;
- obtain production `users.photo` facts for #249;
- configure/verify real communication provider feedback before claiming delivered/bounce;
- define downstream analytics ingestion before social/article attribution;
- explicit go-live authorization and rollback/runbook.

## Issue state

| Issue | State | Decision |
|---|---|---|
| #256 | closed | correctly completed/superseded by persisted Second Read evidence |
| #257 | open | implementation exists only in unmerged PRs #481–#482; close after merge and verification |
| #249 | open | production `users.photo` facts still required |
| #218 | open | foundation proposed; Meta setup, opt-out, E2E and activation remain |
| #209 | open | intentional future multilingual backlog; not closed |

## State separation

- **Repository main:** remains at the observed SHA; none of this final stack is asserted merged.
- **PR state:** missions are proposed in ordered PRs; open is intentional.
- **Production-ready:** no, pending executable gates and external facts.
- **Deployed:** no.
- **Verified online:** no; no production verification was performed.

## Next 10 recommended actions

1. Review and merge the stack in order, or retarget/squash deliberately.
2. Resolve CI availability and run all focused/full suites on final main.
3. Run Pint and the local release script.
4. Apply both migrations in staging with MariaDB evidence.
5. Execute the 390/768/1440 browser matrix.
6. Verify #249 production photo values and choose its documented remediation.
7. Complete Facebook non-production credentials/permissions/E2E.
8. Complete Instagram professional-account and supported-media facts.
9. Reconcile communication provider acceptance/delivery webhook semantics.
10. Authorize a staged rollout with flags OFF→Facebook pilot→review→Instagram, with rollback.

No deploy is part of this handoff.
