# Missione 48 — Colonna "Pagina" nella lista opportunità di ricerca

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

## Gap trovato

`SearchOpportunityScoringService` calcola già `pageUrl` per tre dei sei
tipi di opportunità (`good_position_low_ctr`, `high_impression_low_ctr`,
`near_page_one` — vedi i rispettivi metodi builder e
`SearchOpportunity::$pageUrl`), ma
`resources/views/admin/search-opportunities/index.blade.php` non
mostrava mai quale pagina reale del sito rankeggia per quella query: un
editor vedeva query/articolo/CTR/posizione ma doveva indovinare quale
URL sistemare.

## Soluzione

Aggiunta una colonna "Pagina" alla tabella, tra "Articolo" e
"Impression":

- Se `pageUrl` è presente ed è un URL `http(s)://` legittimo, viene
  mostrato come link (`target="_blank" rel="noopener noreferrer"`, il
  path troncato per leggibilità) che apre la pagina reale del sito.
- Se `pageUrl` è presente ma non ha uno schema http(s) riconosciuto
  (dato CSV non validato allo schema in `SearchConsoleCsvImporter`),
  viene mostrato come testo semplice, mai come link cliccabile — difesa
  minima contro un valore anomalo nel CSV importato.
- Se `pageUrl` è `null` (query in crescita, ricerca interna senza
  risultati, nessuna landing page dedicata), viene mostrato un trattino,
  coerente con le altre colonne opzionali della stessa tabella.

## File toccati

- `resources/views/admin/search-opportunities/index.blade.php`
- `tests/Feature/Admin/SearchOpportunityControllerTest.php`

## Test

- `test_index_shows_the_landing_page_url_when_the_scoring_service_computes_one`
- `test_index_shows_a_dash_for_opportunities_without_a_page_url`

Suite `SearchOpportunity|SearchConsole` completa: 66 test, 161 assertion,
verde. Repeatability gate: 5/5 run consecutivi verdi.
