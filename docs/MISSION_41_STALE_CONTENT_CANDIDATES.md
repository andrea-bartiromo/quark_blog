# Missione 41 — Stale content candidates

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness.
**Esito**: implementazione mirata — un vero gap di visibilità colmato,
nessuna soglia di anzianità inventata.

## Requisito

Individuare e rendere visibili candidati a contenuto "stale" (outcome, non
un'implementazione specifica imposta dalla spec).

## Attenzione esplicita a non inventare una soglia di anzianità

`ArticleContentHealthService::freshness()` esclude già esplicitamente
questa strada: "Nessuna soglia editoriale di anzianità è definita nel
dominio corrente; la foundation non inventa un limite temporale." Qualsiasi
regola tipo "più vecchio di N giorni" sarebbe stata un'invenzione arbitraria
in contrasto con questa decisione già presa nel codebase.

## Stato reale trovato

Il dominio ha già un segnale esplicito, non basato sull'età, per questo
esatto scopo: `Article::verification_status === 'needs_update'`
("Aggiornamento necessario" — verificato in passato, ma serve una revisione).
È già gestito da una pagina admin dedicata e matura (`/admin/verifica`,
`VerificationController` + `verification.blade.php`), con conteggi per
stato, badge colorati e un select inline per cambiare stato.

Il gap reale: questo segnale non era mai stato esposto sul command center
(`/admin/operazioni-editoriali`), l'obiettivo dichiarato di questo intero
batch.

## Implementazione

Nuova sezione `contenuti_da_aggiornare` nello snapshot del dashboard
service: filtra `$articles` (già caricato, pubblicato+programmato — stesso
confine di dominio di ogni altra sezione) per
`verification_status === 'needs_update'`, nessuna nuova query. Inclusa in
`open_problems_total` con lo stesso trattamento di `contenuti_isolati` e
`contenuti_senza_concept`.

Vista: nuova sezione "Da aggiornare" (stesso schema di "Pubblicati senza
Concept") più una nuova KPI card, entrambe con link a `/admin/verifica` per
l'azione di cambio stato (l'unico posto dove quel campo è modificabile —
non duplicato qui).

## Verifica

- `php artisan test --filter='test_a_published_article_needing_an_update_is_listed_as_a_stale_content_candidate|test_a_draft_article_needing_an_update_is_never_listed_as_a_stale_content_candidate|test_editor_sees_an_article_needing_an_update_in_its_own_section'`
  — 3/3 passed (incluso il caso limite: una bozza con lo stesso stato di
  verifica non compare mai, coerente col confine di dominio esistente).
- `php artisan test --filter='EditorialOperationsDashboard|Verification'`
  — 66/66 passed (1 skip preesistente, non correlato).
- Le due formule di recompute di `open_problems_total` nei test esistenti
  sono state aggiornate per includere il nuovo termine — stesso pattern già
  applicato nelle Missioni 27 e 39.
