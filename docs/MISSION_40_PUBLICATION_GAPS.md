# Missione 40 — Publication gaps (ritmo di pubblicazione)

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness.
**Esito**: implementazione mirata — un vero gap operativo colmato, nessuna
duplicazione con la Missione 21.

## Requisito

Rilevare e rendere visibili lacune nella pubblicazione (outcome, non
un'implementazione specifica imposta dalla spec).

## Stato reale trovato — attenzione a non duplicare la Missione 21

`published_beyond_gap` (Missione 21, un batch precedente) è già completo e
già sulla dashboard: rileva un articolo **pubblicato oltre un gap nella
sequenza di un Percorso** — un lettore che segue "continua da qui" incontra
un salto. Quello resta l'unico significato corretto di "gap" a livello di
Percorso: nessuna modifica necessaria lì.

Nessun segnale esisteva però per il **ritmo di pubblicazione dell'intero
sito** — da quanto tempo non esce un nuovo articolo, indipendentemente dai
Percorsi. Un sito che non pubblica da settimane ha un problema editoriale
reale che nessuna sezione esistente della dashboard segnalava.

## Implementazione

Nuovo `PublicationCadenceService` (`app/Services/EditorialOperations/`),
stessa identica forma di `SearchConsoleFreshnessService` (Missione 34): una
sola query aggregata (`Article::published()->max('published_at')`,
riusando lo scope `published()` già esistente sul modello — mai un nuovo
filtro di stato), due stati ("mai pubblicato" / giorni dall'ultima
pubblicazione), nessuna soglia di "quanto è troppo" inventata (non definita
nel repository, stessa cautela già applicata da Search Console).

Wiring: nuova chiave `ritmo_pubblicazione` nello snapshot del dashboard
service, nuova KPI card "Ritmo di pubblicazione" con lo stesso schema
visivo a due stati già usato per Search Console.

## Verifica

- `php artisan test --filter=PublicationCadenceServiceTest` — 3/3 (mai
  pubblicato, riflette il più recente, uno scheduled non conta mai).
- `php artisan test --filter='EditorialOperationsDashboardServiceTest|PublicationCadenceServiceTest|EditorialOperationsDashboardControllerTest'`
  — 66/66 passed, incluse 2 nuove verifiche HTTP che la card compaia
  davvero sulla pagina reale in entrambi gli stati.
- Nessuna nuova query oltre alla singola aggregazione già descritta;
  nessuna modifica a `published_beyond_gap` o alla Missione 21.
