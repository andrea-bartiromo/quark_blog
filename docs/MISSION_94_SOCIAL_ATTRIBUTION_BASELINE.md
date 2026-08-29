# Mission 94 — Social attribution baseline

## Outcome

`FOUNDATION_READY_EXTERNAL_SETUP_REMAINING`

## Contract

| Field | Value | Evidence |
|---|---|---|
| `utm_source` | bounded channel name (`facebook`, `x`, `linkedin`, `altro`) | `UtmLinkGenerator::CHANNELS` |
| `utm_medium` | `social` | `UtmLinkGenerator::UTM_MEDIUM` |
| `utm_campaign` | explicit validated slug, otherwise channel/article/date | `normalizeCampaign()` |
| publication identity | ledger `event_key`, unique with article and channel | `social_publications_logical_unique` |
| provider identity | sanitized `remote_id` / safe HTTPS `remote_url` | `social_publications` |

The UTM URL is generated at provider execution time from the published article. The ledger remains the source of truth for logical-delivery identity. No click, impression, conversion, reach or engagement metric is claimed: no reliable ingestion source exists yet.

## Go-live checklist

1. Complete channel account, permission and token checks.
2. Run non-production posts and retain sanitized evidence.
3. Confirm analytics preserves the three UTM fields.
4. Decide and document retention/access for analytics data.
5. Enable one channel at a time under explicit authorization.
6. Reconcile ledger success with provider evidence; do not infer clicks.
