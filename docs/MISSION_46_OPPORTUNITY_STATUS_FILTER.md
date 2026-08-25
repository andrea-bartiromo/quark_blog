# Missione 46 — Filtro per stato nella lista opportunità di ricerca

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

## Gap trovato

`/admin/opportunita-di-ricerca` permette già di impostare lo stato di ogni
opportunità per riga (`updateStatus()`, `SearchOpportunityStatusService`) e lo
stato corrente è già mostrato accanto a ciascuna riga tramite un `<select>`.
Esisteva però già un filtro identico per `tipo` (`$request->input('tipo')`),
ma nessun filtro equivalente per `stato`: un'opportunità già segnata come
"gestita" o "ignorata" restava sempre mescolata con quelle nuove, rendendo la
pagina via via più rumorosa a mano a mano che il team lavorava le
opportunità.

## Soluzione

Stesso identico pattern già in uso per il filtro `tipo`:

- `SearchOpportunityController::index()` legge `?stato=`, lo valida contro
  `SearchOpportunityStatus::statusOptions()` (un valore sconosciuto viene
  ignorato silenziosamente, mai un errore), poi filtra `$opportunities` dopo
  aver calcolato `$opportunityStatuses` (una singola query per l'intero
  elenco, via `SearchOpportunityStatusService::statusesFor()` — mai una query
  per riga).
- La vista aggiunge un secondo `<select>` "Stato" nello stesso form GET già
  esistente per il filtro tipo, con lo stesso comportamento
  auto-submit-on-change.
- Il messaggio di stato vuoto menziona ora anche il filtro stato applicato.

Nessuna nuova icona di navigazione è stata aggiunta (nessun rischio di
collisione emoji nella sidebar, vedi la lezione della Missione 42).

## Bug di test scoperto e corretto durante la verifica

Il primo tentativo di test per questa missione usava una riga
`SearchConsoleQuery` con `article_id => null`, `impressions => 200`,
`ctr => 0.001`, `position => 25`. Questi valori fanno scattare **due**
opportunità distinte per la stessa query testuale:

- `high_impression_low_ctr` (score ~1, key include `page_url`)
- `no_strong_landing_page` (score 200, nessun articolo collegato, key senza
  `page_url`)

Impostando lo stato solo sulla prima (quella restituita da `->first()`, la
più alta in score) l'altra restava "new" e mostrava lo stesso testo di query,
facendo fallire `assertDontSee()` sul filtro `?stato=new` — non un bug del
controller, ma un'ambiguità nei dati del test. Corretto collegando la riga a
un articolo reale (`article_id`), che disattiva la generazione
`no_strong_landing_page` per quella query e lascia una sola opportunità.

## File toccati

- `app/Http/Controllers/Admin/SearchOpportunityController.php`
- `resources/views/admin/search-opportunities/index.blade.php`
- `tests/Feature/Admin/SearchOpportunityControllerTest.php`

## Test

- `test_index_can_be_filtered_by_status`
- `test_an_unknown_status_filter_is_ignored_silently`

Suite `SearchOpportunity|SearchConsole` completa: 64 test, 156 assertion,
verde. Repeatability gate: 5/5 run consecutivi verdi.
