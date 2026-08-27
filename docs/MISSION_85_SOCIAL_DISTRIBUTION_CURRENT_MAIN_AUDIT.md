# Mission 85 — Social Distribution current-main audit (#218)

## Outcome

`IMPLEMENTED` as an audit/design deliverable. No provider or external call was
introduced.

## Current architecture

| Area | Current-main evidence | Classification |
| --- | --- | --- |
| Official campaign links | `SocialDistribution/UtmLinkGenerator` supports Facebook, X, LinkedIn and Other with validated campaign slugs | PRESENT |
| Admin utility | authenticated `/admin/distribuzione-social`, stateless GET and copyable UTM link | PRESENT |
| Ledger/model | no social delivery model or migration | ABSENT |
| Job/provider | no social job, provider contract or Meta transport | ABSENT |
| Effective publication hook | manual approval/controller and `PublishScheduledArticles` both save `status=published`; model hooks currently notify Percorso continuation | PARTIAL |
| Queue | Laravel queue configuration and queued communication/path jobs exist; no social queue/job | PARTIAL |
| Activity log | `ActivityLog::record()` is used by manual and scheduled publication, but no social outcome/retry entries exist | PARTIAL |
| Feature flags | no global/Facebook/Instagram social publication flags; `laboratorio.social` is only footer profile links | ABSENT |
| Credentials | no Meta credentials in DB or config | NEEDS_EXTERNAL_SETUP |
| Facebook account/app | no configured Page/App/permissions/token facts | NEEDS_EXTERNAL_SETUP |
| Instagram account | professional-account, Page link and media requirements unverified | NEEDS_EXTERNAL_SETUP |

## Issue #218 requirements

| Requirement | Status |
| --- | --- |
| Ledger per article/channel/logical event | ABSENT |
| Post-publication application event | PARTIAL — lifecycle source exists, no dedicated event |
| Idempotent asynchronous dispatch | ABSENT |
| Retry/backoff/logging | ABSENT |
| Secure Meta configuration | ABSENT / NEEDS_EXTERNAL_SETUP |
| Global and channel flags | ABSENT |
| Facebook provider/payload/normalization | ABSENT / NEEDS_EXTERNAL_SETUP |
| Instagram provider/readiness | ABSENT / NEEDS_EXTERNAL_SETUP |
| Admin per-article state and diagnostics | ABSENT |
| Per-article opt-out | ABSENT; requires schema/product placement decision |
| Controlled manual retry | ABSENT |
| September non-production E2E/go-live checklist | ABSENT / NEEDS_EXTERNAL_SETUP |

## Design aligned to current main

1. Add a secret-free `social_publications` ledger with one logical row per
   article, channel and effective-publication identity.
2. Emit one after-success application event at the shared model lifecycle
   boundary; listener/job creation must be fail-open to website publication.
3. Create ledger rows atomically and queue channel jobs. A job claims one row,
   calls a provider contract and records normalized success/failure only.
4. Bind providers through config. Credentials remain env/config secrets;
   Facebook and Instagram flags default OFF.
5. Reuse `UtmLinkGenerator` for official URLs and keep reader share links bare.
6. Add admin status/retry only when social foundation is enabled; sanitize all
   errors and never expose tokens/raw responses.
7. Preserve ActivityLog for editorial actions (manual retry), while the ledger
   remains the machine delivery source of truth.

## Safety boundary

No Meta provider, token, live request, production mutation or backfill was
performed. PHPUnit/Pint are unavailable in this checkout. CI remains an
acknowledged external blocker through 2026-09-01.
