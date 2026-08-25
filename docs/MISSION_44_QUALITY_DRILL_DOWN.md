# Missione 44 — Quality drill-down

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness (missione conclusiva di fase — checkpoint dopo questa).
**Esito**: implementazione mirata — un vero gap di navigabilità colmato,
nessuna nuova regola di dominio.

## Requisito

Permettere di "scendere" da un riepilogo qualità aggregato ai singoli
articoli interessati (outcome, non un'implementazione specifica imposta
dalla spec).

## Stato reale trovato

Due livelli di drill-down erano già completi:

1. **Per articolo**: la card "Qualità editoriale" nella pagina di modifica
   (`resources/views/partials/editorial-quality-gate.blade.php`) espone
   già ogni singolo controllo (label, stato, messaggio) dietro un
   `<details>` — progressive disclosure completa.
2. **Per problema, in forma aggregata**: `EditorialQualityAuditService::
   mostFrequentIssues()` (Missione 35) calcola già "Problemi più
   frequenti" con tanto di `code` machine-readable, `label` e `count` —
   già mostrato sulla pagina sitewide `/admin/qualita-editoriale`.

Mancava però il collegamento tra questi due livelli: "12 articoli senza
fonte primaria" era visibile, ma nessun modo di arrivare ai 12 articoli
effettivi senza scorrere a mano l'intera tabella "Articoli da verificare"
— l'unico drill-down disponibile richiedeva di aprire ogni articolo
flaggato uno per uno per scoprire se quello fosse tra i 12.

## Implementazione

Aggiunto un filtro `problema` (query string) a
`EditorialQualityAuditController::index()`: riusa lo stesso `code` già
calcolato da `mostFrequentIssues()` (validato contro l'elenco reale di
codici presenti in QUESTO audit — mai un valore accettato alla cieca),
filtra gli `entries` già prodotti da `audit()` per quelli il cui report
contiene quel codice tra i propri `issues()`. Nessun ricalcolo dei
controlli.

Vista: ogni riga di "Problemi più frequenti" è ora un link che imposta il
filtro (mantenendo lo stato selezionato); un banner "Filtrato per: ―
Rimuovi filtro" quando attivo; una colonna "Motivo" aggiuntiva nella
tabella, che mostra il messaggio specifico di QUEL controllo per ogni
articolo filtrato — non solo il livello generico già presente.

## Verifica

- `test_the_issue_filter_narrows_the_flagged_table_to_only_matching_articles`
  — due articoli con problemi diversi (`cover_present` vs
  `sources_present`), il filtro mostra solo quello corretto.
- `test_an_unknown_issue_code_is_ignored_rather_than_erroring` — stesso
  pattern di tolleranza già usato per il filtro `stato` esistente.
- `php artisan test --filter='EditorialQuality'` — 104/104 passed.
- Nessuna nuova query, nessun nuovo file di produzione oltre al filtro
  nel controller esistente.
