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
