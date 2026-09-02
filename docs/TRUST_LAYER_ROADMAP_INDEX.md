# Trust Layer — indice roadmap operativo (Agente B)

Handoff separato, non una riscrittura della roadmap v14 (mai trovata come
documento in questo repository — riferita nel contratto ma non
localizzabile qui, trattata come INSUFFICIENT_DATA per tutta la durata
del cantiere) né dei documenti storici esistenti.

## Decisioni prese e loro dipendenze

| # | Documento | Decisione | Dipende da |
|---|---|---|---|
| B-01–B-10 | (report in conversazione) | Audit completo, nessun codice | — |
| B-11–B-18 | `docs/TRUST_LAYER_PUBLIC_SOURCES_V1.md` | READY_FOR_HUMAN_REVIEW | — |
| B-19–B-24 | `docs/TRUST_LAYER_POLICY_PAGES_V1.md` | READY_FOR_HUMAN_REVIEW | — |
| B-25–B-30 | `docs/TRUST_LAYER_PUBLIC_AUTHOR_PAGES_V1.md` | READY_FOR_HUMAN_REVIEW | — |
| B-31–B-38 | `docs/TRUST_LAYER_REVISION_TRANSPARENCY_V1.md` | READY_FOR_HUMAN_REVIEW | — |
| B-39–B-45 | `docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md` | GO prototipo, NO-GO pilot | Merge di B-11–B-18 (componente fonti), owner editoriale, contenuto approvato |
| B-46–B-50 | (report in conversazione) | READY_FOR_HUMAN_REVIEW, gap espliciti | — |
| B-51–B-60 | (report in conversazione) | READY_FOR_HUMAN_REVIEW, gap espliciti | — |
| B-61–B-65 | `docs/TRUST_LAYER_ATLANTE_VISUALE_PILOT.md` | GO prototipo, NO-GO pilot | Owner editoriale, articolo sorgente, metrica dedicata |
| B-66–B-67 | `docs/TRUST_LAYER_NEWSLETTER_MONETIZATION_READINESS.md` | NO-GO monetizzazione | Merge Trust Layer V1, baseline CWV field, policy disclosure |
| B-68 | `docs/TRUST_LAYER_ROLLBACK_RUNBOOK.md` | — | — |

## PR aperte

1. `feat/public-article-sources-v1` — [PR #516](https://github.com/andrea-bartiromo/quark_blog/pull/516)
2. `feat/public-article-revision-transparency-v1` — [PR #517](https://github.com/andrea-bartiromo/quark_blog/pull/517)
3. `feat/public-author-pages-v1` — [PR #518](https://github.com/andrea-bartiromo/quark_blog/pull/518)
4. `feat/trust-policy-pages-v1` — [PR #519](https://github.com/andrea-bartiromo/quark_blog/pull/519)
5. `docs/cosa-sappiamo-davvero-prototype` — [PR #520](https://github.com/andrea-bartiromo/quark_blog/pull/520)
6. `docs/atlante-visuale-prototype` — [PR #521](https://github.com/andrea-bartiromo/quark_blog/pull/521)

Nessuna PR mergiata: tutte in attesa di review umana.

## Owner richiesto per procedere oltre V1

- **Review umana** delle 4 PR applicative sopra (codice) prima di merge.
- **Owner editoriale** per: contenuto definitivo di `/metodologia`
  (bozza tecnica presente, non testo approvato), pilot "Cosa sappiamo
  davvero", pilot Atlante visuale.
- **Decisione su `verification_notes`**: se in futuro si vuole una nota
  di correzione pubblica, serve una decisione editoriale su un nuovo
  campo dedicato (B-35, BLOCKED_BY_MISSING_EDITORIAL_FIELD).

## Dati mancanti (INSUFFICIENT_DATA ricorrenti)

- Roadmap v14 come documento (non trovata nel repository).
- Dati CWV field/CrUX reali (solo lab, con limiti d'ambiente noti).
- Query concetti-calendario ottobre–dicembre (B-55, dati production non
  letti per prudenza).
- Benchmark TROVA query scientifiche italiane (non costruito, proponibile
  separatamente).

## Ordine raccomandato per l'umano che riprende il lavoro

1. Review + merge `feat/public-article-sources-v1` (valore alto, rischio
   tecnico più delimitato).
2. Review + merge `feat/public-article-revision-transparency-v1`.
3. Review + merge `feat/public-author-pages-v1` (verificare in produzione
   che l'editor citato in `/la-redazione` abbia già articoli pubblicati —
   rischio noto documentato in `TRUST_LAYER_PUBLIC_AUTHOR_PAGES_V1.md`).
4. Review contenuto redazionale + merge `feat/trust-policy-pages-v1`.
5. Decisione umana su owner/contenuto per il pilot "Cosa sappiamo
   davvero" prima di qualunque route pubblica.
6. Stessa decisione per l'Atlante visuale.
7. Comando artisan read-only `content-graph:audit-aliases` (unica
   miglioria P1 segnalata in B-60) come prossima PR separata, se
   approvato.
