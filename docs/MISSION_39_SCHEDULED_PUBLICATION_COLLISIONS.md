# Missione 39 — Scheduled publication collision detection

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness.
**Esito**: implementazione mirata — un vero gap operativo colmato, nessuna
duplicazione.

## Requisito

Rilevare e rendere visibili collisioni tra articoli programmati (outcome, non
un'implementazione specifica imposta dalla spec).

## Stato reale trovato

`PublishScheduledArticles` (il comando che pubblica automaticamente gli
articoli scaduti) protegge già correttamente contro la **doppia
pubblicazione dello stesso articolo** in esecuzioni sovrapposte (lock
transazionale + ricontrollo dello stato). Non è però questo il problema:
nessun controllo esisteva per il caso in cui **due articoli diversi**
ricevano lo stesso identico istante di pubblicazione programmata.

Il form di pianificazione (`resources/views/admin/article-form.blade.php`)
usa un campo data + un campo ora **senza secondi**: nulla impedisce a un
redattore di assegnare per errore lo stesso "giorno e ora" a due articoli
scollegati. Quando lo scheduler gira, li pubblica entrambi nella stessa run,
in un ordine deciso solo dall'`id` (query `orderBy('published_at')`, poi
l'ordine naturale della tabella) — non da una scelta editoriale. Nessuna
sezione esistente della dashboard (né `da_pubblicare`, né
`percorsi_order_health`, che copre solo le collisioni cronologiche
*all'interno* di un singolo Percorso) segnalava questo caso.

## Implementazione

Aggiunto un flag `collision` per riga in `da_pubblicare`
(`EditorialOperationsDashboardService::snapshot()`), calcolato con un
semplice `countBy()` sull'istante ISO tra gli articoli già programmati e già
caricati (stessa collezione usata per `overdue` — nessuna nuova query).
Esposto anche come `pubblicazione_readiness.collision_count` e incluso in
`open_problems_total`, con lo stesso trattamento già riservato a `overdue`.

Vista: badge "· stesso orario di un altro articolo" per riga in "Da
pubblicare", più un avviso nella KPI card in cima quando
`collision_count > 0`.

## Verifica

- `php artisan test --filter=EditorialOperationsDashboardServiceTest` — 2
  nuovi test (coppia in collisione, articolo isolato mai segnalato).
- `test_editor_sees_a_scheduling_collision_warning_when_two_articles_share_the_same_instant`
  in `EditorialOperationsDashboardControllerTest` — prova che l'avviso
  compaia davvero nell'HTML reale della pagina, in entrambe le posizioni.
- Le due formule di recompute di `open_problems_total` già esistenti nei
  test (`test_a_percorso_with_only_an_editorial_advisory...`,
  `test_only_the_overdue_scheduled_article_contributes_the_overdue_term`)
  sono state aggiornate per includere il nuovo termine — stesso pattern già
  applicato in Missione 27 per `contenuti_senza_concept`.
- `php artisan test --filter='EditorialOperationsDashboard|PublishScheduledArticles'`
  — 68/68 passed.
