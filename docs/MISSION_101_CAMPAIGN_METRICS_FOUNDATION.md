# Mission 101 — Communication campaign metrics foundation

## Truthful metric vocabulary

| Metric | Availability | Definition |
|---|---|---|
| queued | available | frozen recipient row awaiting claim |
| sending / unknown outcome | available | claimed row whose provider outcome is not yet known, including crash-after-claim ambiguity |
| accepted | available | provider accepted the request; **not delivery proof** |
| transient failure attempt | available as an event counter | retryable attempt returned to `queued`; not a terminal recipient outcome and not additive with accepted recipients |
| permanent failed recipient | available | terminal local/provider rejection after retry policy is exhausted |
| delivered | conditional | only a trusted provider feedback event may set it |
| bounce | conditional | only provider feedback; subscriber state supports it |
| open | unavailable in Communication 2.0 | no reliable event ingestion certified |
| click | unavailable in Communication 2.0 | no reliable event ingestion certified |
| unsubscribe | available as subscriber state/event, not campaign attribution | explicit user action |
| complaint | conditional | subscriber vocabulary exists; provider ingestion not certified |

## Outcome

`FOUNDATION_PARTIAL_EXTERNAL_EVENTS_PARTIAL`

No synthetic open/click/delivered rate is introduced. Admin/reporting must label successful provider calls as **accepted**, and expose delivery/bounce only when a real source event exists. Legacy newsletter tracking is not silently reattributed to Communication 2.0 campaigns.
