# Missione 50 — Coerenza del pillar dei Percorsi sul dashboard editoriale

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

Nota: non confondere con `docs/MISSION_50_NIGHT_RELEASE_GATE_HANDOFF.md`,
che appartiene a un batch autonomo precedente e non correlato ("KAIRUS
Night Autonomous Batch") — numeri di missione riusati fra batch diversi,
nessuna relazione di contenuto.

## Gap trovato

`PercorsoCoverageAuditService::audit()` calcola già
`paths_with_incoherent_pillar` (via `clusterRow()`), classificando ogni
Percorso il cui `pillar_article_id` non è coerente in uno di tre codici:
`pillar_target_missing`, `pillar_not_in_path`, `pillar_not_publishable`.
Nessuna vista lo aveva mai letto:

- `EditorialOperationsDashboardService::snapshot()` chiamava già
  `audit()` per `published_without_path`/`scheduled_without_path`, ma
  scartava silenziosamente `paths_with_incoherent_pillar`.
- `PercorsoPublicationReadinessService` controlla il pillar solo rispetto
  al prefisso pubblico raggiungibile (`pillar_outside_reachable_prefix`),
  mai la sua coerenza strutturale (pillar mai aggiunto come membro,
  pillar ancora in bozza/revisione).

Un Percorso con `pillar_article_id` impostato ma mai aggiunto come
membro effettivo del Percorso, o con un pillar ancora in bozza,
risultava quindi invisibile su ogni superficie admin.

## Nota tecnica: `pillar_target_missing` è oggi irraggiungibile

`content_clusters.pillar_article_id` ha una FK con `nullOnDelete()`
(migrazione `2026_08_13_080630_...`): quando l'articolo pillar viene
eliminato, la colonna viene azzerata nella stessa operazione. Il codice
`pillar_target_missing` (target eliminato) non può quindi verificarsi
tramite `Article::delete()` nel flusso applicativo normale — coerente
con il fatto che nessun test nel repository lo copriva già prima di
questa missione. La label per questo codice resta comunque gestita
difensivamente in `pillarIssueLabel()`, per completezza rispetto ai tre
codici che il servizio può teoricamente produrre.

## Soluzione

- `EditorialOperationsDashboardService::snapshot()` ora mappa
  `paths_with_incoherent_pillar` in `percorsi_pillar_issues` (cluster
  id/nome/codice/etichetta leggibile), lo somma a `open_problems_total`,
  e lo espone nello snapshot. Nessuna nuova query: dati già caricati da
  `$this->percorsoCoverage->audit()`.
- `resources/views/admin/editorial-operations-dashboard.blade.php`
  aggiunge una card di riepilogo ("Pillar Percorsi") e una sezione
  dedicata, stesso stile delle sezioni "Percorsi non pronti"/"Sequenza
  Percorsi" già esistenti, con link a `admin.content-clusters.edit`.

## File toccati

- `app/Services/EditorialOperations/EditorialOperationsDashboardService.php`
- `resources/views/admin/editorial-operations-dashboard.blade.php`
- `tests/Feature/EditorialOperationsDashboardServiceTest.php`
- `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php`

## Test

- `test_a_percorso_whose_pillar_is_not_a_member_of_the_path_is_reported_with_an_incoherent_pillar`
- `test_a_percorso_whose_pillar_is_still_a_draft_is_reported_with_an_incoherent_pillar`
- `test_a_percorso_with_a_coherent_pillar_is_never_reported_as_incoherent`
- `test_editor_sees_a_percorso_with_an_incoherent_pillar_rendered_on_the_page` (HTTP)

Le due ricostruzioni esistenti della formula `open_problems_total`
(`test_an_editorial_advisory_only_percorso_never_counts_as_an_open_problem`,
`test_only_the_overdue_scheduled_article_contributes_the_overdue_term`)
sono state aggiornate per includere il nuovo termine, restando accurate
anziché coincidentalmente corrette a zero contributo.

Suite `EditorialOperationsDashboardServiceTest|EditorialOperationsDashboardControllerTest|PercorsoCoverageAuditServiceTest`
completa: 75 test, 299 assertion, verde. Query-count budget
(`test_query_count_stays_within_a_reasonable_ceiling_for_a_realistic_number_of_percorsi`,
`test_query_count_does_not_grow_with_article_count`) invariato.
Repeatability gate: 5/5 run consecutivi verdi.
