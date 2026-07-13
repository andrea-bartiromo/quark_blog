#  Quark Blog

**Blog di divulgazione scientifica e tecnologica sviluppata in Laravel**

Quark blog è un progetto editoriale ideato e sviluppato da **Andrea Bartiromo**.  
Nasce come blog di divulgazione scientifica e tecnologica, con l'obiettivo di unire sviluppo web, gestione editoriale, automazione dei contenuti e attenzione alla qualità delle fonti.

> Scienza e tecnologia raccontate con rigore, accessibilità e struttura editoriale.


## Obiettivo del progetto

Il progetto nasce dall'idea di costruire una piattaforma moderna, non un semplice blog statico.

L'obiettivo è simulare il funzionamento di una piccola redazione digitale con:

- pubblicazione di articoli;
- area amministrativa;
- gestione bozze e contenuti in verifica;
- automazione nella raccolta di notizie;
- attenzione SEO;
- feed RSS;
- metadati strutturati Schema.org;
- principio di tracciabilità delle fonti.

Ogni contenuto pubblicato dovrebbe poter essere collegato a fonti verificabili e mantenere una struttura chiara per il lettore e per i motori di ricerca.

## Funzionalità principali

### CMS editoriale

- Dashboard amministrativa.
- Gestione articoli.
- Area contenuti in verifica.
- Componenti Blade riutilizzabili.
- Layout pubblico e layout admin separati.

### Automazione notizie

- Comando Artisan `news:fetch`.
- Raccolta da feed RSS.
- Generazione bozze con supporto AI.
- Modalità dry-run per preview senza salvataggio.
- Filtro per categoria.

### SEO e dati strutturati

- Markup Schema.org.
- Supporto `NewsArticle`.
- Supporto `NewsMediaOrganization`.
- Feed RSS 2.0.
- Dublin Core.
- Struttura pensata per contenuti editoriali indicizzabili.

### Sicurezza e produzione

- Middleware dedicato per security headers.
- File `.env.production.example`.
- Indicazioni per cache Laravel.
- Checklist pre-lancio.


## Stack tecnico

| Componente | Tecnologia |

| Framework | Laravel |
| Linguaggio | PHP |
| Database | SQLite |
| Template | Blade |
| Automazione | Comandi Artisan |
| AI | Claude / Anthropic API |
| SEO | Schema.org |
| Feed | RSS 2.0 + Dublin Core |



## Architettura del progetto

text
quark-blog/
├── app/
│   ├── Console/Commands/   # Automazione notizie: news:fetch
│   ├── Http/Controllers/   # Controller area pubblica e admin
│   ├── Http/Middleware/    # SecurityHeaders, Auth
│   └── Models/             # Article, User, Newsletter, ecc.
│
├── resources/views/
│   ├── admin/              # CMS: dashboard, articoli, verifica
│   ├── components/         # Header, footer, sidebar, ticker
│   └── layouts/            # Layout pubblico e admin
│
├── public/
│   ├── css/                # Stylesheet
│   └── assets/             # Immagini e icone
│
└── database/
    └── database.sqlite     # Database principale
```

---

## Flusso editoriale previsto

```text
Feed RSS / Fonti esterne
        ↓
Comando news:fetch
        ↓
Bozze generate
        ↓
Verifica editoriale
        ↓
Pubblicazione articolo
        ↓
SEO + RSS + Schema.org
```

---

## Comandi principali

Avvio locale:

```bash
cd quark-blog
php artisan serve --host=127.0.0.1 --port=8000
```

Accesso admin:

```text
http://127.0.0.1:8000/admin/login
```

Esecuzione automazione notizie:

```bash
php artisan news:fetch
```

Preview senza salvare:

```bash
php artisan news:fetch --dry-run
```

Solo una categoria:

```bash
php artisan news:fetch --category=spazio
```

---

## Variabili ambiente

Copiare il file di esempio adatto all'ambiente e compilare i valori reali solo nel file `.env` locale o sul server:

```bash
cp .env.example .env
php artisan key:generate
```

`APP_KEY` deve restare vuota nei file versionati e va generata nel `.env` reale con `php artisan key:generate`.

Per inviare email con Gmail SMTP usare una App Password Google, mai la password dell'account Google:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@example.com
```

Per usare l'automazione AI è necessario configurare nel file `.env` reale:

```env
ANTHROPIC_API_KEY=your-anthropic-api-key
```

Nessun segreto deve essere versionato: non caricare mai nel repository `APP_KEY`, token Anthropic, token GitHub, password SMTP, password database, webhook o API key reali.

---

## Prima del lancio

Checklist prevista prima di un eventuale deploy:

1. Copiare `.env.production.example` in `.env` sul server.
2. Eseguire `php artisan key:generate`.
3. Eseguire `php artisan config:cache && php artisan route:cache`.
4. Aggiornare `robots.txt` con il dominio reale.
5. Verificare redirect HTTPS.
6. Controllare gli articoli in verifica sulle fonti primarie.
7. Aggiornare dati legali e informazioni editoriali nel footer.

---

## Possibili sviluppi futuri

- Aggiungere screenshot della dashboard admin.
- Aggiungere screenshot della homepage editoriale.
- Migliorare il workflow di verifica fonti.
- Aggiungere gestione utenti e ruoli editoriali.
- Aggiungere test automatici.
- Migliorare la gestione categorie/tag.
- Preparare una demo pubblica.

---

## Stato del progetto

Il progetto è una piattaforma editoriale Laravel sviluppata a scopo personale/formativo, con un'impostazione vicina a un CMS redazionale.

---

## Autore

**Andrea Bartiromo**  
GitHub: [andrea-bartiromo](https://github.com/andrea-bartiromo)
