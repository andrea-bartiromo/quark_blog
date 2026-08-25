# Missione 49 — Il Radar rispetta lo stato editoriale delle opportunità

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

Nota: non confondere con `docs/SECURITY_LEAK_REGRESSION_GATE_MISSION49.md`,
che appartiene a un batch autonomo precedente e non correlato ("KAIRUS
Night Autonomous Batch", Fase H) — numeri di missione riusati fra
batch diversi, nessuna relazione di contenuto.

## Gap trovato

`SearchOpportunityStatusService` (workflow nuova/vista/gestita/ignorata
per le opportunità di Search Console, gestito da
`/admin/search-opportunities`) portava nel suo stesso docblock la nota
"non tocca mai EditorialRadarService (dominio della Radar dell'altra
corsia, non ancora su main)" — un isolamento deliberato ma solo
temporaneo, dovuto al fatto che al momento della sua creazione Radar non
esisteva ancora su main.

Ora Radar esiste (`EditorialRadarProviderGraphService` →
`SearchConsoleOpportunityProvider` → card "Opportunità" su
`/admin/operazioni-editoriali`), ma quell'isolamento non era mai stato
rimosso: `SearchConsoleOpportunityProvider::opportunities()` non
consultava mai `SearchOpportunityStatusService`. Il risultato pratico:
un'opportunità che un redattore aveva già segnato "Gestita" o "Ignorata"
da `/admin/search-opportunities` continuava a ricomparire per sempre
nella card del dashboard principale — il workflow di stato esisteva ma
non aveva alcun effetto lì dove l'editor guarda per prima cosa ogni
giorno.

## Soluzione

`SearchConsoleOpportunityProvider` ora inietta
`SearchOpportunityStatusService` e chiama `statusesFor($signals)` **una
sola volta** (stesso principio "una query per l'intero elenco, mai una
per riga" già in uso altrove), poi esclude dal risultato ogni segnale il
cui stato è `actioned` o `dismissed`. Lo stato `reviewed` ("Vista")
resta invece visibile deliberatamente: significa solo "notata", non
"chiusa" — un'opportunità ancora aperta non deve sparire dal radar solo
perché qualcuno l'ha guardata.

Verificato che il budget di query del provider (già testato con
`test_provider_query_count_is_bounded_and_does_not_scale_per_signal`,
soglia ≤ 6, indipendente dal numero di segnali) resta rispettato con la
query aggiuntiva.

Aggiornato anche il docblock di `SearchOpportunityStatusService` per
rimuovere la nota di isolamento ormai obsoleta.

## File toccati

- `app/Services/EditorialRadar/Providers/SearchConsoleOpportunityProvider.php`
- `app/Services/SearchConsole/SearchOpportunityStatusService.php` (solo docblock)
- `tests/Feature/SearchConsoleRadarProviderTest.php`

## Test

- `test_an_actioned_opportunity_no_longer_appears_on_the_radar`
- `test_a_dismissed_opportunity_no_longer_appears_on_the_radar`
- `test_a_merely_reviewed_opportunity_still_appears_on_the_radar`

Suite `SearchOpportunity|SearchConsole|EditorialRadar` completa (inclusi
i 3 test nuovi di questa missione): 72 test, 174 assertion, verde.
Repeatability gate: 5/5 run consecutivi verdi.
