# Growth S3 — Search Console Opportunity Intelligence

## Stato di partenza

Verificato prima di scrivere codice: nessuna integrazione Google Search
Console esiste in questo repository — nessuna credenziale, nessun client
API, nessun modello dati, nessun documento di design precedente. Ogni
menzione di "Search Console" nel codice preesistente è un commento umano
che descrive una verifica manuale esterna (es. redirect corretti dopo aver
notato un problema in GSC), mai una chiamata API.

**Verdetto: DESIGN READY — INGESTION DEPENDENCY MISSING** per l'accesso API
diretto (richiederebbe credenziali OAuth di produzione che questo ambiente
non ha e non deve procurarsi). Questa v1 implementa comunque
un'infrastruttura reale e funzionante basata su **import CSV manuale**,
percorso legittimo e già disponibile a chiunque abbia accesso alla Search
Console di produzione (esportazione dalla UI), senza richiedere alcuna
credenziale nel codice.

## Formato CSV atteso

Intestazioni richieste (case-insensitive, ordine libero):

```
query,page,clicks,impressions,ctr,position
```

- `query` — testo della query di ricerca.
- `page` — URL completo della pagina di destinazione.
- `clicks`, `impressions` — interi ≥ 0.
- `ctr` — percentuale testuale (`"4.52%"`, come esporta Search Console) o
  decimale 0-1.
- `position` — posizione media, decimale.

Search Console non esporta nativamente un report a due dimensioni
(query+pagina) dalla UI semplice: va ottenuto filtrando per pagina nella
scheda Query, oppure — se in futuro si otterranno credenziali API — dalla
API Search Console con `dimensions: ['query','page']`. Questa v1 non
assume quale via userà la redazione, definisce solo il contratto CSV di
arrivo.

## Query → Articolo

`SearchConsoleQueryArticleMatcher` estrae lo slug dal path
`/articolo/{slug}` e cerca un `Article` con quello slug esatto — nessun
fuzzy-matching, nessuna euristica su titolo/query. Un `page_url` che non
corrisponde resta senza articolo: è un segnale editoriale (nessuna landing
page dedicata), non un errore da forzare.

## Tipi di opportunità e formule

Tutte le formule sono deliberatamente leggibili a mano — nessun modello
statistico o ML — cosi' ogni punteggio si puo' spiegare in una frase.
Soglia minima di evidenza comune (`SearchOpportunityScoringService::MIN_IMPRESSIONS`):
**20 impression** nel periodo (sotto questa soglia il segnale è troppo
rumoroso). La curva "CTR atteso per posizione" è una stima approssimativa
comunemente citata nel settore SEO, **non dati misurati su Kairus** (che
non esistono ancora) — va sostituita con medie osservate reali di Kairus
appena ce ne sono a sufficienza (nota anche nel codice).

| Tipo | Condizione | Punteggio |
|---|---|---|
| `high_impression_low_ctr` | impression ≥ soglia, CTR < 50% del CTR atteso per la posizione | click stimati persi = impression × (CTR atteso − CTR reale) |
| `good_position_low_ctr` | posizione ≤ 10 (pagina 1), CTR < 60% del CTR atteso | come sopra — sottoinsieme di pagina 1, azione tipica: riscrivere titolo/meta |
| `near_page_one` | posizione tra 10 e 20 | impression / posizione |
| `no_strong_landing_page` | nessuna riga della query (aggregata su tutte le pagine nel periodo) corrisponde a un articolo, impression totali ≥ soglia | impression totali |
| `rising_query` | richiede due periodi importati; impression periodo precedente ≥ 10, crescita > 50% | crescita percentuale |
| `search_cannibalization` | due o più articoli pubblici distinti ricevono impression per la stessa query (normalizzata) nel periodo, impression totali ≥ soglia (Cantiere 5) | impression degli articoli non primari (quelle "a rischio" di frammentazione) |

## Import — idempotenza

Un secondo import con lo stesso `(period_start, period_end)` **sostituisce**
le righe esistenti di quel periodo (cancellazione + inserimento in
transazione), non le somma: un redattore che corregge o ri-esporta un file
può ripetere l'import senza doppio conteggio.

## Pagina admin

`/admin/search-opportunities` — elenco semplice (non una dashboard):
tabella filtrabile per tipo, mostra sempre l'ultimo periodo importato con
un confronto automatico contro il periodo immediatamente precedente
disponibile (necessario solo per `rising_query`). Nessun grafico.

## Copertura e baseline (Cantiere 1, programma "Kairus Organic Discovery")

