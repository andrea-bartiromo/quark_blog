# Missione 52 — Articoli sorgente coinvolti nel second read

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

## Gap trovato

`ContinuationAnalyticsService::siteWideTotals()` calcola già
`source_articles_engaged` (quanti articoli sorgente distinti hanno
generato almeno un'impression "Continua da qui" nel periodo, deliberatamente
mai limitato dal `limit` di visualizzazione del breakdown — vedi il
docblock del metodo), già coperto da un test di servizio
(`test_site_wide_totals_are_never_capped_by_the_breakdown_display_limit`),
ma né la card "Continua da qui" del dashboard editoriale né la pagina
dedicata `/admin/second-read` lo mostravano mai — solo tasso e conteggio
totale erano visibili.

Questo è un segnale distinto, non un duplicato: l'ampiezza del
coinvolgimento (quanti articoli diversi generano second read) è
un'informazione diversa dalla sua intensità (quante second read in
totale, o con che tasso) — pochi articoli con un tasso altissimo
raccontano una storia editoriale diversa da molti articoli che
contribuiscono ciascuno un po'.

## Nota su un'area esplicitamente fuori scope

Durante l'investigazione per questa missione, un candidato gap nel
Content Graph (`ContentGraphCoverageService::summary()['concepts']['active_without_article_link']`
e `['questions']['active_concepts_without_questions']`, non mostrati
sul dashboard) è stato scartato: il docblock di
`EditorialOperationsDashboardService::snapshot()` riserva esplicitamente
"la diagnostica per-item più approfondita" su questi dati alla Fase G
(Missioni 55-64) di questo stesso batch — non un gap dimenticato, ma
una scelta architetturale già documentata da rispettare.

## Soluzione

- `resources/views/admin/second-read-analytics/index.blade.php` — aggiunta
  una quarta card statistica "Articoli sorgente coinvolti" alla griglia
  esistente, stesso stile delle altre tre.
- `resources/views/admin/editorial-operations-dashboard.blade.php` — la
  card "Continua da qui" ora include il numero nella didascalia
  esistente ("N seconde letture su M articoli sorgente"), senza
  aggiungere una card separata (il dato è complementare, non abbastanza
  centrale da giustificare una nuova card sul dashboard principale).

Nessuna nuova query in nessuno dei due casi: il dato è già presente su
`$totals`/`$snapshot['second_read']`.

## File toccati

- `resources/views/admin/second-read-analytics/index.blade.php`
- `resources/views/admin/editorial-operations-dashboard.blade.php`
- `tests/Feature/SecondReadAnalyticsV2Test.php`
- `tests/Feature/EditorialOperationsDashboardServiceTest.php`
- `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php`

## Test

- `test_the_admin_page_shows_how_many_distinct_source_articles_are_engaged`
- `test_editor_sees_how_many_source_articles_are_engaged_on_the_second_read_card`
- `test_second_read_totals_reflect_real_continuation_events` (estesa con
  l'assert su `source_articles_engaged`)

Suite `SecondReadAnalyticsV2Test|EditorialOperationsDashboardServiceTest|EditorialOperationsDashboardControllerTest`
completa: 83 test, 329 assertion, verde. Repeatability gate: 5/5 run
consecutivi verdi.
