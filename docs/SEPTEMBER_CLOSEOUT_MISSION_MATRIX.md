# Sequenza operativa 1–70 — matrice conclusiva al 2026-09-01

La matrice evita il falso completamento: `DONE` indica evidenza disponibile;
`BRANCH` codice isolato ma non testato/merged; `PENDING_RUNTIME` richiede gate;
`BLOCKED_TIME/DATA/AUTH` è un esito corretto del prompt.

| Prompt | Esito | Evidenza / prossimo gate |
|---:|---|---|
| 1–4 | DONE | baseline temporale diagnosticata, corretta e certificata |
| 5–7 | DONE | PR #504 aggiornata/revisionata/post-merge |
| 8–12 | DONE | PR #505 auditata e certificata sullo squash |
| 13–14 | DONE | preflight e comando deploy preparati |
| 15 | PENDING_RUNTIME | deploy verificato; coda fixture/export e markup da rieseguire senza falsa assertion ItemList |
| 16 | DONE | audit categoria: 200 out-of-range e tie-break mancanti |
| 17 | BRANCH | `feat/category-pagination-contract` (`6cde1b4`) |
| 18 | DONE | fonti admin presenti, pubblico legacy incompleto |
| 19–20 | BRANCH | `feat/public-article-sources` (`eb09620`), ADR no migration |
| 21 | DONE | audit autore: email pubblica e thin indexing |
| 22–25 | BRANCH | `feat/author-trust-layer-v1` (`f5adcb2`) + policy/ADR |
| 26–32 | PENDING_RUNTIME | budget/audit statico; CWV e crawl production non misurati |
| 33–34 | BLOCKED_DATA | tassonomia non misura sorgenti/placement; dati mancanti ≠ zero |
| 35 | HUMAN_REVIEW | criteri definiti; nessun copy applicato |
| 36 | DONE | Fisica pre-lancio APPROVA, ordine preservato |
| 37 | BLOCKED_TIME | dopo 2026-09-07 13:30 UTC |
| 38–39 | PENDING_RUNTIME | servizi audit pronti; mapping alias richiede righe live/human review |
| 40 | BRANCH | `test/trova-human-reviewed-benchmark` (`cd5eb0b`) |
| 41–45 | PENDING_RUNTIME | contratti auditati; campioni/decisioni/calendario live mancanti |
| 46–49 | BLOCKED_DATA_TIME | date/slug #21/#22 non nel repository; pre/post gate da schedulare |
| 50 | BRANCH | `feat/scheduled-articles-weekly-certification` (`7113cf3`) |
| 51 | DONE | policy per tipologia proposta; nessuna soglia globale |
| 52–53 | DONE | discovery/schema/workflow proposti, provider esclusi |
| 54–55 | DESIGN_BLOCKED | manca modello draft; vietato abusare del ledger delivery |
| 56–57 | DONE | query/UX boundary e runbook future activation, flag OFF |
| 58 | PENDING_TEST | contratti snapshot/concorrenza esistenti; fixture gate richiesto |
| 59 | BLOCKED_AUTH | nessun invio senza destinatario e conferma separata |
| 60 | BRANCH | `fix/newsletter-failure-log-privacy` (`960cac9`) |
| 61–62 | PENDING_RUNTIME | fixture/admin gate, nessun export production |
| 63 | HUMAN_DECISION | RPO/RTO e off-host proposti, non attivati |
| 64 | DONE_STATIC | workflow effimero già conforme; run CI pendente per nuovi branch |
| 65 | PENDING_REGISTRY | inventario fatto; vulnerability audit non eseguito offline |
| 66 | DONE | runbook reale consolidato, nessun cambio `deploy.sh` |
| 67–68 | BRANCH_DOCS | roadmap v14 e indice ADR in questo branch |
| 69 | PARTIAL_NO_GO | insufficienti CWV/CTR e Fisica non ancora pubblica |
| 70 | DELIBERATELY_DEFERRED | vietato finalizzare ottobre prima del closeout |

## GO/NO-GO ottobre

**NO-GO TEMPORANEO** al piano definitivo. Non è un blocco alla manutenzione
sicura: i branch piccoli possono essere revisionati. È un blocco all'apertura di
un nuovo cantiere principale finché non sono disponibili: CWV same-SHA,
denominatori seconda lettura/CTR, Fisica post-data, verbale WCAG/SEO runtime e
adozione aggregata del Command Center.

Quando i gate saranno completi, il candidato ottobre è: cantiere principale
adozione editoriale/Trust Layer; secondario Workspace Social solo bozza/preview,
zero invio. Capacità, owner e metriche vanno assegnati allora, non inventati ora.
