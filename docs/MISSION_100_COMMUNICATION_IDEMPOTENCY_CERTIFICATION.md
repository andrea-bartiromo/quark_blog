# Mission 100 — Provider/idempotency certification

## Outcome

`CERTIFIED_WITH_EXPLICIT_AMBIGUOUS_WINDOW`

| Invariant | Evidence |
|---|---|
| one logical delivery | UNIQUE deterministic `delivery_key` / one campaign send row |
| concurrent claim | guarded `pending/queued -> sending` transition |
| retry after known failure | same row returns to pending; successful terminal rows are immutable |
| crash after provider acceptance | row remains `sending`; it is not blindly resent |
| failure privacy | NOT CERTIFIED: generic callback exceptions and arbitrary provider reasons can still persist unsanitized text; operators must not treat stored failure text as secret-safe |
| batch completion | reports distinguish accepted, transient and permanent failures |
| concurrency | dedicated SQLite behavior plus MariaDB concurrency suites |

The system chooses duplicate prevention over automatic recovery when provider outcome is unknowable. Operator reconciliation is required for stale `sending` rows; calling them failed or retrying automatically would be dishonest.

Relevant suites: `CommunicationDeliveryConcurrencyTest`, `CampaignDeliveryOrchestratorConcurrencyTest`, `StaleSendRecoveryLiveWorkerRaceTest`, and the 10k simulations.
