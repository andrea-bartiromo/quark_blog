# Quark Blog

**CMS editoriale Laravel per la divulgazione scientifica e tecnologica**

## Descrizione

Quark Blog è un CMS editoriale moderno costruito su Laravel, pensato per far funzionare una piccola redazione digitale dalla scrittura alla pubblicazione.

Il progetto include:

- **Articoli** organizzati in **categorie** gestibili da pannello, con stato di verifica delle fonti.
- Un'area **Redazione** dove i collaboratori scrivono e modificano i propri articoli, che entrano automaticamente in revisione prima della pubblicazione.
- Un'area di **Amministrazione** completa per editor: articoli, categorie, commenti, collaboratori, revisione editoriale, statistiche e log delle attività.
- **Newsletter** con iscrizione pubblica, invio settimanale automatizzato e tracciamento di apertura/click.
- Ottimizzazione **SEO**: sitemap XML, sitemap news, feed RSS, dati strutturati per gli articoli.
- **Turing**, una sezione editoriale speciale dedicata ad Alan Turing, con contenuti configurabili da admin.
- Una libreria **media** centralizzata per la gestione delle immagini.
- Un **workflow editoriale** con stati di verifica delle fonti e revisione degli articoli prima della pubblicazione.

## Screenshots

(Home)

(Admin)

(Articolo)

(Turing)

## Stack tecnologico

- **Laravel 13** — framework backend
- **PHP 8.3+**
- **Blade** — motore di template
- **SQLite** — database di default (usato in sviluppo, test e CI)
- **Vite** + **Tailwind CSS 4** — pipeline asset configurata nel progetto; le pagine pubbliche e l'admin usano però CSS scritto a mano in `public/css/`, non componenti Tailwind
- **GD** — estensione PHP opzionale per il ridimensionamento e la compressione delle immagini di copertina
- **Anthropic Claude API** — genera bozze di articoli a partire da feed RSS (comando `news:fetch`)
- **GitHub Actions** — CI che esegue la suite di test automatici ad ogni push e pull request su `main`

## Requisiti

- PHP 8.3 o superiore
- Composer 2.x
- Node.js 18+ e npm
- SQLite (driver `pdo_sqlite`, incluso di norma nelle distribuzioni PHP)
- Estensioni PHP consigliate: `pdo`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `curl`, `dom`, `libxml`, `zip`, `pcntl`
- GD (opzionale, ma consigliata per il ridimensionamento/compressione automatica delle immagini)

## Installazione

```bash
# 1. Clona il repository
git clone https://github.com/andrea-bartiromo/quark_blog.git
cd quark_blog

# 2. Installa le dipendenze PHP e JS
composer install
npm install

# 3. Configura l'ambiente
cp .env.example .env
php artisan key:generate

# 4. Crea il database SQLite
touch database/database.sqlite

# 5. Esegui le migration e popola il database con dati di sviluppo
php artisan migrate --seed

# 6. Compila gli asset (in un terminale separato, opzionale in sviluppo)
npm run dev

# 7. Avvia il server di sviluppo
php artisan serve
```

Il sito sarà raggiungibile su `http://127.0.0.1:8000`.

In alternativa, `composer run dev` avvia in un solo comando server, queue worker, log e Vite in parallelo.

## Account demo

Il seeder (`database/seeders/DatabaseSeeder.php`) crea automaticamente questi account, **destinati esclusivamente allo sviluppo locale**:

| Ruolo | Area | Email | Password |
|---|---|---|---|
| Editor | Amministrazione | `m.esposito@illaboratorio.it` | `password123` |
| Autore | Redazione | `s.ricci@illaboratorio.it` | `password123` |
| Autore | Redazione | `e.romano@illaboratorio.it` | `password123` |

Gli URL di login di Amministrazione e Redazione sono volutamente non ovvi (definiti in `routes/web.php`): usa `php artisan route:list --name=login` per trovarli in locale. Questi account e queste credenziali non devono mai essere usati su un ambiente esposto pubblicamente.

## Architettura

