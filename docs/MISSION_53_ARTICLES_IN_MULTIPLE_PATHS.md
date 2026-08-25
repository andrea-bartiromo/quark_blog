# Missione 53 — Contenuti in più Percorsi sul dashboard editoriale

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

## Gap trovato

`PercorsoCoverageAuditService::audit()` calcola già
`articles_in_multiple_paths` (con `path_count` e l'elenco degli slug dei
Percorsi coinvolti per ogni articolo), e il suo stesso
`policy_notes.multiple_paths_are_reported_not_failed` promette
esplicitamente che questo venga "reported" — una promessa mai
mantenuta, perché nessuna vista leggeva mai questa chiave.
`EditorialOperationsDashboardService` chiama già `audit()` (per
`published_without_path`, `scheduled_without_path`,
`paths_with_incoherent_pillar` — Missione 50), ma scartava
silenziosamente `articles_in_multiple_paths`.

## Soluzione

- `EditorialOperationsDashboardService::snapshot()` ora espone
  `articles_in_multiple_paths` (dati già caricati da `audit()`, nessuna
  nuova query), **deliberatamente escluso da `open_problems_total`** —
  coerente con la policy "reported, not failed": un articolo in più
  Percorsi è un fatto editoriale legittimo, non un'anomalia da contare.
- `resources/views/admin/editorial-operations-dashboard.blade.php`
  aggiunge una sezione "Contenuti in più Percorsi", visibile solo
  quando non vuota (a differenza delle sezioni "problema" esistenti,
  che mostrano sempre un messaggio di stato — qui l'assenza del
  fenomeno non è degna di nota, essendo puramente informativo), con
  link diretto alla modifica dell'articolo.

## File toccati

- `app/Services/EditorialOperations/EditorialOperationsDashboardService.php`
- `resources/views/admin/editorial-operations-dashboard.blade.php`
- `tests/Feature/EditorialOperationsDashboardServiceTest.php`
- `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php`

## Test

- `test_an_article_belonging_to_two_percorsi_is_listed_as_belonging_to_multiple_paths`
- `test_an_article_in_multiple_paths_never_counts_as_an_open_problem`
- `test_an_article_belonging_to_only_one_percorso_is_never_listed_as_belonging_to_multiple_paths`
- `test_editor_sees_an_article_belonging_to_multiple_percorsi_rendered_on_the_page` (HTTP)

Suite `EditorialOperationsDashboardServiceTest|EditorialOperationsDashboardControllerTest|PercorsoCoverageAuditServiceTest`
completa: 80 test, 314 assertion, verde. Query-count budget invariato.
Repeatability gate: 5/5 run consecutivi verdi.
