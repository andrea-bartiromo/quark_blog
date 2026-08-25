# Missione 28 — Upcoming publications panel

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase D.
**Esito**: `VERIFIED_ALREADY_PRESENT` (+ una copertura di regressione mancante, genuinamente utile, aggiunta).

## Requisito

Un pannello che mostri le pubblicazioni programmate imminenti (esito, non
implementazione specifica imposta dalla spec).

## Stato reale trovato

Il pannello esiste già, integralmente, dalla Mission 09 ("Editorial
Operations Dashboard V1 Convergence") ed è stato esteso (ordinamento
esplicito, flag `overdue`) dalla Mission 37 di uno storico batch
precedente — entrambe già su `main` prima di questo batch:

- **Servizio** (`EditorialOperationsDashboardService::snapshot()`,
  chiave `da_pubblicare`): interroga `Article::whereIn('status', [PUBLISHED,
  SCHEDULED])->orderBy('published_at')->orderBy('id')`, poi filtra i soli
  `STATUS_SCHEDULED`. Essendo la query di base già ordinata per
  `published_at` ascendente, il risultato è naturalmente in ordine
  cronologico (il più imminente per primo) — nessun secondo `sortBy`
  necessario. Ogni riga espone `article_id`, `title`, `slug`,
  `published_at` (ISO 8601) e `overdue` (bool: `published_at` nel passato
  ma l'articolo non è ancora stato pubblicato dallo scheduler).
- **Vista** (`resources/views/admin/editorial-operations-dashboard.blade.php`):
  - KPI strip: conteggio "Da pubblicare" in cima alla pagina.
  - Sezione dedicata "Da pubblicare": lista ogni articolo programmato con
    link diretto alla modifica, data/ora localizzata (`Europe/Rome`,
    formato italiano), ed evidenzia in rosso "· in ritardo" quando
    `overdue` è vero.
  - Stato vuoto esplicito ("Nessun articolo programmato in attesa.").
- **`salute_operativa`**: il conteggio degli articoli in ritardo
  (`overdue === true`) contribuisce a `open_problems_total`, quindi un
  articolo scaduto in stato "da pubblicare" fa correttamente uscire la
  dashboard dallo stato "SANA".

Nessuna duplicazione necessaria: il pannello copre già l'intero outcome
richiesto dalla Missione 28.

## Gap reale trovato e colmato

Nessun test esistente provava che **più** articoli programmati compaiano
nel pannello in ordine cronologico (proprietà su cui si basa
esplicitamente il commento "Mission 37" nel codice, ma mai esercitata con
più di un articolo scaduto/futuro insieme). Aggiunto
`test_upcoming_publications_are_listed_soonest_first` in
`tests/Feature/EditorialOperationsDashboardServiceTest.php`: crea tre
articoli programmati in ordine di creazione volutamente invertito
rispetto alla data di pubblicazione (+5 giorni, +2 ore, +2 giorni) e
verifica che `da_pubblicare` li restituisca nell'ordine soonest-first
(+2 ore, +2 giorni, +5 giorni) — non l'ordine di inserimento.

Nessuna modifica al servizio o alla vista è stata necessaria: si tratta
di sola copertura di regressione su un comportamento già corretto.

## Verifica

- `php artisan test --filter=EditorialOperationsDashboard` — 41/41 passed
  (era 40/40 prima di questa missione).
- Nessuna nuova query, nessuna nuova regola di dominio, nessun nuovo file
  di produzione modificato.
