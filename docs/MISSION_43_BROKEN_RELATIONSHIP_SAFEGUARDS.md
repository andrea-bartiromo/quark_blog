# Missione 43 — Broken relationship safeguards

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness.
**Esito**: `VERIFIED_ALREADY_PRESENT` (+ due test di regressione mancanti,
genuinamente utili, aggiunti).

## Requisito

Verificare che le relazioni tra entità del dominio (articoli, Percorsi,
Concetti, autori) non lascino stati "rotti" quando un lato della relazione
viene eliminato, e rendere visibili eventuali problemi (outcome, non
un'implementazione specifica imposta dalla spec).

## Audit completo delle foreign key coinvolte

Verificate tutte le migration rilevanti — ogni relazione è già protetta:

| Relazione | Comportamento onDelete | Evidenza |
|---|---|---|
| `articles.user_id` | `cascadeOnDelete` | `2026_05_01_085645_create_articles_table.php` |
| `article_content_cluster.*` (pivot Percorsi) | `cascadeOnDelete` su entrambe le colonne | `2026_08_13_080620_*.php` |
| `content_clusters.pillar_article_id` | `nullOnDelete` | `2026_08_13_080630_*.php` — già gestito come `null` legittimo da `PercorsoCoverageAuditService` |
| `article_concepts.*` (pivot Content Graph) | `cascadeOnDelete` su entrambe le colonne | `2026_08_23_150000_create_content_graph_tables.php` |
| `concept_questions.target_article_id` | `nullOnDelete` | idem |
| `article_slug_redirects.article_id` | `cascadeOnDelete` | — |
| `search_console_queries.article_id` | `nullOnDelete` | stato `null` esplicitamente documentato come legittimo |
| `article_link_suggestions.source_article_id` | `cascadeOnDelete` | — |
| `article_link_suggestions.target_article_id` | `nullOnDelete` (cambiato deliberatamente da cascade, con colonna `target_slug` di snapshot) | `2026_08_11_165128_alter_target_article_id_on_article_link_suggestions_to_null_on_delete.php` |
| `content_cluster_subscribers.*` | `cascadeOnDelete` su entrambe le colonne | — |
| `article_category` (pivot categorie) | `cascadeOnDelete` su entrambe le colonne | — |
| `articles.category` (colonna stringa, non FK) | Cancellazione categoria bloccata a livello applicativo se ha ancora articoli | `Admin\CategoryController::destroy()` |

Nessuna riga orfana è possibile in nessuna di queste tabelle. `ContentClusterController`
e `ConceptController` non hanno nemmeno un metodo `destroy()` (nessuna rotta
di cancellazione esiste per Percorsi/Concetti) — lo scenario "cosa succede
se elimino un Percorso/Concetto" è oggi puramente teorico, non un gap reale.

## L'unico punto scoperto: link reali nel body verso un articolo eliminato

`InternalLinkAuditService` (Internal Linking V2, batch precedente) già
analizza il body HTML REALE di ogni articolo (non solo record di
suggerimento) tramite `ArticleLinkInsertionService::internalArticleLinkOccurrences()`
— un vero parsing DOM di `<a href="/articolo/{slug}">`. Se un articolo B è
collegato dal body pubblicato di un articolo A e B viene poi eliminato
(nessun redirect viene creato automaticamente alla cancellazione —
`Article::booted()` non lo fa), il link di A resta fisicamente nel body,
ma l'audit lo classifica correttamente `missing` (link rotto) — già
esposto come "Link rotti" nella pagina `/admin/link-interni` (Missione
42, appena completata).

Il livello SERVIZIO copriva solo il caso "slug mai esistito"
(`test_a_link_to_a_nonexistent_slug_is_classified_as_missing`), mai
esplicitamente il caso editoriale reale "slug esisteva, l'articolo è stato
eliminato" — comportamento identico dal punto di vista del codice, ma vale
la pena provarlo esplicitamente perché è lo scenario che accade davvero
("elimino un vecchio articolo" è un'azione editoriale normale). Il livello
HTTP non aveva alcuna prova che questo scenario comparisse sulla pagina
reale.

## Gap reale colmato

Aggiunti due test di regressione, nessuna modifica al servizio o alla
vista:

- `test_a_link_to_an_article_that_was_since_deleted_is_classified_as_missing`
  in `tests/Feature/InternalLinkAuditCommandTest.php` — prova lo scenario
  di cancellazione a livello servizio.
- `test_editor_sees_a_broken_link_row_after_the_target_article_is_deleted`
  in `tests/Feature/Admin/InternalLinkAuditControllerTest.php` — prova che
  compaia davvero sulla pagina reale, con link all'articolo sorgente da
  correggere.

## Verifica

- `php artisan test --filter='InternalLinkAudit'` — 45/45 passed.
- Nessuna nuova query, nessuna nuova regola di dominio, nessun nuovo file
  di produzione modificato.
