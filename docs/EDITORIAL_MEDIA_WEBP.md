# Pipeline WebP per i media editoriali — Kairus

Guida operativa alla conversione progressiva delle immagini editoriali di
Kairus verso WebP: cosa viene convertito, cosa no e perché, come eseguire
un audit prima di agire, e come funziona la conversione stessa. Nasce
dalla missione notturna di agosto 2026, costruita sopra l'audit storage
dell'agosto 2026 (`docs/STORAGE_AUDIT.md`).

## Indice

1. [Architettura attuale](#1-architettura-attuale)
2. [Audit — `media:webp-audit`](#2-audit--mediawebp-audit)
3. [Riferimenti statici protetti](#3-riferimenti-statici-protetti)
4. [Nuovi upload: conversione automatica in WebP](#4-nuovi-upload-conversione-automatica-in-webp)
5. [Migrazione legacy — `media:convert-webp`](#5-migrazione-legacy--mediaconvert-webp)
6. [Cleanup degli originali — `media:webp-cleanup`](#6-cleanup-degli-originali--mediawebp-cleanup)

---

## 1. Architettura attuale

### 1.1 Mappa dei flussi che toccano immagini

| Flusso | Controller | Registra un `Media`? | Resize/ricompressione | Formato di destinazione |
|---|---|---|---|---|
| Copertina articolo (Admin) | `Admin\ArticleController` | Sì (`MediaService::register`) | Sì, 1600px, jpg82/png7/webp82 | **JPG/PNG→WebP automatico** (vedi §4); fallback stesso formato |
| Copertina articolo (Redazione) | `Redazione\ArticleController` | Sì | Sì, 1600px, jpg82/png7/webp82 (store() **e** update(), stessi parametri di Admin) | **JPG/PNG→WebP automatico** (vedi §4); fallback stesso formato |
| Libreria media | `Admin\MediaController` | Sì | Sì, 1600px, jpg82/png7/webp82, GIF ammesse | **JPG/PNG→WebP automatico** (vedi §4); GIF sempre invariata; fallback stesso formato |
| Immagine categoria | `Admin\CategoryController` | Sì | Sì, 1200px, jpg84/png7/webp84 (parametri più deboli degli altri, vedi `docs/STORAGE_AUDIT.md` §7 P2) | Stesso del sorgente — **esclusa deliberatamente** dalla conversione automatica (vedi §4) |
| Foto profilo (Admin/Redazione) | `*\ProfileController` | Sì (Admin) / percorso Storage disk separato (Redazione, prefisso `photos/`) | Sì (Admin), 800px | Stesso del sorgente — non toccata da questa missione |
| Pagine speciali Turing | `Admin\TuringController` | **No** | **No** (upload salvato cosi' com'e', fino a 16 MB) | Invariato |
| Pubblicità | `Admin\AdController` | — | — | Nessun upload: `banner_image` e' un `disk_name` inserito manualmente, referenzia un Media già caricato altrove |

Fino alla missione WebP (agosto 2026) nessun flusso convertiva
automaticamente JPG/PNG in WebP: ogni immagine restava nello stesso
formato in cui era stata caricata. Da questa missione, tre flussi (Media
Library + copertine articolo Admin/Redazione) lo fanno di default — vedi
§4 per la policy esatta, cosa resta escluso e perché.

### 1.2 Classificazione dello storage immagini

| Categoria | Cosa include | Note |
|---|---|---|
| **A. Media editoriali gestiti dal DB** | Ogni file con un record `Media` corrispondente (`media.disk_name`) | Fonte di verità per "e' usato/dove": `MediaReferenceService`/`MediaUsageService`, mai reinventata |
| **B. Cover articoli** | `articles.cover_image` | Sottoinsieme di A quando il file è anche registrato come Media (quasi sempre, tranne dati legacy) |
| **C. Asset applicativi/versionati** | `public/assets/icons/*`, `favicon.*`, CSS/JS — fuori da `assets/img`, mai nel dominio di questa missione | Non toccati da alcun comando qui descritto |
| **D. Immagini Turing** | Tutto sotto `assets/img/turing/` | **Nessun record Media.** Riferimenti in parte hardcoded (`TuringPageController`, vedi §3), in parte in `special_pages.content` (JSON, già coperto da `MediaReferenceService`) |
| **E. Immagini categoria** | `assets/img/categories/*` | Sempre registrate come Media (vedi 1.1); `categories.image` memorizza solo il basename |
| **F. Hero** | `assets/img/hero/*` e affini | Nel dataset osservato: alcuni registrati come Media (cover articolo), altri no — verificare caso per caso, non assumere |
| **G. Placeholder** | `placeholder-1.jpg`, `placeholder-1.svg`, `hero-placeholder.svg` | Hardcoded in molte view come fallback `onerror`/`??`; già in `protected_disk_names` |
| **H. Legacy** | File presenti da prima dell'introduzione della Libreria media, formato/naming non uniforme | Si sovrappongono a I se non hanno un record Media |
| **I. File potenzialmente orfani** | File su disco senza record Media **e** senza riferimento hardcoded noto | **Non equivale a "eliminabile"** — vedi §"Cosa NON viene convertito" |
| **J. File esplicitamente protetti** | `config('media.protected_disk_names')` | Vedi §3: lista ampliata in questa missione dopo un audit esplicito dei riferimenti hardcoded |

### 1.3 Cosa NON viene convertito automaticamente, e perché

Ogni file scansionato da `media:webp-audit` finisce in una di queste
categorie di esclusione, mai in un generico "non trovato":

- **GIF** — la conversione perderebbe l'animazione (frame successivi al
  primo). Mai proposta.
- **Turing (`assets/img/turing/*`)** — nessun record Media da cui
  ricostruire "dove viene usato", e riferimenti che possono essere sia
  hardcoded (vedi §3) sia in `special_pages.content`. La missione chiede
  esplicitamente di non generalizzare qui finché non dimostrato sicuro:
  categoria esclusa in blocco, non file per file.
- **Riferimenti statici protetti** (`config('media.protected_disk_names')`)
  — hardcoded in controller/viste/seeder, invisibili a qualunque scansione
  DB.
- **Nessun record Media** — senza un riferimento strutturato conosciuto da
  riscrivere in sicurezza, non è verificabile se e dove il file è usato.
  Richiede sempre revisione manuale: **non è un "probabile orfano
  automaticamente eliminabile"**, è semplicemente fuori dallo scope
  automatizzabile di oggi.
- **Riferimenti non aggiornabili in sicurezza** — determinato **riusando**
  `MediaReferenceService::preflight()` (lo stesso servizio già usato da
  `media:classify-existing` e dal preflight di spostamento della Libreria
  media): blocca su testo libero (`articles.body`, `ads.html_code`, che
  potrebbero contenere il nome file incollato), foto profilo in formato
  Storage-disk ambiguo, immagini categoria la cui destinazione uscirebbe
  da `categories/`. Nessuna seconda definizione di "riferimento sicuro"
  inventata per questa missione.

## 2. Audit — `media:webp-audit`

```bash
php artisan media:webp-audit                    # report testuale
php artisan media:webp-audit --json              # JSON
php artisan media:webp-audit --path=categories   # solo una sottocartella
php artisan media:webp-audit --only=jpg,png      # solo alcuni formati sorgente
php artisan media:webp-audit --min-size=51200    # ignora file sotto i 50 KB
php artisan media:webp-audit --article=42        # solo la copertina dell'articolo 42 (o --article=slug-articolo)
php artisan media:webp-audit --no-measure        # struttura soltanto, nessuna misura reale del peso WebP (più veloce)
php artisan media:webp-audit --verbose-files      # elenca ogni file escluso, non solo i conteggi
```

**Esclusivamente in lettura.** La stima del peso WebP di ogni candidato è
una misura **reale**, non una stima approssimata: il file viene
effettivamente convertito, ma solo dentro una directory temporanea
usa-e-getta (mai `public/assets/img`), poi il file temporaneo viene
eliminato subito dopo la misura. Nessun dato di produzione viene
letto-e-modificato per ottenere questo numero.

### Output

- Immagini analizzate, dimensione totale, ripartizione per formato
  sorgente.
- Già in WebP (non ricontate come candidate).
- **Candidati**: conteggio, peso attuale, peso WebP misurato realmente,
  risparmio in byte e percentuale, top 10 per dimensione.
- **Escluse**, con conteggio e peso per ciascuna delle categorie del §1.3
  (motivo per singolo file con `--verbose-files`).
- **Record Media con file mancante** — segnalati, mai trattati come
  "quindi il record va rimosso": è un'incoerenza da indagare (allineato
  con `storage:audit`, stessa definizione di "file mancante").
- **Duplicati sicuri** — stesso contenuto byte-per-byte (hash SHA-256, non
  solo stessa dimensione o stesso nome), raggruppati per confronto
  economico (hash calcolato solo tra file che già condividono la stessa
  dimensione in byte).

## 3. Riferimenti statici protetti

`config('media.protected_disk_names')` elenca i `disk_name` hardcoded in
file versionati (controller, viste, seeder) che nessuna scansione DB può
scoprire. Ampliato in questa missione dopo una ricerca esplicita (mai per
deduzione) di ogni `asset('assets/img/...')` letterale nel codice: oltre
al ritratto di Turing già presente, `TuringPageController` definisce ~12
percorsi di default (hero/intro/background per ciascun capitolo, quasi
tutti già `.webp`) usati come fallback quando una pagina speciale non ha
un `background_image`/`image` personalizzato in `special_pages.content` —
quei default non sono mai raggiungibili da nessuna scansione DB perché
non sono mai stati nel DB.

**Chiunque introduca un nuovo riferimento hardcoded a un file della
Libreria media deve aggiungerlo qui manualmente** — è l'unica difesa
possibile finché questa lista resta manuale (nessuna scansione a runtime
del codice sorgente è prevista, né consigliata: aggiungerne una
richiederebbe di analizzare Blade/PHP a ogni richiesta, un costo non
giustificato per un evento raro come "introdurre un nuovo hardcoded
path").

## 4. Nuovi upload: conversione automatica in WebP

FASE 5 della missione: prima di migrare l'archivio esistente (compito
separato e deliberatamente successivo, vedi `media:webp-audit` sopra),
si ferma la crescita futura — i nuovi upload editoriali smettono di
aggiungere JPG/PNG allo storage quando possono diventare WebP da subito.

### 4.1 Meccanismo

`ImageService::autoConvertToWebpIfEligible($fullPath, $ext, $quality, $maxWidth)`
è il punto unico di decisione, richiamato da ciascun controller subito
dopo `ImageService::upload()`:

- **JPG/PNG** → convertiti in WebP (stesso basename, estensione `.webp`),
  l'originale caricato viene rimosso dopo la sostituzione;
- **WebP/GIF** → mai toccati, restituiti invariati;
- **qualunque fallimento di conversione** (encoder assente, contenuto
  corrotto, scrittura fallita) → degrado sicuro: il file caricato resta
  intatto e il controller ricade sul comportamento preesistente
  (`resizeAndCompress()`, ottimizzazione nello stesso formato). Un
  problema di conversione WebP non fa mai fallire un upload che sarebbe
  altrimenti riuscito.

La qualità e la larghezza massima usate sono le stesse dell'audit e
della futura migrazione legacy (`config('media.webp_quality')` /
`config('media.webp_max_width')`, default 82 / 1600px) — nessuna
seconda policy di conversione inventata per i nuovi upload.

### 4.2 Flussi inclusi (default: attivo)

- **Libreria media** (`Admin\MediaController::store()`) — GIF ammesse e
  sempre escluse dalla conversione, coerentemente con l'audit.
- **Copertina articolo, Admin** (`Admin\ArticleController`).
- **Copertina articolo, Redazione** (`Redazione\ArticleController::store()`
  **e** `update()` — entrambi i percorsi, non solo la creazione).

Questi tre flussi condividono lo stesso pattern (`safeExtension()` dal
contenuto reale, non dal nome file → upload → conversione-o-fallback →
`PublicMediaSyncService` → registrazione `Media`), gia' verificato in
questa missione: e' la ragione per cui sono stati inclusi insieme nel
primo rollout.

### 4.3 Flussi esclusi deliberatamente (nessuna generalizzazione automatica)

- **Immagine categoria** (`Admin\CategoryController`) — la missione
  nomina esplicitamente le "categorie" tra i flussi da non estendere
  automaticamente senza sicurezza dimostrata. Nessun problema tecnico
  noto blocca l'estensione futura (le immagini categoria sono comunque
  registrate come Media, e qui si tratta di un file appena creato, non
  di riscrivere un riferimento esistente), ma resta una decisione
  distinta e deliberata, non presa in questa missione.
- **Foto profilo** (`*\ProfileController`) — fuori dallo scope di questa
  missione (non e' mai stato nell'elenco dei flussi editoriali da
  analizzare); il percorso Redazione usa inoltre un disco Storage
  separato con caratteristiche diverse dagli altri flussi.
- **Pagine speciali Turing** (`Admin\TuringController`) — nessun record
  Media, upload salvato cosi' com'e': stessa esclusione categorica
  spiegata in §1.3 per l'audit, valida anche qui.
- **Pubblicità** — nessun upload proprio (`banner_image` referenzia un
  Media già caricato altrove).

### 4.4 Interruttore di sicurezza

`config('media.auto_webp_on_upload')` (default `true`, env
`MEDIA_AUTO_WEBP_ON_UPLOAD`) governa tutti e tre i flussi inclusi
insieme. Impostare `MEDIA_AUTO_WEBP_ON_UPLOAD=false` in produzione
disattiva istantaneamente la conversione automatica (i nuovi upload
tornano al comportamento preesistente: stesso formato del sorgente,
ottimizzato con `resizeAndCompress()`) **senza deploy**, utile come
rollback immediato se emergesse un problema non previsto in produzione.

## 5. Migrazione legacy — `media:convert-webp`

FASE 6 della missione: dopo aver fermato la crescita futura (§4), migra
progressivamente i file JPG/PNG **gia' esistenti** e registrati come
Media. Riusa la stessa definizione di "candidato sicuro" di
`media:webp-audit` (`MediaWebpAuditService::evaluateCandidate()`, una sola
implementazione condivisa da entrambi i comandi) e la stessa scrittura
atomica di `ImageService::convertToWebp()` gia' usata dai nuovi upload.

### 5.1 Cosa fa, e cosa non fa mai

Per ogni Media JPG/PNG idoneo: converte in un file WebP **nuovo** accanto
all'originale, aggiorna `Media.disk_name`/`mime_type`/`size`, riscrive
ogni riferimento strutturato aggiornabile (copertina articolo, banner
pubblicita, foto profilo, immagine categoria, contenuto pagine speciali —
tramite `MediaReferenceService::preflight()`, mai una logica di
riferimento reinventata), e sincronizza la radice pubblica secondaria se
configurata (`PublicMediaSyncService`).

**L'originale non viene mai eliminato da questo comando.** La rimozione
degli originali e' una fase futura, deliberatamente distinta — vedi
`media:webp-cleanup` piu' sotto.

### 5.2 Modalita dry-run (default) e `--execute`

```bash
php artisan media:convert-webp                          # dry-run: rivaluta e misura, non scrive nulla
php artisan media:convert-webp --report=storage/app/reports/webp-plan.json
php artisan media:convert-webp --execute                 # applica, con conferma interattiva
php artisan media:convert-webp --execute --force          # applica senza conferma (mai salta i controlli di sicurezza)
php artisan media:convert-webp --execute --media-id=42    # limita a uno o piu Media specifici
php artisan media:convert-webp --execute --limit=50       # limita il numero di Media processati in questa esecuzione
```

Senza `--execute` il comando e' in sola lettura: rivaluta ogni Media
JPG/PNG registrato, misura realmente (conversione usa-e-getta in una
directory temporanea, mai in `public/assets/img`) il peso WebP
risultante, e stampa/esporta un manifest. Con `--execute`, ogni Media
viene **rivalutato di nuovo da zero** al momento dell'applicazione (mai
fidandosi di un piano calcolato in precedenza: un altro processo
potrebbe aver gia' convertito lo stesso file, o un nuovo riferimento
bloccante potrebbe essere comparso nel frattempo) e convertito uno alla
volta: un errore su un Media non blocca gli altri, non esiste una
transazione unica su tutta la libreria.

**Idempotente**: una seconda esecuzione e' sempre sicura. Un Media gia'
convertito ha ormai `disk_name` `.webp` e non viene piu' selezionato
dalla query dei candidati (esclusione `already_webp`, la stessa categoria
usata da `media:webp-audit`).

### 5.3 Atomicita' e gestione dei fallimenti

Il WebP viene scritto e verificato **prima** di qualunque scrittura sul
database (stessa scrittura atomica temp-file+rename di
`ImageService::convertToWebp()`, mai un rename diretto sulla destinazione
finale): un fallimento di conversione non lascia mai uno stato DB
incoerente da compensare.

Se invece un passo fallisce **dopo** che il WebP esiste gia' (sync
pubblico, update `Media`, aggiornamento riferimenti, verifica finale
post-conversione), l'intera transazione DB va in rollback e il file WebP
generato — con l'eventuale copia nella radice pubblica secondaria — viene
rimosso con best-effort prima di riportare lo stato `failed`: l'originale
resta sempre l'unico stato coerente sul filesystem, mai un WebP orfano
che il database non referenzia. Nessuna transazione filesystem+DB vera
esiste (non e' possibile a livello tecnico): l'ordine delle operazioni
sopra e la compensazione esplicita sono la strategia deliberata per
tenerli allineati.

### 5.4 Stati per Media e struttura del manifest

| Stato | Significato |
|---|---|
| `planned` / `converted` | Candidato sicuro (dry-run) / convertito con successo (`--execute`) |
| `skipped_<motivo>` | Escluso: `gif`, `protected`, `turing_unmanaged`, `webp_destination_conflict`, `blocked_references`, `already_webp` — stessa classificazione di `media:webp-audit` |
| `missing_source` | Il file non esiste piu' sul filesystem, o e' un collegamento simbolico, o uscirebbe dalla radice media (path traversal): richiede indagine manuale, mai trattato come "quindi convertibile" |
| `failed` | Errore tecnico (encoder assente, scrittura fallita, verifica post-conversione fallita): nessuna modifica applicata, rollback automatico |

Il manifest JSON (`--report=...`) contiene `generated_at`, `mode`
(`dry_run`/`executed`), `summary` (conteggio per stato) e `results` (uno
per Media: `media_id`, `original_path`, `new_path`, `original_bytes`,
`webp_bytes`, `saving_bytes`, `saving_percent`, `dimensions`,
`updated_reference_count`, `reason`).

### 5.5 Procedura operativa consigliata per la produzione

1. Backup del database.
2. Backup di `storage/app/public` e `public/assets/img`.
3. Esecuzione in dry-run (senza `--execute`), revisione umana del
   manifest (`--report=...`).
4. Esecuzione in staging con `--execute`, verifica manuale di frontend e
   pannello Admin (copertine articolo, categorie, media library).
5. Esecuzione in produzione con `--execute` su un lotto limitato
   (`--limit=...` o `--media-id=...`) prima dell'intera libreria.
6. Nuovo dry-run di verifica per confermare lo stato finale (tutti i
   Media migrati risultano `skipped_already_webp`).
7. Periodo di osservazione prima di valutare la rimozione degli
   originali — vedi §6, mai automatica.

## 6. Cleanup degli originali — `media:webp-cleanup`

FASE 12 della missione: dopo che un originale e' stato migrato (§5), il
file JPG/PNG resta comunque sul filesystem — questo comando **elenca
soltanto** quali di questi residui potrebbero un giorno essere rimossi,
senza rimuoverli mai. **Nessun flag di esecuzione esiste in questa
versione**: `media:webp-cleanup` e' sola lettura al 100%, per design, non
per omissione temporanea di un flag facile da aggiungere in seguito.

```bash
php artisan media:webp-cleanup                       # report testuale
php artisan media:webp-cleanup --json                 # JSON
php artisan media:webp-cleanup --path=categories       # solo una sottocartella
php artisan media:webp-cleanup --min-age-days=30        # soglia di osservazione diversa dal default
```

### 6.1 Criteri di candidatura

Un file compare tra i "candidati" solo se **tutte** queste condizioni
sono vere (mai una sola, mai per deduzione):

1. e' un JPG/PNG sotto `public/assets/img`;
2. esiste un `Media` il cui `disk_name` e' gia' la sua controparte
   `.webp` — quindi e' stato davvero migrato da `media:convert-webp`, non
   un file indipendente mai toccato dalla pipeline;
3. quel file `.webp` esiste realmente su disco **ed e' un'immagine
   valida** (`getimagesize()` riuscito) — il controllo di sicurezza piu'
   importante di questo comando: se il sostituto e' mancante o corrotto,
   il file non compare mai tra i candidati, indipendentemente da ogni
   altro criterio;
4. nessun `Media` punta ancora al file originale stesso (caso limite:
   duplicati di contenuto con un `.webp` indipendente);
5. non e' nella lista protetta (`config('media.protected_disk_names')`);
6. non e' sotto `turing/` (stessa esclusione categorica di
   `media:webp-audit`/`media:convert-webp`);
7. nessuna menzione in testo libero (`article.body`, `ad.html_code` —
   riusa `MediaReferenceService::findFreeTextMentions()`, la stessa
   ricerca gia' usata dal preflight di spostamento: mai una seconda
   definizione di "potrebbe essere nascosto in un campo di testo");
8. nessuna menzione letterale del nome file nel codice sorgente
   versionato (`resources/views`, `resources/css`, `resources/js`,
   `public/css`, `public/js`);
9. e' trascorso il periodo di osservazione minimo dalla migrazione
   (`--min-age-days`, default `config('media.webp_cleanup_min_age_days')`
   = 14 giorni, calcolato su `Media.updated_at` del record WebP — l'unico
   timestamp di "quando e' stata applicata la migrazione" gia'
   disponibile, nessuna nuova colonna introdotta per questo).

Ogni esclusione viene riportata con un motivo esplicito (stessa filosofia
di `media:webp-audit`): `not_migrated`, `still_referenced_by_media`,
`protected`, `turing_unmanaged`, `webp_missing_or_invalid`,
`free_text_reference`, `static_source_reference`,
`observation_period_not_elapsed`.

### 6.2 Cosa questo comando NON puo' verificare

**La conferma di un backup locale reale** — nessuna integrazione di
backup esiste nel progetto, quindi non e' un dato che il comando possa
leggere o dedurre. Ogni esecuzione con almeno un candidato lo ricorda
esplicitamente in output come promemoria per l'operatore umano: non viene
mai assunto vero, ne' bypassato.

### 6.3 Procedura futura per la rimozione effettiva (non implementata qui)

Deliberatamente fuori scope per questa missione (`--force` esplicitamente
non richiesto dalla missione stessa per questa fase). Quando si decidera'
di implementare la rimozione:

1. Eseguire `media:webp-cleanup` e rivedere umanamente ogni candidato
   riportato (non fidarsi ciecamente dell'elenco: e' un aiuto alla
   decisione, non una decisione).
2. Confermare un backup locale reale e verificato di
   `public/assets/img` (e della radice pubblica secondaria, se
   configurata) precedente alla rimozione.
3. Implementare deliberatamente un comando o flag di cancellazione
   separato — mai come estensione rapida di questo comando di sola
   lettura — con lo stesso livello di test, atomicita' e revisione delle
   altre fasi di questa missione (backup automatico pre-cancellazione,
   rimozione one-file-at-a-time con log per ogni file, mai una
   cancellazione batch senza conferma per singolo file).
4. Eseguire prima su un lotto limitato, verificare manualmente il
   frontend, poi procedere sul resto.
