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

## Limiti dichiarati di questa v1

- Nessuna ingestione automatica/API — solo import manuale CSV.
- Nessuna cannibalizzazione rilevata (più pagine per la stessa query
  vengono valutate riga per riga, non confrontate tra loro).
- La curva CTR-atteso-per-posizione è un'assunzione di settore, non un
  dato Kairus.
- Nessuna persistenza di storico oltre i periodi effettivamente importati
  (nessun job schedulato).
