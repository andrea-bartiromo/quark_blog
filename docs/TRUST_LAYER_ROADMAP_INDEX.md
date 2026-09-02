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

## Workstream parallelo scoperto e risolto (2026-09-02)

Verificando lo stato del repository dopo l'apertura delle PR sopra, sono
emerse **9 altre PR aperte** (#507–#515), di un agente/sessione diverso con
una propria numerazione "Prompt 1–70" indipendente da questa. Includono
anche `docs/KAIRUS_TECHNICAL_ROADMAP_V14.md` (PR #513) — la "roadmap v14"
che questo cantiere aveva classificato INSUFFICIENT_DATA per tutta la sua
durata: esiste, ma solo su un branch mai mergiato in `main`, quindi
invisibile a un audit basato su `origin/main` (non un errore di questo
cantiere — un vero gap di coordinamento tra agenti).

Due sovrapposizioni dirette, stessi file, soluzioni diverse:

- **#508** `feat/public-article-sources` vs **#516** (questo cantiere) —
  confrontate, sintetizzato il meglio delle due in #516 (riconoscimento
  DOI adottato da #508; `nofollow` e provenienza fonti separata mantenuti
  da #516). Push del 2026-09-02, PR #516 aggiornata. **Raccomandato
  chiudere #508 come superseded.**
- **#509** `feat/author-trust-layer-v1` vs **#518** (questo cantiere) —
  confrontate: #509 non chiudeva davvero il gap di esposizione (solo
  `noindex`, mai un 404 reale). Sintetizzato in #518: gate 404 mantenuto,
  innestate le aggiunte valide di #509 (LinkedIn, jobTitle, etichetta di
  ruolo veritiera, email mai pubblica per nessun ruolo). Push del
  2026-09-02, PR #518 aggiornata. **Raccomandato chiudere #509 come
  superseded.**

Le altre 7 PR del workstream parallelo (#507, #510–#513, #515) non hanno
conflitti di file diretti con questo cantiere. **#510** ha già costruito
il benchmark TROVA che B-57 (sotto) segnalava come "non ancora costruito,
proponibile separatamente" — quella nota è superata, non serve rifarlo.

Nessuna PR altrui è stata chiusa da questo agente: la decisione di
chiudere #508/#509 resta a un umano, per rispetto del lavoro di un altro
workstream che potrebbe essere ancora attivo.

## Owner richiesto per procedere oltre V1

- **Review umana** delle 4 PR applicative sopra (codice) prima di merge.
- **Owner editoriale** per: contenuto definitivo di `/metodologia`
  (bozza tecnica presente, non testo approvato), pilot "Cosa sappiamo
  davvero", pilot Atlante visuale.
- **Decisione su `verification_notes`**: se in futuro si vuole una nota
  di correzione pubblica, serve una decisione editoriale su un nuovo
  campo dedicato (B-35, BLOCKED_BY_MISSING_EDITORIAL_FIELD).

## Dati mancanti (INSUFFICIENT_DATA ricorrenti)

- Roadmap v14 come documento (non in `main`; esiste su un branch di un
  workstream parallelo, PR #513 — vedi sopra).
- Dati CWV field/CrUX reali (solo lab, con limiti d'ambiente noti).
- Query concetti-calendario ottobre–dicembre (B-55, dati production non
  letti per prudenza).
- ~~Benchmark TROVA query scientifiche italiane~~ — già costruito nel
  workstream parallelo, PR #510 (`test/trova-human-reviewed-benchmark`).

## Ordine raccomandato per l'umano che riprende il lavoro

0. Chiudere **#508** e **#509** come superseded (o confermare il
   contrario, se si preferisce la loro versione — vedi confronto sopra),
   prima di mergiare qualunque cosa: evita conflitti Git e un'esperienza
   pubblica incoerente.
1. Review + merge `feat/public-article-sources-v1` (#516, ora con
   riconoscimento DOI — valore alto, rischio tecnico più delimitato).
2. Review + merge `feat/public-article-revision-transparency-v1` (#517).
3. Review + merge `feat/public-author-pages-v1` (#518, ora con
   LinkedIn/jobTitle/etichetta ruolo) — verificare in produzione che
   l'editor citato in `/la-redazione` abbia già articoli pubblicati,
   rischio noto documentato in `TRUST_LAYER_PUBLIC_AUTHOR_PAGES_V1.md`.
4. Review contenuto redazionale + merge `feat/trust-policy-pages-v1`
   (#519).
5. Decisione umana su owner/contenuto per il pilot "Cosa sappiamo
   davvero" prima di qualunque route pubblica.
6. Stessa decisione per l'Atlante visuale.
7. Comando artisan read-only `content-graph:audit-aliases` (unica
   miglioria P1 segnalata in B-60) come prossima PR separata, se
   approvato.
8. Valutare le rimanenti PR del workstream parallelo (#507, #510–#513,
   #515) con lo stesso criterio: nessun conflitto diretto rilevato con
   questo cantiere, ma non riviste nel merito qui.
