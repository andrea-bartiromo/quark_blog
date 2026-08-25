# Missione 51 — Statistiche complete sulla pagina Link interni

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

## Gap trovato

`InternalLinkAuditReport` porta 11 campi numerici a livello di corpus
(`analyzed`, `withoutOutgoingLinks`, `withOneOutgoingLink`,
`withTwoOrMoreOutgoingLinks`, `brokenLinks`, `selfLinks`,
`unpublishedTargets`, `scheduledSafeLinks`, `redirectedLinks`,
`articlesWithAmbiguousAnchors`, `isolatedArticles`), tutti già calcolati
da `InternalLinkAuditService::audit()` e tutti già stampati dal comando
CLI `content:internal-link-audit` (sezione "ARTICOLI" del suo output
testuale) — ma `resources/views/admin/internal-link-audit/index.blade.php`
ne mostrava solo 4 su 11 (`analyzed`, `isolatedArticles`, `brokenLinks`,
`articlesWithAmbiguousAnchors`). Gli altri 7 restavano calcolati a ogni
richiesta ma invisibili fuori dalla CLI.

Il fatto che il comando CLI stampi già tutti e undici i campi insieme,
nello stesso ordine, è la prova che il team li considera parte di
un'unica fotografia coerente dello stato dei link — non un sottoinsieme
arbitrario scelto ora, ma un allineamento a ciò che l'output "sorgente
di verità" già mostra.

## Soluzione

Aggiunte 7 nuove card statistiche alla griglia `<dl>` già esistente
in cima alla pagina, stesso stile delle 4 già presenti: "Senza link
interni", "Con 1 link", "Con 2+ link" (subito dopo "Analizzati"), e
"Self-link", "Target non pubblicati", "Target scheduled sicuri", "Link
reindirizzati" (dopo "Anchor ambigui"). Nessuna nuova query: tutti i
dati sono già sul `$report` esistente. Nessuna nuova icona di
navigazione (nessun rischio di collisione emoji nella sidebar, vedi la
lezione della Missione 42).

## File toccati

- `resources/views/admin/internal-link-audit/index.blade.php`
- `tests/Feature/Admin/InternalLinkAuditControllerTest.php`

## Test

- `test_editor_sees_all_seven_previously_hidden_articles_stat_cards_rendered_on_the_page`
  — usa `InternalLinkAuditService::audit()` come oracolo dei numeri
  attesi (stesso principio "aggregazione, mai una regola di dominio"
  già applicato altrove in questo file); le classificazioni stesse
  (self/redirected/unpublished/scheduled_safe/ecc.) restano
  esaustivamente provate da `InternalLinkAuditCommandTest`, mai
  riverificate qui.

Suite `InternalLinkAudit*` completa: 49 test, 132 assertion, verde.
Repeatability gate: 5/5 run consecutivi verdi.
