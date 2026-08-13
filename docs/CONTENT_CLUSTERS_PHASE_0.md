# Content Clusters / Percorsi — Phase 0

## Goal

Evolvere Kairus da una raccolta di articoli indipendenti a percorsi editoriali strutturati e navigabili, con un pillar esplicito, ordine manuale e collegamenti coerenti tra articoli, senza introdurre account/progresso utente e senza duplicare il contenuto degli articoli nelle landing dei percorsi.

Phase 0 è design e inventory. Non introduce migration, backfill o feature pubblica.

## Repository inventory

Il modello `Article` esistente usa slug, categoria, stati `draft/review/scheduled/published`, `published_at`, autore, SEO/canonical/robots/Open Graph/Twitter e Internal Linking tramite `ArticleLinkSuggestion`. Lo scope `published()` richiede stato published e `published_at <= now()`.

Le route pubbliche esistenti usano `/articolo/{slug}` e `/categoria/{slug}`. L'admin articoli è già protetto da `auth` + `editor` e dispone di CRUD, scheduling e Internal Linking.

La sitemap principale è generata da `SeoController`: include pagine statiche, categorie e soli articoli pubblicati. I Percorsi devono integrarsi nella stessa sitemap invece di crearne una separata senza necessità.

## Non-goals

- Nessun account o tracking del progresso del lettore.
- Nessuna sostituzione delle categorie.
- Nessuna sostituzione degli anchor link editoriali nel corpo.
- Nessun auto-clustering AI in Phase 1.
- Nessun fuzzy backfill in produzione.
- Nessuna pubblicazione di articoli scheduled prima del loro `published_at`.
- Nessuna migration o backfill in Phase 0.

## Terminologia

Nome prodotto/UI: **Percorso**.

Nome tecnico consigliato: `ContentCluster`, per evitare collisioni concettuali con route/path HTTP e mantenere esplicito che l'entità è un cluster editoriale.

## Data model

### `content_clusters`

Campi proposti:

- `id` BIGINT UNSIGNED PK;
- `name` VARCHAR(160);
- `slug` VARCHAR(180) UNIQUE;
- `description` TEXT nullable — introduzione pubblica breve e originale;
- `short_description` VARCHAR(320) nullable — card/hub;
- `cover_image` VARCHAR(2048) nullable, coerente con l'attuale media model solo dopo verifica in implementazione;
- `seo_title` VARCHAR(255) nullable;
- `seo_description` VARCHAR(320) nullable;
- `pillar_article_id` BIGINT UNSIGNED nullable;
- `is_active` BOOLEAN default false;
- `sort_order` INT UNSIGNED default 0;
- timestamps.

`pillar_article_id` è preferibile a un flag nel pivot: un Percorso ha al massimo un punto di partenza editoriale e questa cardinalità è più semplice da validare, interrogare e mostrare. FK `SET NULL` alla cancellazione dell'articolo.

### `article_content_cluster`

Relazione many-to-many:

- `content_cluster_id` BIGINT UNSIGNED;
- `article_id` BIGINT UNSIGNED;
- `position` INT UNSIGNED;
- `is_primary` BOOLEAN default false;
- timestamps opzionali solo se servono audit/editorial UX.

Vincoli/indici:

- PK o UNIQUE (`content_cluster_id`, `article_id`);
- INDEX (`content_cluster_id`, `position`);
- INDEX (`article_id`, `is_primary`);
- FK cascade sul pivot quando cluster o articolo vengono eliminati.

La relazione deve essere many-to-many perché un articolo può essere semanticamente utile in più percorsi: per esempio relatività o diagnostica AI possono appartenere a un percorso principale e a uno secondario senza duplicare l'articolo. La nozione di percorso principale serve invece per scegliere quale box mostrare con priorità nell'articolo.

### Primary cluster invariant

`is_primary` sul pivot consente un primary cluster opzionale. L'invariante applicativo deve essere: massimo un pivot `is_primary=true` per articolo.

MariaDB non offre un partial unique index portabile equivalente a `WHERE is_primary = 1`; quindi l'invariante va applicato transazionalmente nel service/admin layer e coperto da test. Evitare workaround DB-specific fragili in Phase 1.

### Ordering

`position` vive sul pivot perché l'ordine è proprietà della relazione articolo↔Percorso, non dell'articolo. Deve essere manuale e deterministico, non derivato dalla data di pubblicazione.

