# Indice delle decisioni architetturali

Questo indice censisce documenti esistenti e proposte correnti senza riscrivere
la storia. “Proposto” non equivale ad approvato o implementato.

| Area | Decisione / documento | Stato |
|---|---|---|
| Percorsi | `CONTENT_CLUSTER_PUBLIC_SEQUENCE_CONTRACT.md`, documenti scheduling/readiness | vigente; resolver canonico unico |
| Content Graph | `CONTENT_GRAPH_V1.md`, `MISSION_64_CONTENT_GRAPH_PHASE_G_GATE.md` | vigente; nessuna pagina automatica |
| TROVA | `TROVA_V1_DESIGN.md`, benchmark V1 su branch dedicato | vigente + proposta misurazione |
| Radar | `RADAR_EDITORIALE_FOUNDATION_DESIGN.md`; ledger esiti descritto nell'audit operativo | foundation vigente; ledger proposto |
| Deploy | `DEPLOYMENT.md`, `MISSION_09_VERSIONED_ASSET_HASH_STRATEGY.md` | vigente; runbook reale integrato |
| Backup | `BACKUP_V2_OPERATIONS.md`, workflow `backup-restore.yml` | vigente; off-host proposto |
| Social | `SOCIAL_DISTRIBUTION.md`, `MISSION_86_SOCIAL_LEDGER_MIGRATION_SAFETY.md` | ledger delivery vigente; draft workflow proposto |
| Newsletter | `NEWSLETTER_2_OPERATIONS.md`, `MISSION_95_NEWSLETTER_SOURCE_DECISION.md` | vigente; legacy ancora separato |
| Export | `DASHBOARD_DATA_EXPORT_V1.md` | deployed; runtime fixture gate pendente |
| Trust Layer | ADR fonti/revisioni nei branch dedicati | proposto, non merged |

Decisioni superseded devono restare consultabili con etichetta e link al commit;
non vanno cancellate. Gli SHA storici v13 sono esplicitamente storici dopo
`84a5803`.
