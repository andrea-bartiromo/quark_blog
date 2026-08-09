# Audit storage e budget di capacità — Kairus

Guida operativa allo storage disco di Kairus: come misurarlo, come interpretarlo,
cosa può essere sicuro da modificare e cosa richiede sempre una decisione
editoriale/di prodotto prima di agire. Nasce dalla missione di audit storage
dell'agosto 2026 (piano hosting corrente: 5 GB).

## Indice

1. [Come eseguire l'audit](#1-come-eseguire-laudit)
2. [Come interpretare l'output](#2-come-interpretare-loutput)
3. [Cosa NON va mai eliminato manualmente](#3-cosa-non-va-mai-eliminato-manualmente)
4. [Verificare MEDIA_PUBLIC_ROOT sul server](#4-verificare-media_public_root-sul-server)
5. [Policy immagini: WebP, PNG, JPEG, GIF](#5-policy-immagini-webp-png-jpeg-gif)
6. [Modello di capacità (3/6/12/18/24 mesi)](#6-modello-di-capacità-361218-24-mesi)
7. [Interventi sicuri (P0–P3)](#7-interventi-sicuri-p0p3)

---

## 1. Come eseguire l'audit

```bash
php artisan storage:audit          # report testuale leggibile
php artisan storage:audit --json   # stesso contenuto, in JSON
```

**Il comando è esclusivamente in lettura**: non scrive, sposta, converte o
elimina mai nulla, né sul filesystem né sul database. È sicuro da eseguire in
qualunque momento, anche direttamente in produzione, senza alcuna finestra di
manutenzione.

Misura: dimensione del database SQLite, dimensione e numero dei backup
(`storage/backups`), dimensione e conteggio dei media (`public/assets/img`),
dimensione dei log (`storage/logs`), altro storage applicativo
(`storage/framework` + `storage/app`), e li confronta con un budget hosting
configurabile (vedi sotto).

Il budget di riferimento vive in `config/storage_audit.php` — **non è
hardcoded**: impostabile via `STORAGE_BUDGET_BYTES` (byte esatti) o
`STORAGE_BUDGET_GB` (approssimato, 1 GB = 1024³ byte) nel `.env`. Se nessuno
dei due è impostato, il default riflette il piano attuale (5 GB) — ma questo
va sempre riverificato lato pannello di hosting: il comando non ha alcun modo
di sapere quale piano è realmente attivo, lo espone solo come riferimento.

## 2. Come interpretare l'output

| Sezione | Significato |
|---|---|
| **Database** | Dimensione del file `database/database.sqlite`. |
| **Backup** | Dimensione totale e numero dei file `database-*.sqlite` in `storage/backups`, più il "moltiplicatore" (numero di copie + 1, cioè quante volte il peso del database si ritrova occupato su disco contando anche i backup). |
| **Media** | Dimensione e conteggio di tutto ciò che si trova sotto `public/assets/img`. |
| **Media registrati nel DB** vs **Media su filesystem** | Se questi due numeri divergono, è il primo segnale di un'incoerenza — vedi *orfani* e *record con file mancante* sotto. |
| **Possibili orfani** | File presenti su `public/assets/img` ma **senza** alcun record `Media` corrispondente (stesso `disk_name`). Non è detto che siano "spazzatura": possono essere immagini caricate da un flusso che non registra `Media` (es. le pagine speciali di Turing, che salvano il file ma non creano un record — comportamento noto, non un bug — vedi §5). Vanno **verificati caso per caso**, mai eliminati in blocco. |
| **Possibili record con file mancante** | Direzione opposta: un record `Media` il cui file non esiste più su disco. Segnala un problema reale (upload fallito a metà, file rimosso manualmente, migrazione incompleta) — da indagare, il comando si limita a segnalarlo. |
| **Log** | Dimensione totale di `storage/logs` (log applicativo Laravel *e* i log di output dello scheduler, es. `articles-publish.log` — vedi §7, P1). |
| **Altro storage** | `storage/framework` (cache/sessioni/viste compilate) + `storage/app` (es. export CSV). |
| **Formati immagine** | Conteggio e peso per JPG (include `.jpeg`), PNG, WebP, GIF, e "other" per qualunque altro file eventualmente presente nella cartella media. |
| **Top 10 immagini/directory più pesanti** | Utile per individuare rapidamente dove concentrare un'eventuale ottimizzazione manuale. |
| **MEDIA_PUBLIC_ROOT** | Vedi §4. |

## 3. Cosa NON va mai eliminato manualmente

- **Nessun file segnalato come "possibile orfano"** senza prima aver verificato
  che non sia referenziato in un modo che l'audit non copre (vedi limiti in
  §4) — in particolare i file caricati dalle pagine speciali di Turing, che
  per design non hanno mai un record `Media` (non sono un falso positivo da
  "correggere").
- **Backup**: la retention corrente è di **7 copie** (`backup:database
  --keep=7`, invocato quotidianamente dallo scheduler). Non va ridotta senza
  una decisione esplicita di Andrea — è l'unica rete di sicurezza per il
  database in caso di problemi.
- **Dati analitici** (`article_views`, `newsletter_opens`, `newsletter_clicks`,
  `activity_log`): hanno valore editoriale/storico. Qualunque proposta di
  retention o aggregazione richiede una decisione di prodotto (privacy,
  valore storico dei dati) — vedi §7, non è mai un'azione da eseguire in
  autonomia.
- **File nella radice pubblica secondaria** (`MEDIA_PUBLIC_ROOT`, quando
  configurata): è un mirror best-effort gestito da `PublicMediaSyncService`,
  non una directory da toccare a mano.

## 4. Verificare MEDIA_PUBLIC_ROOT sul server

Il repository **non può sapere** se `MEDIA_PUBLIC_ROOT` è impostata in
produzione (è un valore `.env`, mai committato). Per verificarlo in modo
sicuro, senza modificare nulla:

```bash
php artisan tinker --execute="echo config('media.public_root') ?? '(non configurata)';"
```

oppure, più completo, direttamente dall'audit:

```bash
php artisan storage:audit
# → sezione "Radice pubblica secondaria (MEDIA_PUBLIC_ROOT)"
```

L'audit riporta tre stati possibili:

1. **Non configurata** — nessuna copia secondaria attesa, comportamento
   attuale del sito non cambia.
2. **Configurata ma fisicamente coincidente** con `public/assets/img` (stessa
   directory reale, es. tramite un symlink a livello di sistema operativo) —
   la sincronizzazione si autodisattiva (scrivere sarebbe scrivere un file su
   se stesso), nessuna duplicazione fisica.
3. **Configurata e distinta** — riporta quanti file risultano presenti in
   entrambe le directory e quanti solo nella radice applicativa. **Limite
   noto e intenzionale**: non viene rilevata la direzione opposta (file
   presenti *solo* nella radice pubblica secondaria), perché quella directory
   può trovarsi fuori dal progetto e avere dimensione/contenuto arbitrari
   (es. l'intera document root cPanel) — scansionarla per intero non sarebbe
   né sicuro né efficiente da fare automaticamente.

## 5. Policy immagini: WebP, PNG, JPEG, GIF

**Conferma definitiva (verificata leggendo il codice di
`ImageService::resizeAndCompress()`, non per deduzione): oggi Kairus non
converte mai automaticamente JPG/PNG in WebP.** Ogni immagine viene
ridimensionata e ricompressa sempre nello stesso formato in cui è stata
caricata — mai una conversione di formato.

Policy consigliata per i **nuovi** upload (nessun cambiamento di codice
implicato, solo una linea guida editoriale):

- **WebP preferito** per fotografie e immagini generate — a parità di
  qualità percepita è quasi sempre più leggero di JPEG, e supporta anche la
  trasparenza (a differenza di JPEG). *Non è garantito che sia sempre più
  leggero*: dipende dal contenuto e dall'encoder, va verificato caso per
  caso per immagini particolarmente semplici o già molto compresse.
- **PNG** solo quando serve davvero la trasparenza o per grafica/illustrazioni
  con poche tinte piatte (loghi, icone, screenshot con testo) — mai per
  fotografie, dove il peso lievita rapidamente (nel dataset attuale, i PNG
  fotografici sono la voce più pesante della libreria media).
- **JPEG** accettabile per compatibilità o quando l'originale è già in questo
  formato e non c'è bisogno di trasparenza.
- **GIF** solo quando serve davvero l'animazione — per un'immagine statica è
  quasi sempre la scelta più pesante disponibile.

**Conversione di immagini già esistenti (legacy) — non ancora implementata,
solo progettata**: un ipotetico comando `media:convert-to-webp` dovrebbe
essere transazionale e mai lasciare un articolo senza copertina:

1. Convertire in un file temporaneo, mai sovrascrivere l'originale in place.
2. Verificare che il nuovo file sia un'immagine valida e leggibile.
3. Aggiornare **tutti** i riferimenti nella stessa transazione DB:
   `articles.cover_image`, `categories.image` (attenzione: memorizza solo il
   basename, non il path completo), `users.photo`, `ads.banner_image`,
   `media.disk_name`. I riferimenti in testo libero (`articles.body`,
   `ads.html_code`) e nei JSON delle pagine speciali (`special_pages.content`,
   solo i percorsi nell'allowlist di `ScansJsonContentLeaves`) vanno
   riscritti come sostituzione di stringa, con lo stesso rischio di falsi
   positivi già noto a `MediaReferenceService` per quei campi.
4. Solo dopo che *tutti* i riferimenti sono stati aggiornati con successo,
   sincronizzare la nuova copia su `MEDIA_PUBLIC_ROOT` (se configurata) ed
   eliminare il vecchio file — mai prima, per non lasciare mai una finestra
   in cui un riferimento punta a un file che non esiste più.
5. In caso di fallimento in un punto qualsiasi: rollback della transazione
   DB, il file originale resta intatto e valido — nessun articolo si trova
   mai senza immagine.

Questo resta un **prerequisito progettuale**, non un'implementazione: una
conversione di massa è esplicitamente fuori scope per l'audit ed è
un'azione che richiede una decisione di Andrea (comporta riscrivere dati di
produzione).

## 6. Modello di capacità (3/6/12/18/24 mesi)

Ogni numero qui sotto è etichettato per provenienza — nessuna falsa
precisione:

- **FORNITO DA ANDREA**: utilizzo hosting attuale ~1,15 GB / 5 GB (~23%),
  rilevato dal pannello di hosting.
- **CALCOLATO**: dal codice/configurazione del repository (es. moltiplicatore
  di backup, comportamento di rotazione dei log).
- **STIMATO**: proiezioni di crescita — range plausibili, non previsioni
  esatte, perché **non abbiamo accesso a traffico reale, dimensione reale
  del database di produzione, o cadenza editoriale reale**. `storage:audit`,
  eseguito periodicamente in produzione, è lo strumento per sostituire
  queste stime con dati misurati.

**Non è possibile** derivare un vero "tra N mesi finisce lo spazio" dal solo
repository: mancano due dati chiave misurabili solo sul server reale — la
dimensione reale del database SQLite di produzione (che guida sia lo spazio
diretto sia il moltiplicatore di backup) e il traffico reale (che guida la
crescita di `article_views`). Quanto segue è quindi uno **scheletro di
modello**, da popolare con `storage:audit` una volta disponibile in
produzione, non una previsione definitiva.

### Componenti di crescita identificati

| Componente | Guidato da | Limite oggi |
|---|---|---|
| `article_views` (per-pageview) | Traffico | Nessuna purge automatica trovata |
| `newsletter_opens` / `newsletter_clicks` | Aperture/click newsletter | Nessuna purge automatica trovata |
| `activity_log` | Azioni editoriali/admin | Nessuna purge automatica trovata |
| Media (`public/assets/img`) | Nuovi articoli/immagini | Nessun limite, cresce con l'editoria |
| Backup DB | Dimensione DB × (retention + 1) | Retention fissa a 7, per policy attuale |
| Log scheduler (`storage/logs/*.log`) | Frequenza dei comandi schedulati | Nessuna rotazione, cresce sempre (vedi §7, P1) |

### Scenari indicativi (STIMATO — range, non previsioni)

| Scenario | Articoli/settimana | Immagini/articolo | Peso medio immagine (WebP preferito) | Pageview/mese |
|---|---|---|---|---|
| Conservativo | 1 | 1 | 200–350 KB | 500–2.000 |
| Normale | 2–3 | 1–2 | 250–450 KB | 5.000–15.000 |
| Forte crescita | 5+ | 2–3 | 300–500 KB | 30.000–100.000+ |

Con questi range, la **crescita mensile solo di media** (nuovi upload, non
il contenuto già esistente) resta dell'ordine di poche decine di MB/mese
anche nello scenario di forte crescita — non è il fattore che rischia di
saturare per primo un piano da 5 GB in tempi brevi.

**Il componente più a rischio di crescere in modo silenzioso e illimitato
è quasi certamente `article_views`** (una riga per pageview, nessuna
retention trovata), seguito a distanza dal moltiplicatore di backup se il
database di produzione dovesse crescere in modo significativo (vedi la
tabella "Backup × dimensione database" più sotto) — non i media, che
restano il componente più facilmente controllabile (preferenza WebP,
compressione già attiva su quasi tutti i flussi).

### Backup × dimensione database (CALCOLATO — vedi anche `storage:audit`)

Formula: `spazio totale = dimensione_DB × (1 + numero_backup)`. Con la
retention attuale di 7 copie, **non modificata**:

| Dimensione DB | ×8 (7 backup, attuale) | % di 5 GB |
|---|---|---|
| 25 MB | 200 MB | 3,9% |
| 100 MB | 800 MB | 15,6% |
| 500 MB | 4.000 MB (3,91 GB) | 78,1% |
| 1 GB | 8.000 MB (7,81 GB) | supera da solo il budget di 5 GB |

Questo è il motivo per cui **la dimensione reale del database di produzione
è il singolo dato più importante da misurare** (`storage:audit`
direttamente sul server) per rendere concreto questo modello.

## 7. Interventi sicuri (P0–P3)

Classificazione per beneficio/rischio/complessità. **Nessuno di questi è
stato applicato automaticamente** — sono proposte, non azioni.

### P0 — nessuno identificato
Nessun intervento è risultato abbastanza urgente da giustificare la
priorità massima allo stato attuale (utilizzo ~23% del budget).

### P1 — importante, a basso rischio

- **Rotazione dei log di output dello scheduler**
  (`storage/logs/articles-publish.log`, `projects-sync.log`,
  `projects-github-sync.log`, ecc.): crescono senza mai essere ruotati,
  indipendentemente da `LOG_CHANNEL`. `articles-publish.log` da solo, che
  scrive una riga **ogni minuto** anche quando non c'è nulla da pubblicare,
  cresce di stima ~22–31 MB/anno. Beneficio: elimina una crescita silenziosa
  e illimitata. Rischio: nessuno (i log operativi non hanno valore
  editoriale). **Richiede comunque una decisione su quanti giorni di log
  conservare** prima di poter essere implementato — proposta, non ancora
  un'azione.
- **`failed_jobs` mai ripulita**: nessun comando di prune schedulato.
  Volume oggi trascurabile (un solo job in coda nell'intera app), ma
  l'automazione mancante va colmata prima che il volume cresca.

### P2 — utile

- **Uniformare i parametri di `Admin\CategoryController`**: unico flusso di
  upload immagini a non passare `preserveTransparency`/`alwaysReencode`/
  `logErrors`, con conseguente compressione più debole e nessun log in caso
  di errore GD — allineabile agli altri 3 flussi senza cambi di
  comportamento visibile.
- **`TuringController`: estensione da MIME, non dal client**: unico flusso a
  usare `getClientOriginalExtension()` invece di
  `ImageService::safeExtension()` (MIME-sniffed) — allineabile per
  coerenza con gli altri flussi.
- **Verifica del worker di coda in produzione**: `SendNewsletterJob` è
  `ShouldQueue`, ma l'unico `queue:listen` nel repository è nello script di
  sviluppo locale (`composer dev`) — da verificare se in produzione
  `QUEUE_CONNECTION` sia effettivamente `sync` (nessun impatto storage, ma
  la newsletter settimanale potrebbe non partire mai se restasse in coda
  senza un worker).

### P3 — futuro / richiede decisione di Andrea

- **Pruning di `cache` scadute e non lette**: nessuna purge automatica per
  voci scadute mai rilette (`cache:prune-stale-tags` copre solo i tag
  orfani). Volume oggi limitato dal numero di chiavi cache uniche mai usate.
- **Retention di `article_views`/`newsletter_opens`/`newsletter_clicks`**: il
  componente di crescita più a rischio nel lungo periodo (§6), ma qualunque
  proposta tocca dati con valore analitico/editoriale e privacy (IP hash,
  user agent) — richiede sempre una decisione esplicita, mai un'azione
  automatica.
- **Conversione legacy a WebP** (vedi §5): beneficio potenzialmente
  significativo sui PNG fotografici più pesanti della libreria, ma riscrive
  dati di produzione — richiede decisione esplicita e l'implementazione
  transazionale descritta in §5.
- **Riduzione della retention dei backup** (attualmente 7 copie): mai da
  cambiare senza una decisione esplicita — è l'unica rete di sicurezza per
  il database.
