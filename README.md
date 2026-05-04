# Il Laboratorio

**Rivista italiana di divulgazione scientifica e tecnologica**

> Scienza e tecnologia raccontate con rigore e accessibilità, per un pubblico colto e curioso.

---

## Il Progetto

*Il Laboratorio* è un progetto editoriale ideato e sviluppato da **Andrea Bartiromo**.

La rivista nasce dalla convinzione che la divulgazione scientifica italiana meriti
una voce editoriale forte, rigorosa e fondata su fonti verificabili. Ogni articolo
pubblicato risponde a un principio semplice: ogni informazione deve essere
tracciabile sulla sua fonte primaria.

## Stack tecnico

| Componente | Tecnologia |
|---|---|
| Framework | Laravel 13.7 |
| PHP | 8.3 |
| Database | SQLite |
| Template | Blade |
| Automazione | Claude AI (Anthropic) |
| Schema.org | NewsArticle + NewsMediaOrganization |
| Feed | RSS 2.0 + Dublin Core |

## Struttura del progetto

```
laboratorio/
├── app/
│   ├── Console/Commands/   # Automazione notizie (news:fetch)
│   ├── Http/Controllers/   # Admin e pubblico
│   ├── Models/             # Article, User, Newsletter, ecc.
│   └── Http/Middleware/    # SecurityHeaders, Auth
├── resources/views/
│   ├── admin/              # CMS: dashboard, articoli, verifica
│   ├── components/         # Header, footer, sidebar, ticker
│   └── layouts/            # App e Admin
├── public/
│   ├── css/                # Stylesheet
│   └── assets/             # Immagini e icone
└── database/
    └── database.sqlite     # Database principale
```

## Avvio locale

```bash
cd laboratorio
php artisan serve --host=127.0.0.1 --port=8000
```

Admin: `http://127.0.0.1:8000/admin/login`

Credenziali di default:
- `m.esposito@illaboratorio.it` / `password123`

## Automazione notizie

```bash
# Raccoglie da 16 feed RSS e genera bozze con AI
php artisan news:fetch

# Solo preview senza salvare
php artisan news:fetch --dry-run

# Solo una categoria
php artisan news:fetch --category=spazio
```

Richiede `ANTHROPIC_API_KEY=sk-ant-...` nel file `.env`.

## Prima del lancio

1. Copiare `.env.production.example` in `.env` sul server
2. `php artisan key:generate`
3. `php artisan config:cache && php artisan route:cache`
4. Aggiornare `robots.txt` con il dominio reale
5. Decommentare redirect HTTPS in `.htaccess`
6. Verificare i 6 articoli "in verifica" sulle fonti primarie
7. Aggiornare P.IVA e dati legali nel footer

---

**© 2025 Andrea Bartiromo — Il Laboratorio. Tutti i diritti riservati.**
