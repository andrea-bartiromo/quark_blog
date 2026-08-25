# Missione 54 — Percorsi con membri non pubblicabili

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

## Gap trovato

`PercorsoCoverageAuditService::clusterRow()` calcola già
`non_publishable_members` per ogni Percorso (articoli membri ancora in
bozza o revisione), aggregato in `paths_with_non_publishable_members` da
`audit()` — ma nessuna vista lo leggeva mai, nonostante
`EditorialOperationsDashboardService` chiamasse già `audit()` per altre
chiavi dello stesso metodo (Missioni 50, 53).

## Decisione: conta come problema, a differenza della Missione 53

A differenza di `articles_in_multiple_paths` (Missione 53), il cui
`policy_notes.multiple_paths_are_reported_not_failed` dichiara
esplicitamente che non è un errore, qui **non esiste alcuna policy_note
equivalente**: un Percorso che elenca formalmente come membro un
articolo non ancora pubblicabile è un'incoerenza strutturale reale
(stesso trattamento già riservato a `paths_with_incoherent_pillar`,
Missione 50) — se il Percorso venisse pubblicato/completato così com'è,
un lettore incontrerebbe un passo mancante. Per questo motivo
`percorsi_non_publishable_members` **conta** in `open_problems_total`,
un termine per Percorso coinvolto (non per singolo membro).

## Soluzione

- `EditorialOperationsDashboardService::snapshot()` mappa
  `paths_with_non_publishable_members` in
  `percorsi_non_publishable_members` (cluster id/nome/elenco membri con
  titolo e stato), nessuna nuova query.
- `resources/views/admin/editorial-operations-dashboard.blade.php`
  aggiunge la sezione "Percorsi con membri non pubblicabili" (stesso
  stile "problema" delle sezioni esistenti, sempre visibile con
  messaggio di stato quando vuota), subito dopo "Pillar Percorsi".
- Nessuna nuova card di riepilogo in cima al dashboard: il conteggio è
  già visibile tramite `open_problems_total`, e il dashboard ha già
  circa dieci card — un'ulteriore card dedicata avrebbe iniziato a
  sovraccaricare la vista senza aggiungere informazione realmente nuova.

## File toccati

- `app/Services/EditorialOperations/EditorialOperationsDashboardService.php`
- `resources/views/admin/editorial-operations-dashboard.blade.php`
- `tests/Feature/EditorialOperationsDashboardServiceTest.php`
- `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php`

## Test

- `test_a_percorso_listing_a_draft_article_as_a_member_is_reported_with_a_non_publishable_member`
- `test_a_percorso_listing_only_published_members_is_never_reported_with_a_non_publishable_member`
- `test_a_percorso_with_a_non_publishable_member_counts_toward_open_problems`
- `test_editor_sees_a_percorso_with_a_non_publishable_member_rendered_on_the_page` (HTTP)

Suite `EditorialOperationsDashboardServiceTest|EditorialOperationsDashboardControllerTest|PercorsoCoverageAuditServiceTest`
completa: 84 test, 328 assertion, verde. Query-count budget invariato.
Repeatability gate: 5/5 run consecutivi verdi.
