# Article Archive / Discovery Audit

Missione 17 — `DESIGN COMPLETE — IMPLEMENTATION DEFERRED` per ownership delle superfici pubbliche.

## Stato repository osservato

Le entry point pubbliche reali sono:

- `/` home;
- `/notizie` archive;
- `/categoria/{slug}`;
- `/ricerca`;
- `/autore/{user}`;
- `/articolo/{slug}`;
- Percorsi pubblici tramite il dominio ContentCluster già esistente.

Le categorie hanno già paginazione server-side e gli articoli pubblicabili sono filtrati da `Article::published()` nelle superfici principali.

## Perché niente runtime in questa missione

Le superfici da modificare per qualunque miglioramento concreto sono contemporaneamente interessate da lavoro aperto:

- #254: related / ArticleController;
- #258: categorie, ricerca e navigation DB-first;
- #273: scheduled visibility audit e Admin Calendar;
- #279/#280 non sono su main e non possono essere assunte come dipendenze.

Questa missione quindi non introduce un nuovo archive, una mega-sitemap HTML o un secondo motore di discovery.

## Audit deterministico futuro

L'audit runtime dovrà costruire un grafo **solo di link pubblici reali** e distinguere almeno:

1. `ARCHIVE_ENTRY` — home/notizie/categoria/autore/Percorso;
2. `EDITORIAL_BODY_LINK` — link nel body;
3. `STRUCTURAL_NAVIGATION` — Percorso prev/next;
4. `RECOMMENDATION` — related / Continua da qui.

Non si deve sommare tutto in un unico score.

Per ogni articolo `Article::published()` il report dovrà esporre:

- quali entry point lo rendono raggiungibile;
- incoming body links reali;
- appartenenza a Percorsi attivi;
- presenza in category/archive pagination;
- eventuale assenza da ogni entry point strutturale;
- profondità minima misurabile dal catalogo, senza contare search query inventate come un click garantito.

## Regole di profondità

La profondità può essere dichiarata soltanto quando la sequenza di pagine è deterministica. Esempi:

- home -> articolo: 1 quando realmente presente nel modulo home;
- home -> categoria -> pagina N -> articolo: profondità calcolabile dalla posizione nella paginazione;
- search -> articolo: non viene considerata profondità garantita perché richiede una query utente non deterministica;
- recommendation dinamiche: vanno misurate sul comportamento reale del servizio, non assunte.

## Output proposto

Per articolo:

- `direct_entry_points`;
- `body_incoming_count`;
- `active_path_count`;
- `category_page_number`;
- `archive_page_number`;
- `minimum_deterministic_depth` oppure `UNKNOWN`;
- `discovery_risks[]` con ragioni testuali.

Possibili risk code, senza soglie magiche:

- `NO_STRUCTURAL_ENTRY_POINT`;
- `NO_BODY_INCOMING_LINKS`;
- `CATEGORY_NOT_NAVIGABLE`;
- `ONLY_DEEP_ARCHIVE_PAGINATION` soltanto dopo che una soglia editoriale verrà formalizzata;
- `NO_ACTIVE_PATH` come informazione, non errore automatico.

## Test contract futuro

- articolo pubblicato raggiungibile dalla categoria;
- paginazione oltre pagina 1;
- categoria inattiva/non navigabile;
- articolo con solo URL diretto;
- body incoming link;
- Percorso attivo;
- related/Continua da qui classificati separatamente;
- draft/review/scheduled mai conteggiati come pubblico;
- query count bounded sul catalogo.

## Safety

Docs-only. Nessuna route, controller, view, model, migration o dato modificato. Nessun merge/deploy.
