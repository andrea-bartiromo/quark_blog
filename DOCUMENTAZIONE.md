# Quark Blog — Documentazione tecnica

> Riferimento tecnico approfondito per chi sviluppa su questo repository. Per una panoramica generale, l'installazione rapida, lo stack e la roadmap vedi **[README.md](README.md)** — qui ci si concentra su dettagli implementativi, schema del database e comportamenti che il README non copre.

---

## Indice

1. [Struttura del progetto](#1-struttura-del-progetto)
2. [Database — tabelle e campi](#2-database--tabelle-e-campi)
3. [Modelli Eloquent](#3-modelli-eloquent)
4. [Rotte](#4-rotte)
5. [Controller](#5-controller)
6. [View Blade e componenti](#6-view-blade-e-componenti)
7. [Configurazione personalizzata](#7-configurazione-personalizzata)
8. [Sicurezza](#8-sicurezza)
9. [Redazione, revisione e verifica delle fonti](#9-redazione-revisione-e-verifica-delle-fonti)
10. [Sistema immagini e metadati cover](#10-sistema-immagini-e-metadati-cover)
11. [Activity Log](#11-activity-log)
12. [Newsletter](#12-newsletter)
13. [Turing](#13-turing)
14. [Automazione notizie con AI](#14-automazione-notizie-con-ai)
15. [SEO e indicizzazione](#15-seo-e-indicizzazione)
16. [Schedulazione automatica](#16-schedulazione-automatica)
17. [Backup automatico](#17-backup-automatico)
18. [CSS e asset](#18-css-e-asset)
19. [Variabili d'ambiente](#19-variabili-dambiente)
20. [Comandi artisan utili](#20-comandi-artisan-utili)
21. [Deploy in produzione](#21-deploy-in-produzione)
22. [Test automatici](#22-test-automatici)
23. [Credenziali di sviluppo](#23-credenziali-di-sviluppo)
24. [Roadmap](#24-roadmap)
25. [Errori noti e limitazioni](#25-errori-noti-e-limitazioni)

---

## 1. Struttura del progetto

```
quark_blog/
├── app/
│   ├── Console/Commands/
│   │   ├── BackupDatabase.php              # backup:database
│   │   ├── FetchNewsAndGenerateDrafts.php  # news:fetch (bozze AI da feed RSS)
│   │   └── SendWeeklyNewsletter.php        # newsletter:send
│   ├── Jobs/
│   │   └── SendNewsletterJob.php           # invio asincrono per singolo iscritto
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                      # pannello Amministrazione (editor/admin)
│   │   │   ├── Redazione/                  # area collaboratori (author)
│   │   │   └── *.php                       # controller pubblici (Home, Article, Search, Author, Newsletter, Contact, Seo, Turing pubblico, ...)
│   │   └── Middleware/
│   │       ├── SecurityHeaders.php         # header HTTP di sicurezza (globale)
│   │       ├── EditorMiddleware.php        # alias 'editor' — accesso Amministrazione
│   │       ├── RedazioneMiddleware.php     # alias 'redazione' — accesso area Redazione
│   │       ├── LoginRateLimiter.php        # alias 'login.limit'
│   │       └── LogLoginAttempts.php        # alias 'login.log'
│   ├── Models/                             # Article, User, Category, Media, ActivityLog, Ad,
│   │                                        # SpecialPage, Newsletter(+Open/Click), Comment,
│   │                                        # ArticleView, NewsSuggestion
│   └── Services/
│       └── ImageService.php                # naming, upload, resize e compressione immagini
├── config/
│   └── laboratorio.php                     # nome sito, tagline, categorie di fallback, social
├── database/
│   ├── database.sqlite                     # database locale (non versionato)
│   ├── migrations/                         # migration Laravel ufficiali
│   └── seeders/                            # DatabaseSeeder, TuringSeeder
├── public/
│   ├── assets/img/                         # cover articoli, placeholder, immagini categorie
│   ├── assets/icons/                       # logo, favicon
│   ├── css/                                # CSS scritto a mano (nessun framework)
│   ├── .htaccess                           # sicurezza/rewrite Apache
│   └── robots.txt
├── resources/views/                        # vedi sezione 6
├── routes/
│   ├── web.php                             # tutte le rotte HTTP
│   └── console.php                         # schedulazione comandi Artisan
├── tests/                                  # vedi sezione 22
├── .env.example                            # template sviluppo (nessun segreto)
├── .env.production.example                 # template produzione (nessun segreto)
├── deploy.sh                               # script di deploy
├── SECURITY.md                             # gestione segreti e credenziali
└── README.md                               # panoramica e quick start
```

---

## 2. Database — tabelle e campi

Tutte le tabelle sono create da migration Laravel ufficiali in `database/migrations/`; non esistono più script esterni che creano tabelle a runtime.

### `articles`

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK |
| user_id | INTEGER | FK → `users.id` |
| title, slug | varchar | slug generato automaticamente dal titolo (mutator sul modello) |
| excerpt, body | TEXT | |
| category | varchar | slug categoria (vedi tabella `categories`) |
| cover_image | varchar, nullable | nome file in `public/assets/img/` |
| status | varchar | `draft`, `review`, `published` |
| featured | boolean | in evidenza in homepage |
| read_minutes | INTEGER | calcolato automaticamente dai controller (~180-200 parole/min) |
| views | INTEGER | contatore, incrementato da `ArticleController@show` |
| published_at | datetime, nullable | |
| verification_status | varchar | `unverified`, `in_progress`, `verified`, `needs_update` |
| verification_notes, verified_at, verified_by, primary_sources | | verifica delle fonti (vedi sezione 9) |
| **cover_alt** | varchar, nullable | testo alternativo dell'immagine di copertina |
| **cover_caption** | TEXT, nullable | didascalia editoriale |
| **cover_credit** | varchar, nullable | credito/autore dell'immagine |
| **cover_source**, **cover_source_url** | varchar, nullable | fonte e relativo URL |
| **cover_license** | varchar, nullable | licenza dell'immagine |

I campi `cover_*` sono stati aggiunti da una migration additiva (`2026_07_15_152453_add_cover_metadata_fields_to_articles_table`), tutti nullable per piena retrocompatibilità con gli articoli esistenti — vedi sezione 10.

### `users`

`id`, `name`, `email`, `password`, `remember_token`, `bio`, `photo`, `role` (`editor`, `admin` o `author`), `twitter`, `linkedin`.

### `categories`

`id`, `name`, `slug`, `description`, `color`, `sort_order`, `is_active`, `image`. Gestite da `Admin\CategoryController`; se la tabella è vuota, `Category::options()` ricade sull'elenco statico in `config/laboratorio.php`.

### `media`

`id`, `user_id`, `filename` (originale), `disk_name` (su disco), `mime_type`, `size`, `alt_text`.

### `activity_log`

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK |
| user_id | INTEGER, nullable | FK → `users.id`, `null` on delete |
| action | varchar, required | descrizione testuale dell'azione |
| subject_type, subject_id | varchar / INTEGER, nullable | riferimento libero (non FK: `subject_type` può essere `'article'`, `'user'`, ecc.) |
| subject_title | varchar, nullable | |
| ip | varchar, nullable | |
| created_at | datetime, nullable | impostato manualmente dal modello — nessun `updated_at` (`$timestamps = false`) |

Tabella creata dalla migration `2026_07_15_155505_create_activity_log_table` — vedi sezione 11.

### `special_pages`

`id`, `slug` (es. `turing`), `title`, `description`, `content` (JSON, cast `array`), `is_active`. Usata per il contenuto configurabile della sezione Turing — vedi sezione 13.

### `news_suggestions`

Bozze generate da `news:fetch`: `source_title`, `source_url`, `source_name`, `source_excerpt`, `category`, `generated_title`, `generated_excerpt`, `generated_body`, `status` (`pending`, `approved`, `published`, `rejected`), `article_id` (dopo pubblicazione), `fetched_at`.

### `newsletter`, `newsletter_opens`, `newsletter_clicks`

- `newsletter`: `email`, `confirmed`, `token`, `unsubscribe_token`.
- `newsletter_opens`: `newsletter_id`, `email`, `ip_hash`, `user_agent`, `opened_at` — tracciamento apertura via pixel.
- `newsletter_clicks`: `newsletter_subscriber_id`, `article_id`, `email`, `ip_hash`, `user_agent`, `url`, `clicked_at` — tracciamento click sui link articolo.

### `comments`

`article_id`, `name`, `email`, `body`, `status` (`pending`, `approved`, `rejected`).

### `article_views`

`article_id`, `ip_hash`, `user_agent`, `referer`, `viewed_at` — una riga per visita unica (deduplicata via sessione in `ArticleController@show`).

### Tabelle standard Laravel

`cache`, `jobs` (coda `database`), `sessions`/`password_reset_tokens` (create insieme a `users`).

### Tabella mancante nota: `ads`

`App\Models\Ad` e `Admin\AdController` (rotte `admin.ads.*`) esistono e sono referenziati dalle rotte, ma **non esiste alcuna migration per la tabella `ads`** — su un'installazione pulita, `Admin\AdController@index` fallisce con `SQLSTATE[HY000]: no such table: ads`. È lo stesso tipo di problema già risolto per `activity_log`, ma non ancora corretto qui. Vedi sezione 25.

---

## 3. Modelli Eloquent

Punti rilevanti non ovvi dal codice (per l'elenco completo dei modelli vedi la struttura in sezione 1):

- **`Article`** — `belongsTo(User::class, 'user_id')` come `author`; `hasMany(Comment::class)` filtrata su `status = approved`; scope `published()`, `featured()`, `byCategory()`; `setTitleAttribute()` genera lo slug solo se non già impostato; `related()` restituisce articoli della stessa categoria.
- **`Category`** — `hasMany(Article::class, 'category', 'slug')` (FK logica sullo slug, non su un ID); `options()` è il punto di accesso usato dai form per popolare il menu categorie, con fallback a `config('laboratorio.categories')`.
- **`ActivityLog`** — `$timestamps = false`; il metodo statico `record(string $action, ?string $subjectType, ?int $subjectId, ?string $subjectTitle)` è l'unico punto d'ingresso usato dai controller per scrivere nel log (vedi sezione 11).
- **`SpecialPage`** — `content` è un campo JSON (cast `array`); `bySlug()` unisce il contenuto salvato con un array di default via `array_replace_recursive`, così le sezioni non ancora compilate mostrano comunque un testo sensato.
- **`Media`** — appende dinamicamente `url` (URL pubblica del file) e `human_size` (dimensione leggibile); non ha alcuna relazione con `Article` (il campo `cover_image` degli articoli è un nome file libero, non una FK verso `media`).
- **`Ad`** — `forPosition()` recupera gli annunci attivi per una posizione (`articolo-top`, `sidebar`, ecc.); `render()` genera l'HTML per i tre tipi supportati (`adsense`, `banner`, `html`).

---

## 4. Rotte

Le rotte sono tutte definite in `routes/web.php` (nessuna route separata per API). Riepilogo per area — per l'elenco esatto e sempre aggiornato usare `php artisan route:list`.

### Pubbliche

| Area | Rotte |
|---|---|
| Contenuti | `/`, `/notizie`, `/categoria/{slug}`, `/articolo/{slug}`, `/ricerca`, `/autore/{user}` |
| Turing | `/turing`, `/turing/enigma`, `/turing/ai` |
| Pagine statiche | `/la-redazione`, `/chi-siamo`, `/pubblicita`, `/contatti`, `/privacy`, `/cookie`, `/termini`, `/rettifiche` |
| Newsletter | `POST /newsletter/subscribe` (throttle 5/min), `/newsletter/conferma`, `/newsletter/disiscrivi`, `/newsletter/open/{subscriber}` (pixel), `/newsletter/click/{subscriber}/{article}` |
| Commenti / contatti | `POST /commenti` (throttle 3/min), `POST /contatti` (throttle 3/min) |
| SEO | `/sitemap.xml`, `/sitemap-index.xml`, `/news-sitemap.xml`, `/feed.xml` |

### Autenticazione

Le vecchie rotte `/admin/login` e `/redazione/login` restituiscono `404` esplicitamente (`fn () => abort(404)`) e non sono più valide. Il login reale è su URL con un suffisso non ovvio, definiti in `routes/web.php` (nomi: `login` per Amministrazione, `redazione.login` per Redazione) — usare `php artisan route:list --name=login` in locale per trovarli. Entrambi i form POST passano dai middleware `login.limit` e `login.log`.

### Amministrazione (`/admin/...`, middleware `auth` + `editor`)

Articoli, categorie, commenti, collaboratori, newsletter (lista, export CSV, anteprima, invio manuale), suggerimenti AI, Turing (editor contenuti), verifica fonti, media, profilo, revisione editoriale, statistiche, activity log, pubblicità (`ads`, con il limite descritto in sezione 2).

### Redazione (`/redazione/...`, middleware `auth` + `redazione`)

Dashboard, CRUD dei propri articoli (`Redazione\ArticleController`, con controllo di proprietà su `user_id` e blocco della modifica se l'articolo è già `published`), profilo.

---

## 5. Controller

### Pubblici (namespace `App\Http\Controllers`)

| Controller | Responsabilità |
|---|---|
| `HomeController` | Homepage: articolo in evidenza, ultimi articoli, selezione per categoria |
| `ArticleController` | `index()` lista paginata, `show($slug)` pagina articolo (incrementa `views`, carica correlati), `category($slug)` lista per categoria |
| `SearchController` | Ricerca con filtri `?q=`, `?categoria=`, `?autore=`, `?da=`/`?a=` (intervallo date) |
| `AuthorController` | Pagina pubblica autore con i suoi articoli pubblicati |
| `CommentController` | Submit commento pubblico (con honeypot anti-spam) |
| `ContactController` | Form contatti (throttle + honeypot) |
| `NewsletterController` / `NewsletterTrackingController` | Iscrizione/conferma/disiscrizione; tracking apertura e click |
| `SeoController` | Sitemap, sitemap news, feed RSS |
| `TuringPageController` / `TuringPublicController` | Pagina Turing principale e le due sotto-pagine (`enigma`, `ai`) |

### Amministrazione (`App\Http\Controllers\Admin`)

| Controller | Responsabilità |
|---|---|
| `DashboardController` | Statistiche di sintesi (articoli per stato, top articoli, distribuzione per categoria, attività mensile) |
| `ArticleController` | CRUD articoli, upload cover via `ImageService`, calcolo `read_minutes`, gestione metadati cover (sezione 10) |
| `CategoryController` | CRUD categorie, upload immagine categoria via `ImageService` |
| `CommentController` | Moderazione commenti (approva/elimina) |
| `CollaboratorController` | Gestione redattori: creazione con invio credenziali via email, modifica, reset password, rimozione con riassegnazione automatica degli articoli all'editor |
| `ReviewController` | Approvazione/rifiuto degli articoli inviati dalla Redazione (vedi sezione 9) |
| `VerificationController` | Pannello di verifica delle fonti (vedi sezione 9) — distinto dal flusso di revisione |
| `MediaController` | Libreria media, upload via `ImageService` |
| `NewsletterController` / `NewsletterPreviewController` | Lista iscritti, export CSV, anteprima della prossima newsletter, invio manuale immediato |
| `SuggestionController` | Bozze generate da `news:fetch`: approvazione/pubblicazione |
| `TuringController` | Editor del contenuto JSON della sezione Turing |
| `StatsController` / `ActivityController` | Statistiche estese con grafici; elenco paginato dell'Activity Log |
| `AdController` | CRUD annunci pubblicitari (soggetto al problema di tabella mancante in sezione 2) |
| `ProfileController` | Profilo utente admin (dati, password, foto) |

### Redazione (`App\Http\Controllers\Redazione`)

`DashboardController`, `ArticleController` (CRUD limitato ai propri articoli, invio automatico in stato `review`), `ProfileController`.

---

## 6. View Blade e componenti

Le view sono organizzate per area sotto `resources/views/`:

- **Pubbliche** — `home.blade.php` (+ partial in `home/partials/`), `notizie.blade.php`, `categoria.blade.php`, `articolo.blade.php` (+ partial in `articles/partials/`: hero, body, author-card, related-articles, share-card, newsletter-band), `autore.blade.php`, `ricerca.blade.php`, pagine statiche, `errors/403|404|500.blade.php`.
- **Amministrazione** — sotto `admin/`, una view per area (articoli, categorie, collaboratori, commenti, newsletter, media, suggerimenti, verifica, revisione, statistiche, attività, annunci, profilo) più `admin/turing*.blade.php` per l'editor dei contenuti Turing.
- **Redazione** — sotto `redazione/` (login, dashboard, articoli, form articolo, profilo), con `layouts/redazione.blade.php` dedicato.
- **Turing** — `turing.blade.php` più `turing/index|enigma|ai.blade.php` e i partial in `turing/partials/` (hero, intro, timeline, editorial-blocks, route-grid, terminal-band, legacy-section, final-card).
- **Componenti riutilizzabili** (`components/`) — `header`, `footer`, `sidebar`, `category-bar`, `ticker`, `pagination`, `cookie-bar`, `newsletter-popup`, `newsletter-alert`, `ad-slot`, `adsense`.
- **Layout** — `layouts/app.blade.php` (pubblico), `layouts/admin.blade.php`, `layouts/redazione.blade.php`, con partial condivisi in `layouts/partials/`.

`welcome.blade.php` è lo scaffold di default di Laravel: resta nel repository ma non è raggiungibile da nessuna rotta (vedi sezione 25 sullo stack Tailwind/Vite).

---

## 7. Configurazione personalizzata

File: `config/laboratorio.php` — nome del sito (`Quark`), tagline, elenco categorie di fallback (usato da `Category::options()` quando la tabella `categories` è vuota) e link social. Accesso nelle view con `config('laboratorio.name')`, `config('laboratorio.categories')`, ecc.

La chiave Anthropic è esposta tramite `config('services.anthropic.key')` (da `ANTHROPIC_API_KEY` in `.env`), non letta direttamente da `env()` nei controller/comandi.

---

## 8. Sicurezza

Per la gestione di segreti, `.env` e credenziali reali fare riferimento a **[SECURITY.md](SECURITY.md)** — qui solo i meccanismi implementati nel codice:

- **`SecurityHeaders`** (middleware globale) — imposta `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` e, in produzione, `Strict-Transport-Security`.
- **URL di login offuscati** — le rotte reali di login Amministrazione/Redazione non usano i path ovvi (`/admin/login`, `/redazione/login`, che rispondono sempre `404`); i path reali sono definiti in `routes/web.php`.
- **Rate limiting** — `login.limit` sui form di login, throttle di Laravel su newsletter/commenti/contatti.
- **`LogLoginAttempts`** — traccia i tentativi di login (alias `login.log`).
- **Honeypot anti-spam** — campo nascosto sul form contatti; se compilato la richiesta viene scartata silenziosamente.
- **`.htaccess`** — blocca l'accesso diretto a `.env`, `.git`, `storage/`, `bootstrap/cache/` e ad alcune estensioni sensibili (`.sqlite`, `.log`, `.sh`, `.sql`, `.bak`, ecc.).
- **Validazione input** — tutti i controller usano `$request->validate()`; l'output nelle view usa `{{ }}` (escaped) salvo dove il contenuto è esplicitamente marcato come HTML fidato (es. corpo articolo).

---

## 9. Redazione, revisione e verifica delle fonti

Ci sono **due flussi distinti**, spesso confusi tra loro:

### Revisione editoriale (pubblicazione)

Un collaboratore (`role = author`) scrive/modifica un articolo dall'area Redazione: l'articolo viene salvato con `status = review` e l'editor riceve una notifica email. Da `/admin/revisione` (`Admin\ReviewController`) l'editor può:
- **approvare** → `status = published`, `published_at = now()`, email di conferma all'autore;
- **rifiutare** → `status = draft` con una nota facoltativa, email all'autore con il motivo.

Un articolo `published` non è più modificabile dall'autore (`Redazione\ArticleController` blocca la modifica con `403`).

### Verifica delle fonti

Indipendente dallo stato di pubblicazione. Ogni articolo ha `verification_status`: `unverified`, `in_progress`, `verified`, `needs_update`. Il pannello `/admin/verifica` (`Admin\VerificationController`) elenca gli articoli ordinati per urgenza; quando lo stato passa a `verified`, `verified_at` e `verified_by` vengono impostati automaticamente.

---

## 10. Sistema immagini e metadati cover

### `ImageService`

`app/Services/ImageService.php` centralizza la logica tecnica di upload usata da `Admin\ArticleController`, `Admin\MediaController`, `Admin\CategoryController` e `Redazione\ArticleController`, evitando la duplicazione che c'era in precedenza:

- `buildFileName()` — genera un nome file univoco (slug del nome originale + suffisso).
- `ensureDirectoryExists()` — crea la cartella di destinazione se assente.
- `upload()` — sposta il file caricato nella destinazione finale.
- `resizeAndCompress()` — ridimensiona (se oltre una larghezza massima) e comprime con **GD**, se l'estensione è disponibile; altrimenti l'immagine viene salvata così com'è, senza errori.

Ogni controller passa i propri parametri (larghezza massima, qualità di compressione, se preservare la trasparenza PNG, se ricomprimere sempre, se loggare gli errori) — la logica tecnica condivisa non impone un comportamento identico ovunque, solo elimina la duplicazione del codice. Il formato del file salvato è sempre lo stesso di quello caricato (JPEG, PNG o WebP): `ImageService` ricomprime nel formato originale, non ne genera uno diverso.

**Non implementato:** generazione WebP/AVIF aggiuntiva, thumbnail, `srcset`, media picker, deduplicazione. Il sistema salva e serve l'immagine nel formato caricato dall'utente.

### Metadati editoriali della cover

Oltre a `cover_image`, ogni articolo può avere (tutti facoltativi, colonne nullable — vedi sezione 2): `cover_alt`, `cover_caption`, `cover_credit`, `cover_source`, `cover_source_url`, `cover_license`. Gestiti dagli stessi form di Amministrazione e Redazione, validati (`cover_source_url` con la regola `url`) e mostrati nella vista pubblica dell'articolo solo se valorizzati:
- l'`alt` dell'immagine hero usa `cover_alt`, con fallback al titolo dell'articolo se vuoto;
- didascalia, credito, fonte (linkata con `rel="noopener noreferrer"` se l'URL è valido) e licenza compaiono in un blocco `figure`/`figcaption` sopra il corpo dell'articolo, **solo se almeno uno di questi campi è compilato** — nessun contenitore vuoto.

### Placeholder

`public/assets/img/placeholder-1.svg` (16:10, card articoli/categorie/autore) e `public/assets/img/hero-placeholder.svg` (1.91:1, fallback per l'immagine Open Graph) sono SVG generati internamente, senza dipendenze esterne, usati quando un articolo o un autore non hanno un'immagine di copertina.

---

## 11. Activity Log

`App\Models\ActivityLog` registra le azioni amministrative rilevanti tramite il metodo statico `ActivityLog::record($action, $subjectType, $subjectId, $subjectTitle)`, chiamato da: creazione/duplicazione/eliminazione articoli (`Admin\ArticleController`), invio/modifica articolo dalla Redazione (`Redazione\ArticleController`), approvazione/rifiuto in revisione (`Admin\ReviewController`), aggiunta/modifica/rimozione collaboratori (`Admin\CollaboratorController`).

La tabella `activity_log` è creata dalla migration `2026_07_15_155505_create_activity_log_table` (schema in sezione 2) — **non esiste più alcuno script esterno** che la crei: su un'installazione pulita è sufficiente `php artisan migrate`. L'elenco è consultabile da `/admin/activity` (`Admin\ActivityController`).

---

## 12. Newsletter

- **Iscrizione pubblica** con conferma email (`NewsletterController`, tabella `newsletter`).
- **Invio settimanale automatizzato** — comando `newsletter:send` (schedulato il giovedì, vedi sezione 16): seleziona i 3 articoli più letti degli ultimi 7 giorni più i 2 più recenti (con fallback ai più letti globali se non ce ne sono abbastanza di recenti), poi mette in coda un `SendNewsletterJob` per ogni iscritto confermato. L'introduzione testuale della newsletter è un testo fisso definito nel job, **non generata dall'AI** nonostante il commento in `routes/console.php` suggerisca il contrario (vedi sezione 25).
- **Tracciamento** — pixel di apertura (`newsletter_opens`) e redirect di tracciamento sui link articolo (`newsletter_clicks`).
- **Strumenti admin** — `Admin\NewsletterController` (lista iscritti, export CSV), `Admin\NewsletterPreviewController` (anteprima della prossima newsletter, invio manuale immediato che richiama lo stesso comando `newsletter:send`).

---

## 13. Turing

Sezione editoriale speciale dedicata ad Alan Turing, indipendente dal sistema articoli. Il contenuto (hero, introduzione, sezione "perché conta ancora" con elementi a griglia, timeline storica, card di approfondimento, blocchi editoriali, sezione finale) è salvato come JSON nel campo `content` di una riga `special_pages` con `slug = turing`, popolata inizialmente da `TuringSeeder` e modificabile da `/admin/turing` (`Admin\TuringController`).

Tre rotte pubbliche condividono lo stesso impianto grafico: `/turing` (pagina principale, `TuringPageController`), `/turing/enigma` e `/turing/ai` (approfondimenti tematici, `TuringPublicController`).

---

## 14. Automazione notizie con AI

```bash
php artisan news:fetch               # esecuzione normale
php artisan news:fetch --dry-run     # anteprima, nulla viene salvato
php artisan news:fetch --category=spazio  # solo una categoria
```

Richiede `ANTHROPIC_API_KEY` in `.env`. Il comando (`FetchNewsAndGenerateDrafts`):

1. raccoglie notizie da un insieme di feed RSS di fonti giornalistiche e istituzionali italiane, organizzati per categoria (ANSA, Wired Italia, Corriere, QualEnergia, Rinnovabili.it, Ministero della Salute, Quotidiano Sanità, AIRC, INAF, ASI, AGI, Il Sole 24 Ore, GreenReport, ISPRA, tra le altre — l'elenco esatto è in `FetchNewsAndGenerateDrafts::$feeds`);
2. filtra le notizie rilevanti con una lista di parole chiave scientifiche/tecnologiche;
3. chiama l'API Anthropic (modello `claude-sonnet-4-6`) con un prompt editoriale che vieta di inventare nomi/dati, impone il condizionale sui fatti non confermati e richiede una nota redazionale finale con cosa verificare;
4. salva le bozze in `news_suggestions` con `status = pending`;
5. il redattore le rivede in `/admin/suggerimenti` (`Admin\SuggestionController`), approvandole o pubblicandole.

---

## 15. SEO e indicizzazione

- **Schema.org / JSON-LD** — in `articolo.blade.php`, markup `NewsArticle` con `headline`, `datePublished`/`dateModified`, `image`, `author`, `publisher` (`NewsMediaOrganization`), `articleSection`, `inLanguage: it-IT`.
- **Open Graph** — `og:title`, `og:description`, `og:image` (con fallback al placeholder hero se l'articolo non ha copertina), `og:type: article`, meta `article:*`.
- **Sitemap** — `/sitemap.xml`, `/sitemap-index.xml`, `/news-sitemap.xml` (formato Google News, ultimi giorni), generate da `SeoController`.
- **Feed** — `/feed.xml`, RSS 2.0 con Dublin Core.

---

## 16. Schedulazione automatica

File: `routes/console.php`.

| Comando | Frequenza |
|---|---|
| `newsletter:send` | Giovedì alle 9:00 |
| `news:fetch` | Lunedì e giovedì alle 9:30 |
| `backup:database` | Ogni giorno alle 2:00 |
| `cache:prune-stale-tags` | Domenica alle 3:00 |

Attivazione sul server (crontab):

```
* * * * * cd /percorso/del/sito && php artisan schedule:run >> /dev/null 2>&1
```

---

## 17. Backup automatico

```bash
php artisan backup:database [--keep=7]
```

Copia `database/database.sqlite` in `storage/backups/` con timestamp nel nome file, mantenendo gli ultimi N backup (default 7).

---

## 18. CSS e asset

Nessun framework CSS: tutti i fogli di stile in `public/css/` sono scritti a mano e caricati direttamente dai layout (non tramite la pipeline Vite). File principali: `style.css` (sito pubblico), `admin.css` (pannello admin), `public-premium.css`/`public-unified.css`/`home-premium.css`/`home-fix.css`/`premium-fixes.css` (varianti/estensioni del layout pubblico e della home), `article-extras.css`, `turing.css`/`turing-overrides.css`.

Vite + Tailwind CSS 4 sono installati e configurati (`vite.config.js`, `resources/css/app.css`, `resources/js/app.js`), ma referenziati con `@vite` solo dallo scaffold di default `welcome.blade.php`, che non è raggiungibile da alcuna rotta reale — non fanno parte della pipeline di stile effettiva del sito.

---

## 19. Variabili d'ambiente

Template disponibili: `.env.example` (sviluppo) e `.env.production.example` (produzione) — nessuno dei due contiene segreti reali. Procedura e regole complete in **[SECURITY.md](SECURITY.md)**.

Variabili rilevanti per funzionalità opzionali:

```env
# Automazione notizie AI (sezione 14)
ANTHROPIC_API_KEY=

# Invio email (reset password, notifiche redazionali, newsletter)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=      # App Password dedicata, mai la password dell'account
MAIL_ENCRYPTION=tls
```

`DB_CONNECTION=sqlite` è il default; `DB_DATABASE` non serve impostarlo esplicitamente per SQLite (Laravel usa `database/database.sqlite` di default).

---

## 20. Comandi artisan utili

```bash
# Sviluppo
php artisan serve
php artisan route:list
php artisan migrate
php artisan migrate:fresh --seed
php artisan tinker

# Automazione
php artisan news:fetch [--dry-run] [--category=spazio]
php artisan newsletter:send [--dry-run]
php artisan backup:database [--keep=14]

# Produzione (dopo ogni deploy)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear

# Schedulazione
php artisan schedule:run
php artisan schedule:list
```

---

## 21. Deploy in produzione

```bash
bash deploy.sh
```

Lo script verifica PHP ≥ 8.3 e `APP_DEBUG=false`, genera `APP_KEY` se assente, esegue le migration, ottimizza le cache, imposta i permessi su `storage`/`bootstrap/cache` e crea un backup immediato del database.

Passi manuali post-deploy: aggiornare `public/robots.txt` con il dominio reale, decommentare il redirect HTTPS in `public/.htaccess`, impostare il cron job per `schedule:run`, configurare il server web (Apache/Nginx) per servire da `public/`.

---

## 22. Test automatici

```bash
php artisan test
```

I test (PHPUnit, Feature e Unit) vivono in `tests/` e usano un database SQLite dedicato e isolato, configurato in `phpunit.xml` (non tocca `database/database.sqlite` di sviluppo). La stessa suite gira automaticamente su GitHub Actions (`.github/workflows/tests.yml`) ad ogni push e pull request su `main`, su PHP 8.4.

La copertura più recente riguarda in particolare: i metadati editoriali della cover (creazione, aggiornamento, validazione di `cover_source_url`, retrocompatibilità, rendering pubblico) e l'Activity Log (creazione della tabella, `record()`, associazione utente, soggetto nullable, integrazione con le rotte reali che lo scrivono). Il resto della copertura è più leggero: non tutti i controller e i flussi (Redazione, revisione, verifica fonti, newsletter, Turing) hanno test dedicati.

---

## 23. Credenziali di sviluppo

Vedi la tabella completa in **[README.md](README.md#account-demo)**. Gli account creati da `DatabaseSeeder` sono destinati esclusivamente allo sviluppo locale: un `editor` (accesso Amministrazione) e due `author` (accesso Redazione). Gli indirizzi email usano ancora il dominio storico `@illaboratorio.it` (nome del progetto prima del rebranding a "Quark") — sono dati di seed, non indirizzi reali.

---

## 24. Roadmap

Vedi anche la roadmap sintetica in [README.md](README.md#roadmap); qui la stessa suddivisione con qualche dettaglio tecnico in più.

**Completato**
- Header di sicurezza HTTP, URL di login offuscati, rate limiting, `SECURITY.md`
- Sezione editoriale Turing (contenuto configurabile via `SpecialPage`)
- `ImageService` centralizzato (naming, upload, resize, compressione condivisi tra i controller)
- Placeholder SVG per le immagini mancanti (card e Open Graph)
- Metadati editoriali della cover (`cover_alt`, `cover_caption`, `cover_credit`, `cover_source`, `cover_source_url`, `cover_license`)
- Migration ufficiale per `activity_log` (rimosso lo script manuale che la creava)

**In corso**
- Allineamento della documentazione tecnica allo stato reale del progetto
- Estensione della copertura dei test automatici ai flussi non ancora coperti (Redazione, revisione, newsletter, Turing)

**Futuro**
- Migration per la tabella `ads` mancante (vedi sezione 25)
- Demo pubblica
- Miglioramento della responsività
- Libreria media più evoluta
- Sostituzione delle immagini placeholder con foto originali
- Ottimizzazione delle performance

---

## 25. Errori noti e limitazioni

### Tabella `ads` assente

`Admin\AdController` e il modello `Ad` sono referenziati dalle rotte `admin.ads.*` ma non esiste alcuna migration per la tabella `ads` (vedi sezione 2): su un'installazione pulita, `/admin/ads` genera un errore `SQLSTATE[HY000]: no such table: ads`. Va corretto con una migration dedicata, seguendo lo stesso approccio già usato per `activity_log`.

### Commento fuorviante su `newsletter:send`

Il commento sopra la schedulazione di `newsletter:send` in `routes/console.php` dice "genera intro con AI", ma il comando (tramite `SendNewsletterJob`) usa un testo introduttivo fisso — non chiama l'API Anthropic. Il comportamento reale è descritto in sezione 12.

### `welcome.blade.php` e stack Tailwind/Vite inutilizzato

Vite e Tailwind CSS 4 sono installati e configurati, ma l'unica view che li referenzia (`welcome.blade.php`) non è raggiungibile da nessuna rotta. Non è un errore bloccante, ma può confondere chi si aspetta che il sito sia stilizzato con Tailwind: il CSS reale è scritto a mano in `public/css/` (sezione 18).

### Branding storico "Il Laboratorio"

Il progetto si chiamava in precedenza "Il Laboratorio"; il rebranding a "Quark" è completo nell'app (`config/laboratorio.php`, `.env.example`), ma alcuni artefatti storici sopravvivono: gli indirizzi email dei dati di seed (`@illaboratorio.it`), la descrizione e l'homepage degli autori in `composer.json`, e alcuni testi in `deploy.sh`. Non hanno impatto funzionale.
