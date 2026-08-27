# Missione 66 — Second Read metric semantics audit

## Esito

**IMPLEMENTED — audit documentale, nessuna modifica alla definizione.**

Baseline: `main` `1a029671e324d0fae8c61acdf86b3ddc0ff09b71`.

## Formula effettiva

```text
Second Read Rate =
  numero di eventi second_read_start
  -----------------------------------
  numero di eventi impression
```

Il risultato è arrotondato a quattro decimali. Se il denominatore è zero, il
rate è `0.0`.

La formula è disponibile per articolo sorgente
(`ContinuationAnalyticsService::statsFor()` e `articleBreakdown()`) e
sitewide (`siteWideTotals()`). Tutti i metodi accettano limiti temporali
`since` e `until`.

## Che cosa misura davvero

La metrica misura il modulo standalone **“Continua da qui”** costruito da
`ArticleContinuationService`, non l'intera navigazione previous/next dei
Percorsi.

Nel controller:

```php
$showContinuation = $continuation && (! $pathNavigation || ! $pathNavigation['next']);
```

Quando il Percorso ha già un articolo successivo, il modulo standalone viene
soppresso per non duplicare il link “Successivo”. Di conseguenza:

- il link `next` del Percorso non produce una `impression` server-side;
- il link `previous` del Percorso non produce una `impression` server-side;
- gli eventi client `path_next_click`, `path_previous_click` e
  `second_reading` restano locali al browser;
- questi eventi non entrano nel numeratore o denominatore della KPI corrente.

Il nome “Second Read” è quindi più ampio dello scope reale. La label admin va
letta come conversione del modulo standalone, non come conversione completa
della navigazione di un Percorso.

## Risposte alle domande della missione

| Domanda | Evidence | Conclusione |
|---|---|---|
| L'impression è registrata solo con CTA valida? | `recordImpression()` viene chiamato soltanto quando `$showContinuation` è truthy; target restituito dal servizio e URL firmato sono costruiti insieme | Sì, per il modulo standalone. Non per le CTA previous/next del Percorso. |
| Il second-read-start è un arrivo reale? | Registrato nel controller della pagina target solo con firma Laravel valida e `cd_src` presente | Sì: è una richiesta reale alla pagina B, non un click intent. |
| Source/target sono corretti? | Source caricato con `Article::published()->find(cd_src)`; target è l'articolo corrente; self A→A escluso | Sì per link firmati generati dal modulo. |
| Deduplicazione sessione | Chiavi `continuation_impression_A_B` e `continuation_second_read_A_B` | Una coppia A→B conta al massimo una volta per evento nella stessa sessione. |
| Previous e next coerenti? | Le CTA path usano URL normali e detector client; il server analytics usa solo URL temporaneo firmato standalone | No: sono fuori dalla metrica corrente, non trattate come la CTA standalone. |
| Staff/bot esclusi come article views? | Entrambi riusano `ArticleViewTrackingService::shouldCountRequest()` | Staff/redazione esclusi nello stesso modo. Nessun filtro bot esiste né per views né per continuation: comportamento coerente, limite noto. |

## Semantica di source e target

### Impression

- source = articolo A che renderizza il modulo;
- target = articolo B scelto da `ArticleContinuationService`;
- evento persistito durante la render request di A;
- l'impression misura modulo disponibile/renderizzato, non visibilità nel
  viewport e non click.

### Second read start

- source = articolo pubblicato identificato dal parametro firmato `cd_src`;
- target = articolo B correntemente aperto;
- firma con scadenza di 30 minuti;
- firma assente, scaduta o manomessa: nessun evento;
- source uguale al target: nessun evento.

## Deduplicazione: benefici e limiti

### Protegge da sovraconteggio

- refresh ripetuti di A nella stessa sessione non moltiplicano A→B impression;
- refresh ripetuti di B tramite lo stesso link non moltiplicano
  second-read-start A→B;
- nessun identificativo sessione/visitatore viene persistito nel DB.

### Possibile sottoconteggio

- la stessa persona che compie legittimamente A→B due volte nella stessa
  sessione conta una sola volta;
- una sessione Laravel molto lunga mantiene la deduplicazione oltre la singola
  sequenza editoriale;
- la deduplicazione è per coppia A/B: A→B e A→C sono conteggi separati.

### Possibile sovraconteggio del rate aggregato

Gli eventi impression e second-read-start sono deduplicati separatamente. Un
link firmato valido può teoricamente essere condiviso e aperto da una sessione
diversa: B registra un second-read-start anche se quella sessione non ha
registrato l'impression di A. La firma dimostra l'origine del link, non
l'appartenenza alla stessa sessione che ha visto A. Non viene persistito un
session identifier, per scelta privacy-safe.

## Multi-target e multi-Percorso

- la deduplicazione è per `source_article_id + target_article_id + event_type`;
- più target dallo stesso source non collidono tra loro;
- il DB non persiste `content_cluster_id`;
- la metrica non può essere attribuita con certezza a un Percorso quando la
  stessa coppia A→B è semanticamente presente in più Percorsi;
- l'aggregazione attuale è article-centric, non path-centric.

## Staff e bot

`ContinuationAnalyticsService` riusa esattamente
`ArticleViewTrackingService::shouldCountRequest()`:

- admin/editor/author autenticati con accesso redazione: esclusi;
- guest e utenti senza accesso redazione: inclusi;
- bot/crawler: non filtrati.

Questo non è un disallineamento: article views e Second Read applicano la stessa
regola. È un limite comune, già documentato da `ArticleViewTrackingService`.

## Persistenza e privacy

`article_continuation_events` contiene soltanto:

- tipo evento;
- source article id;
- target article id;
- created_at.

Non contiene IP, user agent, cookie, token o session id. La deduplicazione vive
esclusivamente nella sessione Laravel e non permette correlazione cross-visita
dai dati persistiti.

## Cosa non cambia

- nessun nuovo evento;
- nessun endpoint;
- nessun listener del detector client;
- nessuna migration;
- nessuna modifica della formula;
- nessun filtro bot euristico;
- nessuna attribuzione per Percorso inventata.

## Test già presenti

`SecondReadAnalyticsTest` copre:

- impression solo con candidate;
- deduplicazione refresh;
- esclusione traffico interno;
- arrivo con firma valida;
- firma scaduta/manomessa/assente;
- self/mismatch;
- deduplicazione second read;
- fail-open;
- formula e zero denominator;
- query budget.

`SecondReadAnalyticsV2Test` copre:

- breakdown per source;
- range temporali;
- query bounded;
- totali sitewide non troncati;
- `source_articles_engaged`;
- admin `/second-read`.

## Decisioni rinviate

Qualunque allargamento della KPI a previous/next richiede una decisione
esplicita:

1. se il denominatore debba contare una o entrambe le CTA;
2. se l'unità sia articolo, coppia A→B, Percorso o sessione;
3. come attribuire coppie presenti in più Percorsi;
4. se e come collegare impression e arrival nella stessa sessione senza
   persistere identificatori invasivi;
5. se mantenere il nome “Second Read Rate” o renderlo più specifico.

Senza questa decisione, cambiare il tracking produrrebbe una serie storica con
semantica incompatibile.
