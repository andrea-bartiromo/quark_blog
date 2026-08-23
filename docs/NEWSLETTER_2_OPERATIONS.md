# Newsletter 2.0 — Operations

## Status

**No real email is ever sent by this subsystem.** Every delivery path in this codebase terminates in an in-memory fake provider (`NullEmailProvider` or `RecordingEmailProvider`). No SMTP/API credentials, no real transport, no scheduler, no automatic recovery exists anywhere in this subsystem. This document describes what is built and how to operate it manually in this state — it does not enable production sending.

The legacy `NewsletterController` / `newsletter_subscribers` flow (single-topic newsletter, real `Mail::send`) is untouched and out of scope here. Newsletter 2.0 is a separate, parallel subsystem (`comm_*` tables, `Communication*` models/controllers/services) built to reach **READY FOR PROVIDER INTEGRATION** without ever connecting one.

**Recipient Snapshot + Campaign Freeze** (Mission 8): the recipient snapshot (`RecipientSnapshotService`, step 1 below) already existed. What this increment adds is `CampaignFreezeService` — an explicit, idempotent "congela campagna" action that locks a ready campaign's content/template/mittente and its `comm_sends` recipient list against further changes. See "Congelamento (Campaign Freeze)" below for exactly what it guarantees and — just as important — what it deliberately does not.

## Architecture

```
subscriber (comm_subscribers, status=confirmed)
      |
      v
campaign (comm_campaigns) --"Prepara destinatari"--> snapshot (comm_sends, status=queued)
      |                                                      |
      v                                                      v
CampaignRenderer.render()                          CampaignDeliveryOrchestrator
  - subject/preheader/html/text                       - claim (queued -> sending, atomic)
  - unsubscribe URL (real subscriber)                 - revalidate (fresh DB reads, never
  - idempotency key (sha256(campaign:subscriber))        trust the snapshot)
      |                                                  - render (same CampaignRenderer)
      v                                                  - provider->deliver()
   admin preview / preflight / dry-run                   - persist result (sent/queued-retry/
      (all read-only, zero side effects)                    failed)
                                                              |
                                                              v
                                                    EmailDeliveryProvider (fake only)
                                                      - NullEmailProvider (discards)
                                                      - RecordingEmailProvider (records
                                                        in memory, configurable outcomes)
                                                              |
                                                              v
                                                    result persisted to comm_sends
                                                              |
                                                              v
                                            CommunicationCampaignActivityLog (audit trail,
                                            reused from the existing system, not a new one)
```

Rendering is a single pure function (`CampaignRenderer`) reused identically by preview, dry-run, and the real-send path once it exists — there is deliberately no second rendering implementation that could diverge from what actually gets "sent".

## State machines

Both are explicit transition tables (`CampaignStateMachine`, `SendStateMachine`) enforcing atomic conditional updates (`UPDATE ... WHERE id=? AND status=<expected>`), never a bare `save()`. A lost race returns `false` and refreshes the in-memory model; an out-of-table transition throws (a programming error, not a race).

**Campaign** (`comm_campaigns.status`): `draft` ⇄ `scheduled` → `sending` → `completed` | `failed` | `cancelled` (all three terminal). `sending` is reachable only from the delivery orchestrator, never from an admin action — there is no "Invia" button.

**Send** (`comm_sends.status`): `queued` → `sending` → `sent` | `failed` | (`queued` again, on retry). `cancelled` is reachable only from `queued` (a claim already in flight always finishes on its own — see Failure matrix). All of `sent`/`failed`/`cancelled` are terminal.

## Operator workflow

