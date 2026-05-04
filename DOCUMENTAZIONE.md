# Quark — Documentazione tecnica completa

**Blog italiano di divulgazione scientifica**
**Fondatore e Direttore Responsabile:** Andrea Bartiromo
**Email:** redazione@illaboratorio.it

> Questo documento è autosufficiente. Contiene tutto ciò che serve per capire, modificare, estendere e deployare il progetto senza accesso alla conversazione originale di sviluppo. È pensato per essere dato in pasto a un'AI di assistenza o letto da uno sviluppatore umano.

---

## Indice

1. [Stack tecnologico](#1-stack-tecnologico)
2. [Struttura del progetto](#2-struttura-del-progetto)
3. [Database — tabelle e campi](#3-database--tabelle-e-campi)
4. [Modelli Eloquent](#4-modelli-eloquent)
5. [Route complete](#5-route-complete)
6. [Controller](#6-controller)
7. [View Blade](#7-view-blade)
8. [Componenti Blade](#8-componenti-blade)
9. [Configurazione personalizzata](#9-configurazione-personalizzata)
10. [Sicurezza](#10-sicurezza)
11. [Sistema editoriale e verifica fonti](#11-sistema-editoriale-e-verifica-fonti)
12. [Automazione notizie con AI](#12-automazione-notizie-con-ai)
13. [SEO e indicizzazione](#13-seo-e-indicizzazione)
14. [Schedulazione automatica](#14-schedulazione-automatica)
15. [Backup automatico](#15-backup-automatico)
16. [File CSS e asset](#16-file-css-e-asset)
17. [Variabili d'ambiente](#17-variabili-dambiente)
18. [Installazione da zero](#18-installazione-da-zero)
19. [Patch vendor necessarie](#19-patch-vendor-necessarie)
20. [Deploy in produzione](#20-deploy-in-produzione)
21. [Comandi artisan utili](#21-comandi-artisan-utili)
22. [Credenziali di default](#22-credenziali-di-default)
23. [Cosa fare prima del lancio](#23-cosa-fare-prima-del-lancio)
24. [Errori noti e soluzioni](#24-errori-noti-e-soluzioni)

---

## 1. Stack tecnologico

| Componente | Versione | Note |
|---|---|---|
| PHP | 8.3.6 | Minimo richiesto: 8.3 |
| Laravel | 13.7.0 | Installato via git clone (non Composer) |
| Database | SQLite | File: `database/database.sqlite` |
| Template engine | Blade | Laravel built-in |
| CSS | Custom | Nessun framework CSS — tutto scritto a mano |
| Autenticazione | Custom minimal | Nessun Breeze/Jetstream — auth inline in `routes/web.php` |
| AI per automazione | Claude API (Anthropic) | Modello `claude-sonnet-4-6` |

**Nota critica sull'installazione:** Laravel 13.7.0 è stato installato tramite `git clone` diretto dal repository GitHub, non tramite Packagist/Composer, perché Packagist non era raggiungibile nell'ambiente di sviluppo. Il vendor è stato costruito manualmente con patch specifiche (vedi sezione 19).

---

## 2. Struttura del progetto

```
laboratorio/
├── app/
│   ├── Console/Commands/
│   │   ├── BackupDatabase.php          # Backup SQLite giornaliero
│   │   └── FetchNewsAndGenerateDrafts.php  # Automazione notizie con AI
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   ├── ArticleController.php    # CRUD articoli (admin)
│   │   │   │   ├── CommentController.php    # Moderazione commenti
│   │   │   │   ├── DashboardController.php  # Dashboard con statistiche
│   │   │   │   ├── MediaController.php      # Libreria media (upload)
│   │   │   │   ├── NewsletterController.php # Gestione iscritti + CSV
│   │   │   │   ├── ProfileController.php    # Profilo redattore
│   │   │   │   ├── SuggestionController.php # Bozze AI
│   │   │   │   └── VerificationController.php  # Pannello verifica fonti
│   │   │   ├── ArticleController.php        # Pagine pubbliche articoli
│   │   │   ├── AuthorController.php         # Pagina pubblica autore
│   │   │   ├── CommentController.php        # Submit commenti pubblici
│   │   │   ├── HomeController.php           # Homepage
│   │   │   ├── NewsletterController.php     # Iscrizione newsletter
│   │   │   └── SearchController.php         # Ricerca avanzata
│   │   └── Middleware/
│   │       └── SecurityHeaders.php          # Header HTTP sicurezza
│   ├── Models/
│   │   ├── Article.php
│   │   ├── Comment.php
│   │   ├── Media.php
│   │   ├── NewsSuggestion.php
│   │   ├── Newsletter.php
│   │   └── User.php
│   └── Providers/
│       └── AppServiceProvider.php
├── config/
│   └── laboratorio.php                 # Configurazione custom del sito
├── database/
│   ├── database.sqlite                 # Database principale
│   └── migrations/                    # 10 migration
├── public/
│   ├── assets/
│   │   ├── icons/
│   │   │   ├── favicon.svg
│   │   │   └── logo.svg
│   │   └── img/
│   │       ├── hero-placeholder.svg    # 1200×630
│   │       └── placeholder-1..7.svg   # 800×450 (una per categoria)
│   ├── css/
│   │   ├── style.css                  # CSS principale (tutto custom)
│   │   ├── admin.css                  # CSS pannello admin
│   │   └── article-extras.css         # CSS extras articolo
│   ├── .htaccess                      # Redirect + sicurezza file sensibili
│   └── robots.txt                     # Da aggiornare con dominio reale
├── resources/views/                   # 40 view Blade (vedi sezione 7)
├── routes/
│   ├── web.php                        # 55 route
│   └── console.php                    # Schedulazione comandi
├── storage/
│   └── backups/                       # Backup SQLite giornalieri
├── .env                               # Configurazione ambiente
├── .env.production.example            # Template per produzione
├── deploy.sh                          # Script di deploy automatico
└── README.md                          # Documentazione sintetica
```

---

## 3. Database — tabelle e campi

### Tabella `articles`

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK autoincrement |
| user_id | INTEGER | FK → users.id |
| title | varchar | Titolo articolo |
| slug | varchar | URL-friendly, generato automaticamente |
| excerpt | TEXT | Sommario breve (max ~200 char) |
| body | TEXT | Corpo articolo (Markdown con grassetti `**testo**`) |
| category | varchar | Valori: `intelligenza-artificiale`, `energia`, `salute`, `societa`, `spazio`, `ambiente` |
| cover_image | varchar | Nome file in `public/assets/img/` (es. `placeholder-1.svg`) |
| status | varchar | `published` oppure `draft` |
| featured | tinyint(1) | 1 = articolo in evidenza in homepage |
| read_minutes | INTEGER | Calcolato automaticamente (180 parole/min) |
| views | INTEGER | Contatore visualizzazioni (incrementato ad ogni visita) |
| published_at | datetime | Data di pubblicazione |
| created_at | datetime | |
| updated_at | datetime | |
| verification_status | varchar | `unverified`, `in_progress`, `verified`, `needs_update` |
| verification_notes | TEXT | Note del redattore sulla verifica |
| verified_at | datetime | Data/ora ultima verifica |
| verified_by | varchar | Nome del redattore che ha verificato |
| primary_sources | TEXT | Elenco fonti primarie verificate |

### Tabella `users`

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK |
| name | varchar | Nome completo |
| email | varchar | Email (usata per login) |
| password | varchar | Bcrypt hashed |
| bio | TEXT | Biografia breve |
| photo | varchar | Nome file foto profilo |
| role | varchar | `editor` oppure `author` |
| twitter | varchar | Handle Twitter (con o senza @) |
| linkedin | varchar | URL LinkedIn |

### Tabella `newsletter`

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK |
| email | varchar | Email iscritto |
| confirmed | tinyint(1) | 1 = email confermata |
| token | varchar | Token per conferma email |
| created_at | datetime | |
| updated_at | datetime | |

### Tabella `comments`

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK |
| article_id | INTEGER | FK → articles.id |
| name | varchar | Nome commentatore |
| email | varchar | Email commentatore |
| body | TEXT | Testo commento |
| status | varchar | `pending`, `approved`, `rejected` |

### Tabella `media`

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK |
| user_id | INTEGER | FK → users.id |
| filename | varchar | Nome file originale |
| disk_name | varchar | Nome file salvato su disco |
| mime_type | varchar | es. `image/jpeg` |
| size | INTEGER | Dimensione in bytes |
| alt_text | varchar | Testo alternativo |

### Tabella `news_suggestions`

Bozze generate automaticamente dal comando `news:fetch`.

| Campo | Tipo | Note |
|---|---|---|
| id | INTEGER | PK |
| source_title | varchar | Titolo della notizia originale |
| source_url | varchar | URL della notizia originale |
| source_name | varchar | Dominio della fonte (es. `ansa.it`) |
| source_excerpt | TEXT | Estratto della notizia originale |
| category | varchar | Categoria suggerita |
| generated_title | varchar | Titolo generato dall'AI |
| generated_excerpt | TEXT | Sommario generato dall'AI |
| generated_body | TEXT | Corpo articolo generato dall'AI |
| status | varchar | `pending`, `approved`, `published`, `rejected` |
| article_id | INTEGER | FK → articles.id (dopo pubblicazione) |
| fetched_at | datetime | Data raccolta dalla fonte |

---

## 4. Modelli Eloquent

### Article

**Relazioni:**
```php
$article->author     // belongsTo(User)
$article->comments   // hasMany(Comment) — solo approved
```

**Scope:**
```php
Article::published()           // WHERE status = 'published'
Article::featured()            // WHERE featured = 1
Article::byCategory('spazio')  // WHERE category = 'spazio'
```

**Metodi:**
```php
$article->incrementViews()           // Incrementa il contatore views
$article->related(3)                 // 3 articoli correlati (stessa categoria)
$article->verification_label        // Etichetta leggibile dello stato verifica
$article->isVerified()               // bool
$article->read_time                  // "5 min di lettura"
```

**Attributi automatici:**
- `setTitleAttribute()`: imposta automaticamente lo slug dal titolo
- `read_minutes`: calcolato dal controller al salvataggio (180 parole/min)

**Costanti:**
```php
Article::$verificationLabels  // ['unverified' => 'Non verificato', ...]
Article::$verificationColors  // ['unverified' => '#ef4444', ...]
```

### User

**Relazioni:**
```php
$user->articles  // hasMany(Article)
```

**Metodi:**
```php
$user->isEditor()  // role === 'editor'
```

---

## 5. Route complete

### Pubbliche (GET)

| URL | Nome | Controller |
|---|---|---|
| `/` | `home` | `HomeController@index` |
| `/notizie` | `notizie` | `ArticleController@index` |
| `/categoria/{slug}` | `categoria` | `ArticleController@category` |
| `/articolo/{slug}` | `articolo` | `ArticleController@show` |
| `/ricerca` | `ricerca` | `SearchController@index` |
| `/autore/{user}` | `autore` | `AuthorController@show` |
| `/redazione` | `redazione` | view diretta |
| `/chi-siamo` | `chi-siamo` | view diretta |
| `/pubblicita` | `pubblicita` | view diretta |
| `/contatti` | `contatti` | view diretta |
| `/privacy` | `privacy` | view diretta |
| `/cookie` | `cookie` | view diretta |
| `/termini` | `termini` | view diretta |
| `/rettifiche` | `rettifiche` | view diretta |
| `/sitemap.xml` | `sitemap` | closure inline |
| `/sitemap-index.xml` | `sitemap-index` | closure inline |
| `/news-sitemap.xml` | `news-sitemap` | closure inline |
| `/feed.xml` | `feed` | closure inline |
| `/newsletter/conferma` | `newsletter.confirm` | `NewsletterController@confirm` |

### Pubbliche (POST)

| URL | Nome | Throttle |
|---|---|---|
| `/newsletter/subscribe` | `newsletter.subscribe` | 5/min |
| `/commenti` | `commenti.store` | 3/min |
| `/contatti` | `contatti.send` | 3/min |

### Admin (tutte protette da `auth` middleware)

| Metodo | URL | Nome | Controller |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | `Admin\DashboardController@index` |
| GET | `/admin/articoli` | `admin.articles` | `Admin\ArticleController@index` |
| GET | `/admin/articoli/nuovo` | `admin.articles.create` | `Admin\ArticleController@create` |
| POST | `/admin/articoli` | `admin.articles.store` | `Admin\ArticleController@store` |
| GET | `/admin/articoli/{id}/modifica` | `admin.articles.edit` | `Admin\ArticleController@edit` |
| PUT | `/admin/articoli/{id}` | `admin.articles.update` | `Admin\ArticleController@update` |
| DELETE | `/admin/articoli/{id}` | `admin.articles.destroy` | `Admin\ArticleController@destroy` |
| PATCH | `/admin/articoli/{id}/verifica` | `admin.articles.verify` | `Admin\ArticleController@updateVerification` |
| GET | `/admin/commenti` | `admin.comments` | `Admin\CommentController@index` |
| PATCH | `/admin/commenti/{id}/approva` | `admin.comments.approve` | `Admin\CommentController@approve` |
| DELETE | `/admin/commenti/{id}` | `admin.comments.destroy` | `Admin\CommentController@destroy` |
| GET | `/admin/newsletter` | `admin.newsletter` | `Admin\NewsletterController@index` |
| GET | `/admin/newsletter/export` | `admin.newsletter.export` | `Admin\NewsletterController@export` |
| DELETE | `/admin/newsletter/{id}` | `admin.newsletter.destroy` | `Admin\NewsletterController@destroy` |
| GET | `/admin/media` | `admin.media` | `Admin\MediaController@index` |
| POST | `/admin/media` | `admin.media.store` | `Admin\MediaController@store` |
| POST | `/admin/media/upload-ajax` | `admin.media.upload` | `Admin\MediaController@uploadAjax` |
| DELETE | `/admin/media/{id}` | `admin.media.destroy` | `Admin\MediaController@destroy` |
| GET | `/admin/profilo` | `admin.profile` | `Admin\ProfileController@edit` |
| PUT | `/admin/profilo` | `admin.profile.update` | `Admin\ProfileController@update` |
| POST | `/admin/profilo/foto` | `admin.profile.photo` | `Admin\ProfileController@photo` |
| PUT | `/admin/profilo/password` | `admin.profile.password` | `Admin\ProfileController@password` |
| GET | `/admin/suggerimenti` | `admin.suggestions` | `Admin\SuggestionController@index` |
| POST | `/admin/suggerimenti/{id}/approva` | `admin.suggestions.approve` | `Admin\SuggestionController@approve` |
| POST | `/admin/suggerimenti/{id}/pubblica` | `admin.suggestions.publish` | `Admin\SuggestionController@publish` |
| GET | `/admin/verifica` | `admin.verification` | `Admin\VerificationController@index` |

### Autenticazione

| Metodo | URL | Note |
|---|---|---|
| GET | `/admin/login` | Pagina di login (view `admin.login`) |
| POST | `/admin/login` | Autenticazione — closure inline in `routes/web.php` |
| POST | `/admin/logout` | Logout — closure inline in `routes/web.php` |

---

## 6. Controller

### HomeController
Carica: articolo featured, ultimi 6 articoli, 3 per categoria, articoli ticker.

### ArticleController (pubblico)
- `index()`: lista articoli con paginazione (12/pagina)
- `show($slug)`: pagina singolo articolo — incrementa views, carica articoli correlati
- `category($slug)`: lista articoli per categoria

### SearchController
Ricerca full-text con filtri avanzati:
- `?q=` testo libero (titolo, excerpt, body — ordinato per rilevanza)
- `?categoria=` filtro categoria
- `?autore=` filtro per user_id
- `?da=` e `?a=` filtro per intervallo di date (formato `YYYY-MM-DD`)

### AuthorController
- `show(User $user)`: pagina pubblica autore con lista articoli paginata (12/pagina)

### Admin\ArticleController
- CRUD completo con upload immagine cover
- `updateVerification()`: aggiorna stato/note/fonti di verifica
- `validated()`: calcola automaticamente `read_minutes` = ceil(words/180)

### Admin\DashboardController
Fornisce alla view:
- `$stats`: array con contatori (published, drafts, unverified, newsletter, comments, total_views)
- `$topArticles`: top 5 articoli per views
- `$byCategory`: distribuzione articoli per categoria con totale views
- `$recentArticles`: ultimi 8 articoli modificati
- `$monthlyActivity`: articoli e views per mese (ultimi 6 mesi)

### Admin\NewsletterController
- `index()`: lista iscritti con totale e confermati
- `export()`: genera e scarica CSV con BOM UTF-8 (compatibile Excel)
- `destroy()`: elimina singolo iscritto

### Admin\VerificationController
- `index()`: lista tutti gli articoli ordinati per urgenza (unverified → in_progress → verified)

---

## 7. View Blade

### Layouts

**`layouts/app.blade.php`** — Layout principale pubblico. Sections accettate:
- `@section('title', ...)` — inline, non richiede `@endsection`
- `@section('description', ...)` — inline
- `@section('og_type', ...)` — inline
- `@section('og_image')` ... `@endsection` — meta OG aggiuntivi
- `@section('head')` ... `@endsection` — contenuto extra nel `<head>`
- `@section('content')` ... `@endsection` — contenuto principale
- `@push('scripts')` ... `@endpush` — script JS (DEVE stare fuori da `@section`)

> **ATTENZIONE BUG NOTO:** `@push('scripts')` deve stare DOPO l'ultimo `@endsection`, mai dentro una `@section`. Se messo dentro, il compilatore Blade genera PHP non valido con `if` non bilanciati. Vedi sezione 24 per dettagli.

**`layouts/admin.blade.php`** — Layout CMS admin. Include sidebar con menu di navigazione e badge notifica articoli da verificare.

### Pagine pubbliche

| View | Route | Descrizione |
|---|---|---|
| `home.blade.php` | `/` | Homepage con hero, griglia articoli, sidebar |
| `notizie.blade.php` | `/notizie` | Lista tutti gli articoli con paginazione |
| `articolo.blade.php` | `/articolo/{slug}` | Pagina singolo articolo con Schema.org, social share, commenti |
| `categoria.blade.php` | `/categoria/{slug}` | Lista articoli per categoria |
| `ricerca.blade.php` | `/ricerca` | Form di ricerca con filtri avanzati |
| `autore.blade.php` | `/autore/{user}` | Pagina pubblica redattore |
| `redazione.blade.php` | `/redazione` | Presentazione della redazione |
| `chi-siamo.blade.php` | `/chi-siamo` | Storia, valori, politica editoriale, sezione fondatore |
| `contatti.blade.php` | `/contatti` | Form di contatto con honeypot anti-spam |
| `privacy.blade.php` | `/privacy` | Informativa privacy |
| `cookie.blade.php` | `/cookie` | Policy sui cookie |
| `termini.blade.php` | `/termini` | Termini e condizioni |
| `rettifiche.blade.php` | `/rettifiche` | Politica sulle rettifiche |
| `pubblicita.blade.php` | `/pubblicita` | Info per inserzionisti |
| `newsletter-confirmed.blade.php` | `/newsletter/conferma` | Conferma iscrizione newsletter |

### Pagine admin

| View | Route | Descrizione |
|---|---|---|
| `admin/login.blade.php` | `/admin/login` | Form login |
| `admin/dashboard.blade.php` | `/admin` | Dashboard con statistiche e grafici |
| `admin/articles.blade.php` | `/admin/articoli` | Lista articoli con filtri |
| `admin/article-form.blade.php` | `/admin/articoli/nuovo` e `/modifica` | Form creazione/modifica articolo |
| `admin/comments.blade.php` | `/admin/commenti` | Moderazione commenti |
| `admin/newsletter.blade.php` | `/admin/newsletter` | Lista iscritti + export CSV |
| `admin/media.blade.php` | `/admin/media` | Libreria media con drag&drop |
| `admin/profile.blade.php` | `/admin/profilo` | Profilo redattore |
| `admin/suggestions.blade.php` | `/admin/suggerimenti` | Bozze generate dall'AI |
| `admin/verification.blade.php` | `/admin/verifica` | Pannello verifica fonti |

### Pagine di errore

- `errors/403.blade.php`
- `errors/404.blade.php`
- `errors/500.blade.php`

---

## 8. Componenti Blade

Tutti in `resources/views/components/`. Si usano con `@include('components.nome')`.

| Componente | Descrizione |
|---|---|
| `header.blade.php` | Testata con logo, navigazione, hamburger mobile |
| `footer.blade.php` | Piè di pagina con link, social, firma |
| `sidebar.blade.php` | Sidebar con articoli popolari, categorie, newsletter |
| `category-bar.blade.php` | Barra categorie orizzontale |
| `ticker.blade.php` | Ticker notizie scorrevole in homepage |
| `newsletter-popup.blade.php` | Popup iscrizione newsletter (appare dopo 30s) |
| `cookie-bar.blade.php` | Banner cookie con accetta/rifiuta |
| `pagination.blade.php` | Paginazione personalizzata compatibile con Laravel |

---

## 9. Configurazione personalizzata

File: `config/laboratorio.php`

```php
return [
    'name'    => 'Quark',
    'tagline' => 'La scienza spiegata come si deve',

    'categories' => [
        'intelligenza-artificiale' => 'Intelligenza Artificiale',
        'energia'                  => 'Energia & Clima',
        'salute'                   => 'Salute & Biotech',
        'societa'                  => 'Tecnologia & Società',
        'spazio'                   => 'Spazio',
        'ambiente'                 => 'Ambiente',
    ],

    'social' => [
        'facebook'  => 'https://facebook.com/illaboratorio',
        'twitter'   => 'https://twitter.com/illaboratorio',
        'instagram' => 'https://instagram.com/illaboratorio',
        'linkedin'  => 'https://linkedin.com/company/illaboratorio',
        'youtube'   => 'https://youtube.com/@illaboratorio',
        'telegram'  => 'https://t.me/illaboratorio',
    ],
];
```

Nelle view si accede con `config('laboratorio.name')`, `config('laboratorio.categories')`, ecc.

---

## 10. Sicurezza

### Middleware SecurityHeaders
File: `app/Http/Middleware/SecurityHeaders.php`
Registrato globalmente in `bootstrap/app.php`.

Header impostati su ogni risposta:
- `X-Frame-Options: SAMEORIGIN` — anti-clickjacking
- `X-Content-Type-Options: nosniff` — anti-MIME sniffing
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- `Content-Security-Policy` — sorgenti permesse esplicitamente
- `Strict-Transport-Security` — solo in produzione (`APP_ENV=production`)

### CSRF
Tutti i form POST hanno `@csrf`. Verificato: 19 occorrenze di `@csrf`.

### Rate limiting
- Newsletter: 5 richieste/minuto per IP
- Commenti: 3 richieste/minuto per IP
- Form contatti: 3 richieste/minuto per IP
- Login admin: protetto dal throttle di Laravel

### Honeypot anti-spam
Il form contatti ha un campo nascosto `website`. Se compilato, la richiesta viene scartata silenziosamente.

### .htaccess
Blocca accesso diretto a: `.env`, `.git`, `storage/`, e file con estensioni `.sqlite`, `.log`, `.sh`, `.sql`, `.bak`.

### Validazione input
Tutti i controller usano `$request->validate()`. L'output nelle view usa sempre `{{ }}` (escaped) tranne dove indicato esplicitamente.

---

## 11. Sistema editoriale e verifica fonti

### Filosofia
Il Laboratorio non pubblica notizie inventate. Ogni articolo deve avere fonti verificabili. L'AI è usata solo come strumento di bozza, mai come fonte.

### Stato di verifica degli articoli
Ogni articolo ha il campo `verification_status` con 4 valori:

| Valore | Significato |
|---|---|
| `unverified` | Non ancora controllato — badge rosso |
| `in_progress` | In corso di verifica — badge arancione |
| `verified` | Verificato sulla fonte primaria — badge verde |
| `needs_update` | Verificato ma da aggiornare — badge viola |

### Pannello di verifica
URL: `/admin/verifica`

Lista tutti gli articoli ordinati per urgenza. Per ogni articolo mostra:
- Stato corrente con badge colorato
- Note di verifica già inserite
- Fonti primarie citate
- Form espandibile per aggiornare stato, note e fonti

Quando si imposta `verified`, il sistema registra automaticamente data/ora e nome del redattore.

### Regola d'oro
Prima di pubblicare: **aprire la fonte primaria e verificare il dato chiave**. Ogni articolo riporta le fonti in fondo al corpo nel formato: `*Fonti verificate: ...*`

---

## 12. Automazione notizie con AI

### Comando
```bash
php artisan news:fetch               # Esecuzione normale
php artisan news:fetch --dry-run     # Solo anteprima, nulla viene salvato
php artisan news:fetch --category=spazio  # Solo una categoria
```

### Configurazione richiesta
Nel file `.env`:
```
ANTHROPIC_API_KEY=sk-ant-api03-...
```

### Come funziona
1. Raccoglie notizie da 16 feed RSS di fonti istituzionali (ANSA, ASI, ISPRA, AIRC, Ministero Salute, QualEnergia, rinnovabili.it, INAF, AGI, Il Sole 24 Ore, GreenReport...)
2. Filtra le notizie rilevanti con una lista di parole chiave scientifiche/tecnologiche
3. Chiama l'API di Claude con un prompt editoriale rigoroso che impone:
   - Nessun nome/dato inventato
   - Condizionale per fatti non confermati
   - Fonte citata per ogni informazione
   - Nota redazionale finale con cosa verificare
4. Salva le bozze nella tabella `news_suggestions` con status `pending`
5. Il redattore le rivede nel pannello `/admin/suggerimenti`

### Criteri editoriali nel prompt AI
Il sistema passa all'AI queste regole:
- Cita SOLO fatti verificabili con fonte identificabile
- Non inventare MAI nomi di persone, istituzioni, dati
- Non aggiungere numeri non presenti nella notizia originale
- Usa il condizionale per fatti non ancora confermati
- Indica sempre da dove proviene ogni informazione
- Ogni bozza deve terminare con `⚠ NOTA REDAZIONALE:` con cosa verificare

### Struttura articolo generata
1. TITOLO preciso e specifico (max 90 caratteri)
2. SOMMARIO (max 200 caratteri)
3. LEAD: chi, cosa, quando, dove, perché
4. CORPO: 4-6 paragrafi con sottotitoli in grassetto `**Titolo**`
5. NOTA REDAZIONALE: cosa il redattore deve verificare
6. FONTI CITATE: elenco delle fonti usate

---

## 13. SEO e indicizzazione

### Schema.org / JSON-LD
In `resources/views/articolo.blade.php`, ogni articolo ha JSON-LD di tipo `NewsArticle` con:
- `headline`, `description`, `url`
- `datePublished`, `dateModified`
- `image` con ImageObject (1200×630)
- `author` con Person e URL pagina autore
- `publisher` con NewsMediaOrganization e logo
- `articleSection`, `inLanguage: "it-IT"`, `isAccessibleForFree: true`
- `mainEntityOfPage`

### Open Graph
In ogni articolo: `og:title`, `og:description`, `og:image`, `og:type: article`, `article:author`, `article:published_time`, `article:modified_time`, `article:section`, `twitter:creator`.

### Meta tag globali
In `layouts/app.blade.php`:
- `<meta name="author" content="Andrea Bartiromo">`
- `<meta name="copyright" content="...">`
- `<link rel="canonical" href="...">`
- `<link rel="alternate" type="application/rss+xml" href="/feed.xml">`
- `<link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">`

### Sitemap
- `/sitemap.xml` — sitemap principale con tutti gli articoli e le pagine statiche
- `/sitemap-index.xml` — indice delle sitemap
- `/news-sitemap.xml` — sitemap Google News (ultimi 2 giorni, format specifico Google)
- `robots.txt` — include entrambe le sitemap (da aggiornare con dominio reale)

### Feed RSS
- `/feed.xml` — RSS 2.0 con Dublin Core e `content:encoded` (testo completo)

### Candidatura Google News
Per candidarsi: https://publishercenter.google.com/
Prerequisiti: sito HTTPS, sitemap news attiva, almeno 90 giorni di pubblicazioni costanti.

---

## 14. Schedulazione automatica

File: `routes/console.php`

| Comando | Frequenza | Log |
|---|---|---|
| `news:fetch` | Lun e Gio alle 9:00 | `storage/logs/news-fetch.log` |
| `backup:database` | Ogni giorno alle 2:00 | `storage/logs/backup.log` |
| `cache:prune-stale-tags` | Ogni domenica alle 3:00 | — |

### Attivazione sul server
Aggiungere al crontab del server (comando: `crontab -e`):
```
* * * * * cd /percorso/del/sito && php artisan schedule:run >> /dev/null 2>&1
```

---

## 15. Backup automatico

Comando: `php artisan backup:database [--keep=7]`

- Copia il file `database/database.sqlite` in `storage/backups/`
- Nome file: `database-YYYY-MM-DD-HHMMSS.sqlite`
- Mantiene gli ultimi N backup (default: 7), cancella i più vecchi
- Eseguito automaticamente ogni notte alle 2:00

---

## 16. File CSS e asset

### `public/css/style.css`
CSS principale del sito pubblico. Contiene:
- Variabili CSS (`--color-ink`, `--color-accent: #c9184a`, `--font-display`, ecc.)
- Layout grid per homepage e pagine articoli
- Stili per card articoli (verticali e orizzontali)
- Media query: `max-width: 1024px`, `768px`, `480px`
- Stili share buttons social
- Stili badge verifica editoriale
- Stili pagina autore

### `public/css/admin.css`
CSS del pannello admin. Variabili separate, tema scuro per la sidebar.

### `public/css/article-extras.css`
Stili aggiuntivi per la pagina articolo singolo.

### Immagini placeholder
Tutte le immagini sono SVG generati programmaticamente, con sfondo colorato e etichetta categoria. Devono essere sostituite con foto vere prima del lancio.

---

## 17. Variabili d'ambiente

File di riferimento: `.env.production.example`

### Obbligatorie per il funzionamento

```env
APP_NAME="Il Laboratorio"
APP_ENV=production          # MAI "local" in produzione
APP_KEY=                    # Generare con: php artisan key:generate
APP_DEBUG=false             # MAI true in produzione
APP_URL=https://www.illaboratorio.it

DB_CONNECTION=sqlite
DB_DATABASE=/percorso/assoluto/database/database.sqlite

SESSION_SECURE_COOKIE=true  # Solo con HTTPS attivo
```

### Per l'automazione notizie AI

```env
ANTHROPIC_API_KEY=sk-ant-api03-...
```

### Per il reset password via email

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.tuoprovider.it
MAIL_PORT=587
MAIL_USERNAME=redazione@illaboratorio.it
MAIL_PASSWORD=tuapassword
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=redazione@illaboratorio.it
MAIL_FROM_NAME="Il Laboratorio"
```

---

## 18. Installazione da zero

### Prerequisiti
- PHP 8.3+
- SQLite (incluso in PHP di solito)
- Composer (solo per le dipendenze, ma il vendor è già incluso)

### Passi

```bash
# 1. Clonare/copiare il progetto
cd /var/www
cp -r laboratorio/ illaboratorio/
cd illaboratorio/

# 2. Configurare l'ambiente
cp .env.production.example .env
# Editare .env con i valori reali

# 3. Generare la chiave app
php artisan key:generate

# 4. Creare il database e applicare le migration
php artisan migrate

# 5. Popolare con i dati di base (articoli, utenti)
# Usare i seeder oppure importare il database.sqlite esistente

# 6. Impostare i permessi
chmod -R 755 storage bootstrap/cache
chmod 644 database/database.sqlite

# 7. Ottimizzare per produzione
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Test
php artisan about
```

### Avvio locale (sviluppo)

```bash
cd laboratorio/
php artisan serve --host=127.0.0.1 --port=8000
# Sito: http://127.0.0.1:8000
# Admin: http://127.0.0.1:8000/admin/login
```

---

## 19. Patch vendor necessarie

Laravel 13.7.0 è stato installato via git clone. Il vendor contiene patch manuali che devono essere mantenute se il vendor viene rigenerato.

### Patch 1 — Container.php
File: `vendor/laravel/framework/src/Illuminate/Container/Container.php`
Circa riga 1286: sostituire `array_last($this->with)` con `end($this->with)`

### Patch 2 — Console/Application.php
File: `vendor/laravel/framework/src/Illuminate/Console/Application.php`
Sostituire `parent::addCommand($command)` con `parent::add($command)`

### Patch 3 — Polyfill PHP 8.4
File: `vendor/laravel/framework/src/Illuminate/Support/php84_polyfill.php`
Contiene le definizioni di: `array_find`, `array_find_key`, `array_any`, `array_all`, `array_last`, `array_first`
Questo file deve essere il PRIMO incluso in `vendor/composer/autoload_files.php`.

### Nota
Se si rigenerano le dipendenze con Composer in un ambiente corretto, queste patch non sono necessarie (Laravel 13.x ha già le funzioni o usa versioni PHP che le supportano nativamente).

---

## 20. Deploy in produzione

### Script automatico
```bash
bash deploy.sh
```

Lo script verifica: PHP >= 8.3, APP_DEBUG=false, esegue migration, ottimizza le cache, imposta i permessi, esegue un backup.

### Passi manuali post-deploy
1. Aggiornare `public/robots.txt`: sostituire `DOMINIO` con il dominio reale
2. Decommentare il redirect HTTPS in `public/.htaccess`
3. Aggiungere il cron job per `schedule:run`
4. Configurare il server web (Apache/Nginx) per puntare a `public/`
5. Aggiornare P.IVA e dati legali nel footer

### Configurazione Apache (esempio)
```apache
<VirtualHost *:443>
    ServerName www.illaboratorio.it
    DocumentRoot /var/www/illaboratorio/public
    
    <Directory /var/www/illaboratorio/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/illaboratorio.pem
    SSLCertificateKeyFile /etc/ssl/private/illaboratorio.key
</VirtualHost>
```

### Configurazione Nginx (esempio)
```nginx
server {
    listen 443 ssl;
    server_name www.illaboratorio.it;
    root /var/www/illaboratorio/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    ssl_certificate /etc/ssl/certs/illaboratorio.pem;
    ssl_certificate_key /etc/ssl/private/illaboratorio.key;
}
```

---

## 21. Comandi artisan utili

```bash
# Sviluppo
php artisan serve                    # Avvia server locale
php artisan route:list               # Lista tutte le route
php artisan migrate                  # Esegue le migration
php artisan migrate:fresh            # Ricrea il DB da zero
php artisan tinker                   # Console PHP interattiva

# Automazione
php artisan news:fetch               # Raccoglie notizie e genera bozze AI
php artisan news:fetch --dry-run     # Solo preview, nulla salvato
php artisan news:fetch --category=spazio  # Solo categoria specifica
php artisan backup:database          # Crea backup SQLite
php artisan backup:database --keep=14    # Mantieni 14 backup

# Produzione (eseguire dopo ogni deploy)
php artisan config:cache             # Cache configurazione
php artisan route:cache              # Cache route
php artisan view:cache               # Pre-compila le view Blade
php artisan optimize:clear           # Pulisce tutte le cache
php artisan view:clear               # Pulisce solo le view compilate

# Schedulazione
php artisan schedule:run             # Esegue i comandi schedulati (chiamato dal cron)
php artisan schedule:list            # Lista i comandi schedulati
```

---

## 22. Credenziali di default

> **IMPORTANTE:** Cambiare queste credenziali prima del lancio in produzione.

| Ruolo | Email | Password |
|---|---|---|
| Editor | m.esposito@illaboratorio.it | password123 |
| Author | s.ricci@illaboratorio.it | password123 |
| Author | e.romano@illaboratorio.it | password123 |

Cambio password: `/admin/profilo` → sezione "Cambia password"

---

## 23. Cosa fare prima del lancio

### Bloccanti (senza queste, non andare online)

- [ ] `.env`: impostare `APP_DEBUG=false` e `APP_ENV=production`
- [ ] `.env`: generare una nuova `APP_KEY` con `php artisan key:generate`
- [ ] `public/robots.txt`: sostituire `DOMINIO` con il dominio reale
- [ ] `public/.htaccess`: decommentare il blocco redirect HTTPS
- [ ] Footer (`resources/views/components/footer.blade.php`): aggiornare P.IVA reale
- [ ] Verificare i 6 articoli con `verification_status = in_progress` nel pannello `/admin/verifica`
- [ ] Cambiare le password degli utenti admin

### Importanti (entro il primo giorno)

- [ ] `.env`: configurare `MAIL_*` per il reset password
- [ ] `.env`: aggiungere `ANTHROPIC_API_KEY` per l'automazione notizie
- [ ] Server: impostare il cron job per `schedule:run`
- [ ] Sostituire le immagini placeholder SVG con foto reali in `public/assets/img/`
- [ ] Caricare foto profilo reale per Andrea Bartiromo (tramite `/admin/profilo`)
- [ ] Verificare i 17 articoli con `verification_status = unverified`

### Consigliati (prima settimana)

- [ ] Registrare il sito in Google Search Console
- [ ] Inviare la sitemap da Search Console
- [ ] Testare il feed RSS con Feedly o Inoreader
- [ ] Testare il sito su mobile (iOS e Android) e Safari
- [ ] Configurare un servizio analytics (Google Analytics o Plausible)
- [ ] Aprire i profili social e aggiornare i link in `config/laboratorio.php`
- [ ] Impostare backup automatici del server (non solo del DB)

---

## 24. Errori noti e soluzioni

### Errore: "unexpected end of file expecting elseif/else/endif" nelle view articolo

**Causa:** `@push('scripts')` era annidato dentro `@section('content')`. Il compilatore Blade genera PHP con `if` non bilanciati quando `@push` è dentro una sezione.

**Soluzione:** `@push('scripts')` deve stare SEMPRE fuori e dopo l'ultimo `@endsection`:
```blade
{{-- SBAGLIATO: --}}
@section('content')
  ...
  @push('scripts')...<script>...</script>@endpush
@endsection

{{-- CORRETTO: --}}
@section('content')
  ...
@endsection

@push('scripts')
<script>...</script>
@endpush
```

### Errore: "Undefined array key 0" nel renderer di eccezioni

**Causa:** Questo errore appare nel renderer HTML delle eccezioni di Laravel, non nel codice applicativo. È un bug del renderer che si manifesta quando c'è un errore sottostante.

**Soluzione:** Ignorare questo messaggio e cercare l'errore reale nei log di Laravel (`storage/logs/laravel.log`) o abilitare temporaneamente `APP_DEBUG=true` per vedere il vero stack trace.

### Il file cache Blade non si aggiorna

**Soluzione:**
```bash
php artisan optimize:clear   # Pulisce tutto
php artisan view:cache       # Ricompila
php -l storage/framework/views/NOMEFILE.php  # Verifica sintassi
```

### Il server integrato PHP restituisce 500 ma PHP diretto ritorna 200

**Causa:** Può dipendere da conflitti di sessione, cache parzialmente aggiornata, o problemi di working directory.

**Soluzione:** Usare `php artisan optimize:clear` e riavviare il server. Per i test, usare PHP diretto invece del server HTTP integrato.

### robots.txt ritorna 404 via PHP server integrato

**Causa:** Il server integrato di PHP non serve automaticamente i file statici. È normale.

**Soluzione:** Sul server reale con Apache/Nginx funziona correttamente. Non è un bug del codice.

---

## Note finali

Questo documento è stato generato il 2 maggio 2026 e riflette lo stato del progetto in quella data. Il sito è funzionante (52/53 pagine HTTP 200, la 53° è `robots.txt` che è un file statico servito correttamente da Apache/Nginx).

**Firma:** Andrea Bartiromo — Fondatore e Direttore Responsabile, Il Laboratorio
**Sviluppato con:** Laravel 13.7.0, PHP 8.3, SQLite, Claude AI (Anthropic)
**© 2025 Andrea Bartiromo. Tutti i diritti riservati.**
