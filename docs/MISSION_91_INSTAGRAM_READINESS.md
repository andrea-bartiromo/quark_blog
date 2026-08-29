# Mission 91 — Instagram readiness/provider

## Outcome

`BLOCKED_WITH_EXTERNAL_FACTS`

Instagram remains behind `SOCIAL_DISTRIBUTION_ENABLED=false` and
`SOCIAL_INSTAGRAM_ENABLED=false`; its configured provider remains the fake. No
API capability, endpoint or publishing behavior is invented.

## Required operator facts

- confirm the official Kairus Instagram account is Professional (Business or
  Creator) and record its non-secret account ID;
- confirm it is connected to the intended Kairus Facebook Page and Meta App;
- confirm App Review/permissions actually granted for content publishing and
  the token type/lifetime/renewal owner;
- verify in Meta's current official documentation which publishing container
  flow and media formats are supported for this account;
- verify image URL reachability by Meta, accepted MIME types, dimensions,
  aspect ratio and file-size limits using a non-production test asset;
- decide caption maximum/template, hashtag policy and accessible alt-text
  handling;
- confirm the product expectation for article URLs: captions do not guarantee
  clickable links, so attribution/link-in-bio behavior requires an explicit
  product decision;
- capture normalized success fields and representative expired-token,
  permission, rate-limit, invalid-media and container-processing failures;
- perform a non-production E2E post and deletion/recovery exercise.

## Unblock rule

Only after the facts above are retained may an Instagram provider be added with
mocked HTTP tests and the flag still OFF. Credentials must remain env/config
secrets; no token or raw Meta response may enter the ledger. Live activation is
a separate authorized go-live operation.