1. **Prepara destinatari** (`RecipientSnapshotService`) — creates one `queued` row per currently-confirmed subscriber not yet snapshotted for this campaign. Additive, idempotent, safe to re-run at any time before send; never removes or re-validates existing rows (that happens at claim time instead).
2. **Anteprima** (preview) — real rendering for a real confirmed subscriber, selectable via a paginated, escaped-LIKE search (`?q=`). Read-only; opening it any number of times never mutates anything.
3. **Verifica pre-invio** (preflight) — `CampaignPreflightService::assess()`. Read-only. Reports blocking errors (no sender / archived sender / no subject / no content / zero prepared recipients / campaign not preparable / unsubscribe route missing) and non-blocking warnings (prepared recipients no longer confirmed; confirmed subscribers not yet snapshotted; no preheader). Verdict is `not_ready` or `ready_for_test_send` — never a send trigger.
4. **Congela campagna** (`CampaignFreezeService`) — optional, requires the same readiness as preflight/dry-run (`CampaignPreflightService::assess()->isReady()`). Locks content/template/mittente and the `comm_sends` recipient list. Idempotent: freezing an already-frozen campaign is a safe no-op. See "Congelamento (Campaign Freeze)" below.
5. **Dry-run** (`CampaignDryRunService`) — runs the *entire* real delivery pipeline (`CampaignDeliveryOrchestrator::runCampaign()`) against a `RecordingEmailProvider`, inside a DB transaction that is **always** rolled back, success or exception. Reports the six canonical counters: `eligible`, `skipped`, `rendered`, `accepted`, `transient_failed`, `permanent_failed`. Zero persistent mutation, rerunnable at will. Only reachable from the preflight page once preflight itself is blocker-free. Independent of freeze — reachable whether or not the campaign is frozen.
6. **Disiscrizione** (unsubscribe) — public, token-based, GET (confirmation page, zero side effects) / POST (idempotent single conditional `UPDATE`) split. Row is never deleted (status flips to `unsubscribed`); GDPR erasure is a separate, more deliberate action never implicit in a click.

No step past #5 exists. The pipeline is deliberately capped at **PREPARA → ANTEPRIMA → VERIFICA PRE-INVIO → (CONGELA) → DRY-RUN → READY FOR PROVIDER INTEGRATION**.

## Congelamento (Campaign Freeze)

`CampaignFreezeService::freeze()` sets `comm_campaigns.frozen_at`/`frozen_by`. Two existing guardrails then key off `CommunicationCampaign::isFrozen()`:

- `CommunicationCampaignController::update()` rejects any edit (title/subject/preheader/body/template/sender/project) to a frozen campaign, server-side — the only mutation path, so this is a single, unbypassable choke point.
- `RecipientSnapshotService::canPrepare()` returns `false` once frozen: re-running "Prepara destinatari" adds no rows, even for subscribers who confirm *after* the freeze.

**What freezing does NOT do**: it never touches `comm_campaigns.status` (orthogonal to `CampaignStateMachine`), and it never weakens `CampaignDeliveryOrchestrator::revalidate()` — that method is unmodified by this increment and still re-reads subscriber/campaign/sender state fresh from the DB at claim time, never trusting a frozen `comm_sends` row. Concretely: a subscriber who unsubscribes *after* freeze still has their `queued` row resolve to `failed` (`subscriber_not_eligible`) the moment a real send is attempted, exactly as before freeze existed — proven by `tests/Feature/Communication/CampaignFreezeZeroSendRegressionTest.php`, which drives the real orchestrator (not a reimplementation) through this exact scenario with `Mail::fake()`/`Notification::fake()` asserting nothing is ever sent.

Audit trail: each freeze writes one `comm_campaign_activity_logs` row (`subject_type='freeze'`) with the acting user id, timestamp, campaign id, recipient count (`new_value`), and a content-version identifier (`reason`: `template_version:{id}` or `contenuto_manuale`) — never the content itself, never an email address, never a credential.

## Provider abstraction

`App\Contracts\EmailDeliveryProvider` — one method, `deliver(RenderedCampaignMessage): DeliveryResult`. Exactly two implementations exist and are the only ones that may ever exist in this codebase's current state:

