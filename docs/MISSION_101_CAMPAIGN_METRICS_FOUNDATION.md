# Mission 101 — Communication campaign metrics foundation

## Truthful metric vocabulary

| Metric | Availability | Definition |
|---|---|---|
| queued | available | frozen recipient row awaiting claim |
| accepted | available | provider accepted the request; **not delivery proof** |
| failed | available | normalized transient/permanent local/provider rejection |
| delivered | conditional | only a trusted provider feedback event may set it |
| bounce | conditional | only provider feedback; subscriber state supports it |
| open | unavailable in Communication 2.0 | no reliable event ingestion certified |
| click | unavailable in Communication 2.0 | no reliable event ingestion certified |
| unsubscribe | available as subscriber state/event, not campaign attribution | explicit user action |
| complaint | conditional | subscriber vocabulary exists; provider ingestion not certified |

## Outcome

`FOUNDATION_COMPLETE_EXTERNAL_EVENTS_PARTIAL`

No synthetic open/click/delivered rate is introduced. Admin/reporting must label successful provider calls as **accepted**, and expose delivery/bounce only when a real source event exists. Legacy newsletter tracking is not silently reattributed to Communication 2.0 campaigns.