Ogni import registra anche la propria **copertura effettiva** in
`search_console_import_coverage` (una riga per property/periodo/tipo di
report, upsert — mai uno storico di ogni singolo import): property
(opzionale nel form, default `search-console.default_property` o
`config('app.url')`), tipo di report (`query_only` o `query_page`, dedotto
dalla presenza della colonna pagina), righe importate, query assegnate/non
assegnate a un articolo, pagine osservate, origine (`manual_csv`, l'unica
possibile finché non esiste un'integrazione API). Visibile in
`/admin/search-opportunities` sotto "Copertura dati", distinta dalla
"Cronologia import" (che resta lo storico grezzo per singolo import di
`SearchConsoleFreshnessService`).

`/admin/search-console-baseline` espone inoltre un report read-only per
periodo selezionabile: totali clic/impression/CTR/posizione media (CTR e
posizione ricalcolati dalle somme del periodo, non una media delle medie),
top landing page organiche, query non-brand (escluse per sottostringa
configurabile in `config('search-console.brand_terms')`, default il nome
del sito) e i conteggi delle opportunità già scorate da
`SearchOpportunityScoringService` (CTR basso, posizione 11-20, nessuna
landing page forte) — mai ricalcolate. Dispositivo e Paese sono dichiarati
esplicitamente non disponibili: nessun export CSV supportato li contiene.

## Decisione editoriale tracciabile (Cantiere 4, programma "Kairus Organic Discovery")

Ogni opportunità può ricevere una **decisione editoriale** — mai un'azione
automatica — in `search_opportunity_decisions` (una riga per
`opportunity_key`, la stessa identità stabile `type|query|page_url` già
usata da `SearchOpportunityStatus`): aggiorna un articolo esistente, crea
un brief per un nuovo articolo, segnala una sovrapposizione con un
articolo esistente ("fusione"), oppure ignora con una motivazione
obbligatoria. Chi/quando/motivazione sono sempre registrati.

- **Aggiorna articolo esistente / sovrapposizione**: collega un
  `article_id` esistente — mai una modifica al contenuto o alla
  pubblicazione dell'articolo stesso.
- **Crea brief**: crea un `ProjectTask` di tipo `publication` nel progetto
  editoriale predefinito attivo (`Project::defaultEditorial()`), **senza
  alcun articolo collegato** — mai un `Article` creato automaticamente.
  Se nessun progetto editoriale predefinito è configurato, la decisione
  fallisce esplicitamente (fail-closed) invece di crearne uno al volo.
- **Ignora**: richiede sempre una motivazione.

**Baseline e misurazione a 28/90 giorni**: alla primissima decisione per
un'opportunità, le metriche correnti (clic/impression/CTR/posizione)
vengono catturate come baseline — mai ricalcolate da decisioni
successive sulla stessa opportunità. Il comando
`php artisan search-opportunities:measure-outcomes` (sola lettura, nessuna
chiamata esterna, eseguibile manualmente in qualunque momento) cerca poi
la stessa opportunità nei dati Search Console attualmente disponibili una
volta trascorsi 28 e 90 giorni dal baseline, registrando l'esito osservato.
Un'opportunità non più presente nei dati attuali resta semplicemente non
misurata: mai un valore indovinato.

**Storico append-only**: ogni cambiamento di decisione produce una riga in
`search_opportunity_decision_histories` (mai un update — stesso schema di
`ProjectActivityLog`): azione, valore precedente/nuovo, motivazione, chi,
quando. Nessuna riga viene mai modificata o cancellata dall'applicazione.

## Cannibalizzazione di ricerca (Cantiere 5, programma "Kairus Organic Discovery")

`SearchOpportunityScoringService::cannibalizationFindings()` individua
articoli **pubblici** distinti che ricevono impression Search Console
**reali** per la stessa query (normalizzata) nello stesso periodo —
segnale osservato nei dati, distinto e più forte del controllo già
esistente (e più leggero) su `ArticleSearchProfile::primary_query`
dichiarato uguale tra due articoli
(`ArticleSearchProfileCollisionService`/`EditorialOpportunityDecisionService`,
non duplicato qui). Richiede la dimensione pagina (come
`no_strong_landing_page`): senza `page_url` non si può sapere quale
articolo abbia ricevuto l'impression. Un articolo non più pubblico (bozza,
programmato) non entra mai nel conteggio, né come "primario" né come
concorrente — fail-closed, mai un suggerimento di consolidamento verso un
articolo non raggiungibile pubblicamente. Le query brand sono escluse
(stessa configurazione `search-console.brand_terms`).

Ogni gruppo trovato produce anche un'opportunità di tipo
`search_cannibalization` (stessa identità `type|query|page_url`, articolo
= il "probabile primario", quello con più impression) che compare
automaticamente nell'elenco generale `/admin/search-opportunities` e
partecipa alla stessa infrastruttura di decisione, baseline e misurazione
a 28/90 giorni del Cantiere 4 — nessuna seconda struttura di persistenza.
La pagina dedicata `/admin/cannibalizzazione-ricerca` mostra il dettaglio
per-articolo (impression/clic/posizione di ciascun concorrente, non solo
del primario) e un modulo che registra la decisione "Sovrapposizione con
articolo esistente" (`merge`) tramite la stessa route di scrittura già
esistente del Cantiere 4 — nessuna nuova azione automatica, la
consolidazione/differenziazione resta sempre una scelta editoriale umana.

## Limiti dichiarati di questa v1

- Nessuna ingestione automatica/API — solo import manuale CSV.
- La cannibalizzazione (Cantiere 5) confronta solo query normalizzate
  esattamente uguali tra articoli — mai un confronto semantico/fuzzy tra
  query diverse ma equivalenti (stesso limite dichiarato già esistente in
  `ArticleSearchProfileCollisionService`).
- La curva CTR-atteso-per-posizione è un'assunzione di settore, non un
  dato Kairus.
- Nessuna persistenza di storico oltre i periodi effettivamente importati
  (nessun job schedulato).
- Nessun dato per dispositivo o Paese (Cantiere 1): richiederebbe
  un'integrazione API che questo programma non implementa ancora.
- Il comando `search-opportunities:measure-outcomes` (Cantiere 4) non è
  schedulato automaticamente in questa v1: va eseguito manualmente (o
  aggiunto a `routes/console.php` con `Schedule::command(...)` da chi
  gestisce l'ambiente) finché non esiste una decisione esplicita di
  automatizzarlo.