- `NullEmailProvider` — always accepts, discards the message. Used where a delivery must happen but its content is irrelevant to the test.
- `RecordingEmailProvider` — records every attempt in memory (never disk/DB/network), returns configurable outcomes (`willReturn()` FIFO queue or `resolveUsing()` closure) to simulate accepted / rejected / transient_failure / permanent_failure / a real thrown exception (timeout/crash simulation).

A dedicated regression test statically scans both provider source files for `Mail::`, `Http::`, `curl_`, socket primitives, etc., and fails if any appear — the guarantee is enforced by a test, not just a convention.

## Idempotency

**Canonical key: `campaign_id` + `subscriber_id`** (`hash('sha256', "{$campaignId}:{$subscriberId}")`), the same granularity as the pre-existing `unique(campaign_id, subscriber_id)` DB constraint on `comm_sends`.

There is no `campaign_version` concept and none was introduced. This schema has no notion of "resend the same campaign as a distinct logical event" — editing campaign content after a snapshot changes what will be rendered under the same identity, exactly like editing a draft before sending doesn't turn it into a different email. Introducing a version column would only make sense for a future "repeated sends of the same campaign as distinct events" feature, which is explicitly not part of this subsystem.

## Revalidation at send time

A `queued` row is **never** trusted on its own. Every claim (`processSend()`) re-reads the subscriber, campaign, and sender profile fresh from the DB immediately before rendering/delivering:

- subscriber must still be `confirmed` (not unsubscribed/bounced/complained/pending since the snapshot)
- campaign must still exist, not be soft-deleted, and be `sending`
- sender profile must exist and be `active`

Any failure here resolves immediately to `failed` with an explicit reason — the provider is never called.

## Failure matrix

The canonical, test-backed matrix (15 scenarios: claim races, revalidation failures, render exceptions, all four provider outcomes including a genuine PHP exception, retries, max-attempts, cancellation timing, a lost concurrent-state-change race) lives in the docblock of `App\Services\Communication\CampaignDeliveryOrchestrator` — read it there rather than duplicating it here, since code comments drift from reality less than a separate document does. Every row is covered by at least one test in `CampaignDeliveryOrchestratorTest`.

Retry policy: transient failures retry up to `CampaignDeliveryOrchestrator::DEFAULT_MAX_ATTEMPTS` (3) then convert to permanent `failed`. Rejected/permanent failures never retry. `runCampaign()` reprocesses the queue in internal rounds (bounded by `DEFAULT_MAX_ATTEMPTS`) until nothing is left `queued` — a retried row is fully resolved (sent or permanently failed) within one `runCampaign()` call, no separate manual re-run is needed for retries to actually happen.

**Deliberately never auto-resolved**: an exception thrown *by the provider itself* (a real timeout/crash, not a `DeliveryResult` failure) leaves the row `sending` — the only ambiguous state in this design, matching the same honesty already established for `CommunicationDelivery`. Recovery is `App\Console\Commands\CommunicationReviewStaleSends`, a **manual-only** Artisan command (`--minutes`, `--release-all`), never scheduled — verified by a regression test that greps `routes/console.php` for the command name and asserts it is absent.

**Operational warning, found during pre-merge red-team review**: only release a row that is genuinely abandoned (its worker process is confirmed dead), never merely slow. Releasing a row a live worker is still processing is now caught loudly — the original worker's attempt to persist its outcome throws a `RuntimeException` instead of silently reporting success — but the underlying ambiguity (the provider may already have been called) still requires manual judgment before touching that row again. The `--minutes` threshold is a heuristic, not a guarantee; when in doubt, wait longer before releasing rather than risk a real double send once a provider is connected.

## Security

Audited in this phase (N2.11):

