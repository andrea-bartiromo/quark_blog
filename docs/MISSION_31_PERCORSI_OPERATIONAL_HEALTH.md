# Missione 31 — Percorsi operational health

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase D.
**Esito**: `VERIFIED_ALREADY_PRESENT` (+ una copertura di regressione HTTP mancante, genuinamente utile, aggiunta).

## Requisito

Una vista operativa sullo stato di salute dei Percorsi (outcome, non
un'implementazione specifica imposta dalla spec).

## Stato reale trovato

La capacità esiste già, integralmente, ed è il risultato di più missioni
di un batch precedente (Mission 02, 04, 14, 15, 37), tutte già su `main`
prima di questo batch. Due sezioni distinte e deliberatamente non
appiattite in una sola, ciascuna con la propria semantica:

- **`percorsi_readiness`** (sezione "Percorsi non pronti"): per ogni
  `ContentCluster`, `PercorsoPublicationReadinessService::evaluate()`
  valuta sia i campi editoriali del Percorso stesso (nome, slug,
  descrizioni, SEO, cover, takeaways, ecc.) sia — tramite
  `ContentClusterHealth::evaluate()`, già incorporato al suo interno —
  la salute strutturale (pillar, membri pubblici, ordering). Ogni riga
  espone `status` (READY / READY WITH WARNINGS / NOT READY),
  `error_count`, `warning_count`, e i `codes` ERROR/WARNING effettivi
  (mai i codici solo INFO, es. `SCHEDULING_NOT_AVAILABLE`). Solo i
  Percorsi non READY compaiono nella sezione.
- **`percorsi_order_health`** (sezione "Sequenza Percorsi"):
  `PercorsoCoverageAuditService::editorialOrderHealth()` classifica le
  anomalie di posizione/sequenza in tre categorie (`structural_error`,
  `publication_warning`, `editorial_advisory`, quest'ultima mai
  bloccante), più un conteggio dedicato di articoli pubblicati "dietro
  un gap" nel prefisso pubblico.
- **Deduplicazione della causa condivisa** (Mission 14/15): quando lo
  stesso segnale (es. `complete_with_hidden_remainder`) alimenta
  entrambe le sezioni per lo stesso Percorso, la riga in "Percorsi non
  pronti" lo dichiara esplicitamente ("Segnalato anche in Sequenza
  Percorsi qui sotto") invece di presentarlo come due problemi
  scollegati.
- Entrambe le sezioni contribuiscono a `salute_operativa.open_problems_total`
  (Mission 26), e il contesto di catalogo (`active_percorsi_total`) è
  già visibile nella striscia di salute in cima alla pagina.

Nessuna duplicazione necessaria: la combinazione delle due sezioni copre
già l'intero outcome richiesto dalla Missione 31, con una disciplina di
non-sovrapposizione già collaudata da test dedicati (vedi
`test_a_percorso_with_both_readiness_findings_and_order_health_issues_appears_in_both_sections`
e `test_shared_hidden_remainder_cause_is_flagged_as_also_in_order_health`
in `EditorialOperationsDashboardServiceTest`).

## Gap reale trovato e colmato

Il livello SERVIZIO è testato a fondo (38 riferimenti a
`percorsi_readiness`/`percorsi_order_health` nel test file dedicato,
inclusi ordinamento, deduplicazione causa condivisa, e un vincolo di
performance sul conteggio query a scala realistica). Il livello
CONTROLLER/HTTP, però, non aveva mai provato che le liste per-Percorso
(nome, stato, conteggi, codici) comparissero davvero sulla pagina reale
— solo la singola frase del "gap" dentro "Sequenza Percorsi" era
coperta via HTTP (Missione 21, batch precedente).

Aggiunto
`test_editor_sees_percorso_readiness_and_order_health_rows_rendered_on_the_page`
in `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php`:
riusa lo stesso fixture "misto" già validato lato servizio (Percorso con
campi editoriali mancanti + membro con `position=0`) e verifica che sia
il nome del Percorso sia la frase di deduplicazione causa condivisa
compaiano nell'HTML reale della pagina.

Nessuna modifica al servizio o alla vista è stata necessaria: si tratta
di sola copertura di regressione su un comportamento già corretto.

## Verifica

- `php artisan test --filter=EditorialOperationsDashboard` — 48/48 passed
  (era 47/47 prima di questa missione).
- Nessuna nuova query, nessuna nuova regola di dominio, nessun nuovo file
  di produzione modificato.
