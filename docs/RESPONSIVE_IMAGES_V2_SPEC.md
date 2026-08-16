# Responsive Images V2 — specifica tecnica (missione futura)

Prodotta durante la missione "Frontend Performance & Quality — Safe Hardening"
(vedi PR corrispondente). Non implementata in quella missione perché
richiede una migration e una decisione di prodotto (formato delle varianti,
soglie), non un fix piccolo e reversibile.

## Problema

Nessuna tabella (`media`, `articles`) memorizza `width`/`height` delle
immagini. Conseguenze osservate durante l'audit:

- Le immagini in contenitori a dimensione fissa (hero homepage, hero
  articolo, card articolo/categoria) sono già protette da CLS via CSS
  (`min-height` o `aspect-ratio` esplicito sul contenitore) — **non è un
  problema per queste superfici**, verificato leggendo il CSS
  effettivamente applicato (`home-fix.css`, `public-premium.css`).
- Le immagini **dentro il corpo articolo** (`.article-premium__body img`,
  inserite via TinyMCE) non hanno alcun contenitore a dimensione fissa
  (`width:100%`, altezza libera): senza `width`/`height` o `aspect-ratio`
  noti in anticipo, il browser non può riservare lo spazio corretto prima
  del caricamento — CLS reale, non speculativo, per queste immagini
  specificamente.
- Non esistono varianti dimensionali: ogni immagine servita è il file
  originale caricato, indipendentemente dal viewport. Nessun `srcset`
  è quindi utilizzabile oggi in modo corretto (non ci sono URL alternativi
  a cui puntare).

## Perché non risolto ora

Le opzioni valutate:

1. **Nuove colonne `width`/`height`** su `media` (e backfill per le righe
   esistenti) — richiede una migration e un comando di backfill
   (`getimagesize()` su ogni file esistente): esattamente il tipo di
   intervento strutturale che questa missione doveva evitare senza una
   necessità dimostrata di espanderne lo scope.
2. **`getimagesize()` a runtime** ad ogni render di un'immagine nel corpo
   articolo — introduce I/O su filesystem per ogni richiesta, un nuovo
   rischio di performance non compensato da una causa concreta già
   individuata nel codice (violerebbe il principio "nessuna ottimizzazione
   speculativa" della missione).
3. **Riscrivere `article.body` in-place** per iniettare `width`/`height`
   negli `<img>` esistenti — muterebbe dati editoriali salvati per un
   effetto puramente di presentazione: rischio sproporzionato rispetto al
   beneficio, e non reversibile in modo pulito.

Nessuna delle tre è "piccola, retrocompatibile, verificabile" nel senso
richiesto dalla missione — da qui la decisione di documentare invece di
implementare.

## Proposta per la V2

### 1. Metadati dimensionali persistiti

Migration additiva su `media`:

```php
$table->unsignedInteger('width')->nullable()->after('mime_type');
$table->unsignedInteger('height')->nullable()->after('width');
```

Popolati:
- al momento dell'upload (`ImageService::upload()`/`autoConvertToWebpIfEligible()`
  già leggono l'immagine via GD — `imagesx()`/`imagesy()` sono già
  disponibili gratuitamente in quel punto, senza I/O aggiuntivo);
- per gli esistenti, un comando console dedicato (`media:backfill-dimensions`,
  stesso pattern read-only-poi-apply già usato da `media:classify-existing`
  e `media:convert-webp`), eseguito una tantum, con `--dry-run` di default.

### 2. Applicazione nel corpo articolo

`ArticleBodyImageService` (introdotto in questa missione per il lazy
loading) è già il punto di estensione naturale: una volta disponibili le
dimensioni per `disk_name`, lo stesso passaggio DOM può risolvere ogni
`src` locale al proprio `Media`, e impostare `width`/`height` (o, se la
UI richiede `width:100%` come oggi, un `aspect-ratio` inline calcolato da
`width/height`) prima del `loading="lazy"` già aggiunto.

Comportamento per immagini non risolvibili (URL esterni, file rimossi,
media caricati prima della migration senza backfill): nessun attributo
aggiunto, comportamento identico a oggi — mai un errore, mai un placeholder
visibile.

### 3. Varianti responsive (`srcset`)

Fuori scope anche per la V2 "minima": richiede decidere un set di breakpoint
(es. 480/800/1200/1600px), generarli alla conversione WebP (`ImageService::
convertToWebp()` già ridimensiona per un singolo `maxWidth` — estendibile a
un array di target), e un naming convenzionale per `disk_name` che non
collida con quello esistente. Proposta di prima iterazione: solo per la
cover articolo (l'unica immagine il cui `sizes` è prevedibile in ogni
superficie in cui compare), non per le immagini nel corpo (dimensione di
visualizzazione troppo variabile per un `sizes` affidabile senza leggere il
CSS a runtime).

## Rollback

Ogni pezzo è additivo e indipendente: la migration può restare con colonne
`nullable` mai lette se il resto non viene implementato; il backfill è
idempotente e ri-eseguibile; `ArticleBodyImageService` già degrada con
grazia (nessuna dimensione nota → nessun attributo aggiunto).

## Non-obiettivi

- Nessuna CDN o servizio immagini a pagamento.
- Nessun cambiamento al formato di storage esistente (`assets/img/...`
  resta la sorgente di verità).
- Nessuna modifica alla pipeline WebP già esistente, solo un'estensione.