In salvataggio, normalizzare le posizioni a una sequenza stabile (10,20,30... oppure 1..N) in una transazione. Per una prima UX, campi numerici ordinabili sono sufficienti; drag-and-drop può essere aggiunto solo se resta accessibile via tastiera.

## Publication contract

Un Percorso pubblico richiede `is_active=true`.

Le pagine pubbliche mostrano solo articoli che soddisfano lo stesso contratto di `Article::published()`: `status=published` e `published_at <= now()`.

Articoli draft/review/scheduled restano visibili soltanto in admin. Un Percorso attivo senza articoli pubblicati può essere escluso dalla sitemap e, preferibilmente, rispondere 404 finché non ha contenuto pubblico utile.

Il pillar pubblico deve anch'esso essere pubblicato. Se il `pillar_article_id` punta a un articolo non pubblico, la landing non deve esporlo e deve degradare alla lista ordinata degli articoli pubblicati.

## Admin UX

Nuova sezione `Admin → Percorsi` nello stesso gruppo protetto `auth` + `editor`.

### Index

Mostrare nome, stato active/inactive, numero articoli, pillar, sort order e azioni modifica.

### Create/Edit

Campi essenziali:

- nome;
- slug con generazione assistita ma modificabile;
- short description;
- description;
- cover;
- SEO title/description;
- active/inactive;
- pillar;
- elenco articoli associati;
- posizione manuale.

Il pillar deve essere selezionabile soltanto tra gli articoli associati al Percorso; il backend deve comunque validare l'invariante.

### Article form

Aggiungere una sezione compatta:

- Percorso principale;
- Percorsi aggiuntivi;
- posizione per ciascuna membership.

Non aggiungere un secondo flag Pillar nel form articolo: il pillar è proprietà del Percorso e si gestisce dalla schermata Percorso, evitando due fonti di verità.

## Public UX — `/percorsi`

Hub indicizzabile con H1 e introduzione breve. Mostra soltanto Percorsi attivi con contenuto pubblico.

Ogni card:

- nome;
- short description;
- cover se presente;
- numero di articoli attualmente pubblici;
- CTA `Esplora il percorso`;
- opzionale `Parti da qui: <pillar>` se il pillar è pubblico.

Nessuna percentuale di progresso personale.

## Public UX — `/percorsi/{slug}`

Struttura:

1. breadcrumb;
2. H1;
3. introduzione unica e breve;
4. blocco `Parti da qui` per il pillar pubblico;
5. lista editoriale ordinata degli articoli pubblicati;
6. eventuali Percorsi correlati solo quando esiste una relazione editoriale esplicita futura.

La landing deve riassumere, organizzare e navigare. Non deve riscrivere gli articoli né diventare un articolo duplicato.

Gli articoli scheduled non sono mostrati pubblicamente e non devono contribuire al conteggio pubblico.

## Article UX — `Continua il percorso`

Nel singolo articolo, dopo il contenuto principale o in una zona non invasiva prima dei related content, mostrare un box mobile-first per il primary cluster pubblico:

- nome Percorso;
- `3 di 7` calcolato sulla sequenza degli articoli attualmente pubblici;
- precedente;
- successivo;
- `Vedi tutto il percorso`;
- se l'articolo è pillar, etichetta `Parti da qui`.

Se non esiste primary cluster pubblico, si può scegliere il primo cluster pubblico per `sort_order` soltanto se questa fallback policy è documentata; preferenza: nessun box ambiguo.

## Internal Linking contract

I Percorsi non sostituiscono `ArticleLinkSuggestion` e non inseriscono automaticamente anchor nel body.

Contract futuro:

- pillar → satellites: il motore può aumentare la priorità di suggerimenti verso membri dello stesso Percorso;
- satellite → pillar: forte candidato quando semanticamente valido;
- satellite → next/previous: navigazione strutturale resa dal box Percorso, non necessariamente anchor nel body;
- tutte le regole temporali esistenti dell'Internal Linking restano autoritative: nessun link pubblico anticipato verso scheduled/draft/review.

Phase 1 deve prima esporre membership/ordine in modo queryable; l'integrazione col ranking del suggeritore può essere una PR separata.

## Initial IA path

Nome: `IA spiegata`.