- **Amministrazione** — pannello riservato a editor/admin: dashboard, articoli, categorie, commenti, newsletter, media, suggerimenti generati dall'AI, revisione editoriale, gestione collaboratori, statistiche, pubblicità e registro attività.
- **Redazione** — area riservata ai collaboratori (autori): scrittura e modifica dei propri articoli, che vengono inviati automaticamente in revisione all'editor.
- **Frontend** — homepage, elenco articoli, categorie, pagina articolo, ricerca, pagina autore e pagine statiche (chi siamo, contatti, privacy, ecc.).
- **Turing** — sezione editoriale speciale con hero, timeline ed eredità storica, il cui contenuto è configurabile da Amministrazione e persistito tramite il modello `SpecialPage`.
- **Media** — libreria immagini centralizzata in Amministrazione, con upload dedicato.
- **Newsletter** — iscrizione pubblica, invio settimanale automatizzato (comando `newsletter:send`) tramite coda, tracciamento apertura e click.
- **SEO** — sitemap XML, sitemap news, feed RSS e dati strutturati per gli articoli.
- **ImageService** (`app/Services/ImageService.php`) — servizio applicativo che centralizza naming, upload, ridimensionamento e compressione delle immagini di copertina, condiviso tra i controller di Amministrazione e Redazione.
- **ActivityLog** (`app/Models/ActivityLog.php`) — registra le azioni amministrative rilevanti (creazione/eliminazione articoli, gestione collaboratori, ecc.).

## Gestione immagini

L'upload delle immagini di copertina (articoli e categorie) passa attraverso `ImageService`, che centralizza il naming del file, il salvataggio su disco, il ridimensionamento e la compressione tramite l'estensione GD, quando disponibile. Ogni controller applica i propri parametri (larghezza massima, qualità), ma la logica tecnica è condivisa e non duplicata.

Ogni articolo può avere, oltre all'immagine di copertina, metadati editoriali dedicati e facoltativi:

- **Testo alternativo** (`cover_alt`) — se non impostato, viene usato automaticamente il titolo dell'articolo.
- **Didascalia** (`cover_caption`)
- **Credito immagine** (`cover_credit`)
- **Fonte** (`cover_source`) ed **URL della fonte** (`cover_source_url`)
- **Licenza** (`cover_license`)

Quando un articolo o un autore non hanno un'immagine di copertina, il frontend usa due placeholder SVG generati internamente e privi di dipendenze esterne: `placeholder-1.svg` per le card (articoli, categorie, autore) e `hero-placeholder.svg` come fallback per l'immagine Open Graph.

## Activity Log

Le azioni amministrative rilevanti (creazione, modifica ed eliminazione di articoli e collaboratori, ecc.) vengono registrate tramite il modello `ActivityLog`. La tabella `activity_log` è creata da una migration Laravel ufficiale ed è quindi presente automaticamente dopo `php artisan migrate`, senza bisogno di script esterni.

## Sicurezza

- Il file `.env` non è mai versionato: si parte da `.env.example` (privo di segreti) e si genera una `APP_KEY` locale con `php artisan key:generate`.
- L'invio email supporta SMTP (es. Gmail con una App Password dedicata, mai la password dell'account Google).
- Header di sicurezza HTTP applicati globalmente tramite middleware dedicato.
- Rate limiting sui form pubblici (newsletter, commenti, contatti) e sui tentativi di login.
- Le linee guida complete su gestione di segreti, chiavi e credenziali sono documentate in [`SECURITY.md`](SECURITY.md).

## Test

Il progetto include una suite di test automatici (Feature e Unit), eseguibile con:

```bash
php artisan test
```

I test usano un database SQLite dedicato (configurato in `phpunit.xml`, isolato da quello di sviluppo) e coprono, tra le altre cose, i metadati delle immagini di copertina e il registro delle attività. La stessa suite viene eseguita automaticamente da GitHub Actions ad ogni push e pull request su `main`.

## Roadmap

**Completato**

- Sicurezza di base (header HTTP, gestione `.env`, `SECURITY.md`)
- Sezione editoriale Turing
- `ImageService` centralizzato per l'upload delle immagini
- Placeholder per le immagini mancanti
- Metadati editoriali della copertina (alt, didascalia, credito, fonte, licenza)
- Migration ufficiale per l'Activity Log

**In corso**

- Aggiornamento e allineamento della documentazione tecnica
- Estensione della copertura dei test automatici

**Futuro**

- Demo pubblica
- Miglioramento della responsività
- Libreria media più evoluta
- Sostituzione delle immagini placeholder con foto originali
- Ottimizzazione delle performance

## Licenza

Progetto proprietario (vedi `composer.json`). Tutti i diritti riservati.

**Autore:** Andrea Bartiromo — [github.com/andrea-bartiromo](https://github.com/andrea-bartiromo)