- **CRLF/header injection**: `subject`, `preheader` (campaign) and `from_name` (sender profile) will become real email headers once a provider is connected. Both the input `FormRequest`s (regex rejecting `\r`/`\n`) and `CampaignRenderer` itself (defense in depth, so any pre-existing/imported/direct-write data can never propagate a newline) now strip/reject them.
- **XSS**: no `{!! !!}` raw Blade output anywhere in the Communication views. Campaign body is rendered as escaped plain text (`white-space:pre-line`), never interpreted as HTML. The preview page's live-HTML iframe uses `sandbox=""` (no scripts, no same-origin, no top navigation) as defense in depth even though the content is already escaped upstream.
- **CSRF**: every mutating form carries `@csrf`.
- **Authorization**: every admin route (prepare/preview/preflight/dry-run) requires the `editor` role via the existing `auth`+`editor` middleware group; verified per-endpoint.
- **IDOR**: the preview page's `subscriber_id` parameter is always scoped through `CommunicationSubscriber::confirmed()`, never an unscoped `find()`.
- **Token enumeration / PII leakage**: unsubscribe returns a uniform generic 404 for malformed and nonexistent tokens alike; the token itself is never logged. The stale-send review command logs only numeric IDs, never subscriber emails.
- **Mass assignment**: no new fillable surface introduced beyond what each model already exposed.

Re-audited adversarially in a follow-up pre-merge red-team pass: real `<script>`/SVG-onload/`javascript:`/`data:` payloads fired through the actual HTTP preview render (not just a static grep for `{!! !!}`) confirmed the campaign body is escaped twice by construction (once by the email template, again when that already-escaped HTML is embedded into the preview iframe's `srcdoc` attribute) — no live markup ever reaches the DOM. Found and fixed one real concurrency bug in this pass (see Failure matrix). Also proved, and accepted as a residual risk rather than a bug, a TOCTOU window: `revalidate()` reads subscriber consent fresh right before rendering/delivery, but there is no second check *after* the provider call — a message already in flight when a subscriber unsubscribes still delivers that one message. Closing it would require holding a DB lock across an external network call, which is a worse anti-pattern than the narrow window it would close; every real bulk-email system has the same structural window.

## Performance

Measured (N2.12) at 1,000 and 10,000 confirmed subscribers, on both SQLite and MariaDB:

- Recipient search preview and preflight assessment: **constant query count regardless of scale** (paginate()/count()/whereDoesntHave(), never a full-table load).
- Dry-run: **linear** growth with recipient count, not quadratic — expected, since every row requires its own atomic claim for concurrency safety (see Failure matrix); verified by comparing the query-count ratio between 100 and 1,000 recipients.
- `RecipientSnapshotService::prepare()` scale behavior (1k/10k, chunked bulk insert, no SQL parameter-limit issues) was established in the prior mission phase and is not repeated here.

## CI

`.github/workflows/communication-delivery-mariadb.yml` is the dedicated per-feature MariaDB gate for this entire subsystem (originally scoped to just the `CommunicationDelivery` ledger, widened in this phase to cover Newsletter 2.0's full file set). It runs Pint, a fresh-migration MariaDB test pass over `tests/Unit/Communication`, `tests/Feature/Communication`, `tests/Feature/Admin/Communication`, and an incremental-migration verification. Triggered on every push to `main` and on pull requests touching any Communication-prefixed path. The full suite additionally runs on SQLite via `.github/workflows/tests.yml` on every push/PR with no path filter.

## What's missing for production

This subsystem is **not** production-ready and is not intended to become so without deliberate, separate decisions:

- No real `EmailDeliveryProvider` implementation exists or should be added casually — connecting one is an explicit, reviewed decision (ESP selection, credentials, deliverability/reputation setup), not a code change alone.
- No scheduler/queue worker drives delivery automatically — `runCampaign()`/`processQueue()` must be invoked manually (Artisan command, tinker, or a future explicit trigger) even once a real provider exists.
- No webhook/bounce/complaint ingestion exists — `comm_sends.status` values like `delivered`/`bounced` are defined but never populated automatically in this codebase.
- No rate limiting/warm-up strategy for a real send volume has been designed.
- Stale-sending recovery remains manual-only by design; whether that should ever change is an operator/product decision, not a default this subsystem should silently adopt.