Pillar consigliato: **Che cos'è un LLM e come funziona davvero?** È il fondamento concettuale più adatto per introdurre i successivi articoli su ChatGPT, allucinazioni e valutazione dell'intelligenza artificiale.

Ordine editoriale proposto:

1. Che cos'è un LLM e come funziona davvero? — pillar;
2. Come funziona davvero ChatGPT;
3. Perché l'intelligenza artificiale allucina?;
4. Il Test di Turing spiegato davvero;
5. Il Test di Turing ha ancora senso nel 2026?;
6. L'intelligenza artificiale fisica;
7. AI e diagnosi medica;
8. GPT-5 e futuro del lavoro.

`AI e diagnosi medica` e `GPT-5 e futuro del lavoro` sono applicazioni/impatti e possono in futuro diventare membri secondari di percorsi verticali. Restano nel percorso IA iniziale solo se la redazione vuole una chiusura applicativa dopo le basi.

## Initial Spazio path

Nome: `Spazio`.

Progressione narrativa proposta:

1. Perché il cielo è nero? — apertura intuitiva che introduce scala cosmica e propagazione della luce;
2. Guardare lontano = guardare indietro — tempo di viaggio della luce;
3. Come i telescopi vedono il passato — strumento/osservazione;
4. Relatività speciale — quadro fisico di spazio, tempo e luce;
5. Perché il Sole non esplode? — fisica stellare vicina;
6. Betelgeuse pillar/detail — evoluzione stellare e caso concreto;
7. Betelgeuse compagna — approfondimento del sistema.

Pillar consigliato: **Guardare lontano = guardare indietro** se l'articolo è sufficientemente introduttivo; alternativa **Betelgeuse pillar/detail** soltanto se l'obiettivo editoriale del Percorso è esplicitamente stelle/evoluzione stellare invece di astronomia generale.

Il contenuto su satellite italiano/clima va lasciato fuori dal percorso principale salvo legame editoriale forte con osservazione terrestre/spaziale; è candidato migliore per un futuro percorso clima/osservazione della Terra.

## SEO design

### `/percorsi`

Canonical self-referencing. Indicizzabile se esistono Percorsi pubblici.

### `/percorsi/{slug}`

- `seo_title` con fallback `<name> | Kairus`;
- `seo_description` con fallback alla short description;
- canonical self-referencing generato con route/url helpers coerenti con l'attuale HTTPS canonicalization;
- `robots=index,follow` soltanto per Percorso attivo con contenuto pubblico;
- Open Graph e Twitter con cover/fallback globale;
- breadcrumb Home → Percorsi → Nome Percorso.

Non usare la landing come canonical degli articoli: ogni articolo mantiene il proprio canonical.

## Structured data

Per una landing Percorso pubblica:

- `CollectionPage` per descrivere la pagina di raccolta;
- `ItemList` con gli articoli pubblici nell'ordine editoriale;
- `BreadcrumbList` per la navigazione gerarchica.

Non usare `Course`, `LearningResource` o altri tipi che implichino proprietà educative non dimostrate dal prodotto.

## Sitemap

Integrare i Percorsi attivi con contenuto pubblico nell'attuale `/sitemap.xml` generato da `SeoController`.

- `/percorsi` incluso come pagina statica quando la feature è pubblica;
- `/percorsi/{slug}` solo per cluster active con almeno un articolo pubblico;
- inactive/draft esclusi;
- nessuna nuova sitemap dedicata in Phase 1.

## Analytics

Eventi candidati, soltanto se l'architettura analytics esistente supporta naturalmente eventi custom senza introdurre un secondo stack:

- `path_view`;
- `path_article_click`;
- `path_next_click`;
- `path_previous_click`;
- `path_pillar_click`.

Payload minimo non sensibile: `path_slug`, `article_slug`, `position`, `source_surface`. Non implementare tracking nella PR data-model.

## Accessibility

- heading hierarchy coerente;
- card/link con focus visibile;
- prev/next con label testuali, non sole icone;
- ordering admin utilizzabile da tastiera;
- cover con alt appropriato o decorativa quando ridondante;
- nessun horizontal overflow a 390/768/1440;
- target touch adeguati;
- contrasto coerente col design system esistente.

## Migration plan — future only

PR implementativa futura:

1. creare `content_clusters` senza dati;
2. creare pivot `article_content_cluster` con unique membership e ordering index;
3. aggiungere FK pillar nullable `SET NULL` dopo che entrambe le tabelle esistono, oppure creare la FK in ordine compatibile con MariaDB;
4. nessun backfill fuzzy nella migration;
5. testare `migrate:fresh` e migration incrementale su MariaDB, non solo SQLite.

Prima del merge della migration va verificato il comportamento DDL MariaDB e l'ordine delle FK. La migration deve essere piccola e reversibile; il `down()` elimina soltanto le nuove strutture della feature.

## Backfill plan — future/manual-reviewable

Backfill separato dalla migration schema.

Input consigliato: manifest versionato con slug esatti degli articoli, per esempio:

```php
'ia-spiegata' => [
    'pillar' => 'slug-esatto-pillar',
    'articles' => [
        ['slug' => 'slug-esatto-1', 'position' => 1, 'primary' => true],
    ],
],
```

Il comando deve essere idempotente (`updateOrCreate`/sync deterministico), fallire se uno slug dichiarato è ambiguo o assente salvo flag esplicito di dry-run/report, e produrre prima un report umano. Nessun matching fuzzy sui titoli in produzione.

## Test plan

Feature/PHP:

- cluster CRUD protetto da auth/editor;
- slug uniqueness;
- active/inactive visibility;
- solo articoli `Article::published()` pubblici;
- ordering manuale;
- pillar deve appartenere al cluster;
- massimo un primary cluster per articolo;
- deletion semantics di pivot e pillar;
- `/percorsi`;
- `/percorsi/{slug}`;
- canonical/robots/OG/Twitter;
- `CollectionPage`, `ItemList`, `BreadcrumbList`;
- sitemap include active/public e esclude inactive/empty;
- article box prev/next usa soltanto membri pubblici;
- scheduled future non trapela in conteggi, posizione o link;
- Internal Linking temporal rules restano invariate.

MariaDB:

- `migrate:fresh`;
- migration incrementale su schema già popolato;
- FK/unique/index behavior;
- delete cluster/article semantics;
- ordering uniqueness/application invariant.

## Playwright plan

Copertura pubblica:

- `/percorsi`;
- `/percorsi/ia-spiegata`;
- articolo con `Continua il percorso`;
- previous/next;
- keyboard navigation;
- nessun console error;
- nessun horizontal overflow;
- viewport 390, 768, 1440.

Usare seed deterministico separato o estendere con cautela `BrowserTestSeeder`; evitare dipendenza da contenuto production.

## Risks

- cannibalizzazione SEO se la description del Percorso diventa un articolo duplicato;
- incoerenza primary cluster se l'invariante è applicato in più controller invece che in un service;
- leak di scheduled content se le query pubbliche non riusano il contratto `published()`;
- N+1 su membership/prev-next;
- ordering ambiguo dopo rimozioni;
- coupling prematuro con Internal Linking;
- migration FK/index non verificata su MariaDB;
- backfill basato su titoli mutabili.

## Rollback

Prima release: feature flag implicito tramite assenza di route/UI fino alle PR pubbliche. La PR data-model/admin può essere mergiata senza rendere pubblici i Percorsi.

Rollback applicativo: rimuovere route/UI pubbliche lasciando i dati intatti.

Rollback schema, se realmente necessario prima di uso production, elimina soltanto pivot e `content_clusters`; dopo uso editoriale reale va preferita una follow-up migration rispetto a un rollback distruttivo dei dati editoriali.

## Phase 1 implementation plan

### PR A — data model + admin

- migrations MariaDB-safe;
- `ContentCluster` model e relazioni Article;
- service per membership/primary/pillar invariants;
- admin CRUD e article-form integration;
- feature tests;
- nessuna route pubblica.

### PR B — public Percorsi + SEO

- `/percorsi` e `/percorsi/{slug}`;
- publication filtering;
- canonical/robots/OG/Twitter;
- structured data;
- sitemap integration;
- Playwright responsive/accessibility coverage.

### PR C — article continuation + Internal Linking integration

- `Continua il percorso`;
- prev/next;
- query optimization;
- eventuale ranking signal per pillar/same-cluster nel suggeritore, senza bypassare temporal eligibility;
- analytics events solo se compatibili con lo stack esistente.

Questa scomposizione mantiene ogni PR piccola, testabile e reversibile e separa schema/admin dalla superficie pubblica e dal motore di linking.