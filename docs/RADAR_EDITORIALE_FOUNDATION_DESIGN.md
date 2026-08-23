# Radar editoriale — foundation design

## Stato

**DESIGN COMPLETE — IMPLEMENTATION DEFERRED.**

Il dependency audit mostra che alcune fonti decisive esistono soltanto in PR aperte e non sono quindi runtime dependencies valide su `main`. Costruire ora tabelle/regole di opportunity significherebbe fissare soglie e integrazioni su infrastruttura non ancora stabilizzata.

## Fresh main

Base del design: `main` @ `580ee379a6ef6d9f81829109b462b928dd9c9459`.

## Dependency matrix

### Disponibile su main

#### Article catalog

Disponibili:

- status draft/review/scheduled/published;
- `published_at`;
- categoria;
- author;
- contenuto/metadati editoriale;
- `views` aggregato sul record;
- `ArticleView` con `viewed_at` e referer.

Usi potenziali Radar:

- inventory;
- scheduled-content suppression;
- trend di view dopo baseline/threshold policy;
- candidate per UPDATE_CONTENT, mai per NEW_ARTICLE senza ulteriori segnali.

#### Percorsi (`ContentCluster`)

Disponibili su main con membership articolo/posizione.

Usi potenziali:

- cluster inventory;
- rilevare tappe non pubbliche o cluster con copertura incompleta solo con regole editoriali esplicite;
- sopprimere duplicati se un tema/articolo e gia pianificato nel Percorso.

Non viene definita automaticamente una "dimensione ideale" di Percorso.

#### Newsletter legacy tracking

`NewsletterOpen` e `NewsletterClick` esistono e registrano eventi. I record raw includono email, ip hash e user agent.

Radar **non deve mai** consumare o mostrare questi identificatori. Un provider futuro puo leggere solo aggregati per articolo/periodo (conteggi/rate), eliminando il dettaglio individuale prima di creare un `Signal`.

#### Search pubblica

`ArticleSearchService` esiste, ma le query digitate non vengono persistite come dataset analytics. Quindi **INTERNAL_SEARCH_GAP non e disponibile** su main.

### Non disponibile su main

#### Search Console Opportunity Intelligence

PR #275 aperta. Non disponibile finche non mergiata.

Segnali bloccati:

- SEARCH_GROWTH;
- HIGH_IMPRESSIONS_LOW_CTR;
- POSITION_OPPORTUNITY;
- query-level CONTENT_GAP.

#### Second Read Analytics

PR #267 aperta (con #268 stacked). Non disponibile.

Segnali bloccati:

- STRONG_SECOND_READ;
- WEAK_SECOND_READ.

#### Social Attribution

PR #277 aperta. Non disponibile.

Qualunque segnale social downstream resta deferred.

#### Content Graph

PR #279 draft. Non disponibile.

Bloccati:

- deduplication primaria per Concept;
- concept-level CONTENT_GAP;
- semantic adjacency;
- copertura per Concetto.

#### TROVA V1

Missione 08 e design-only (#281) finche #279/ownership Search non si stabilizzano. Nessun query-log interno disponibile.

#### Admin Article Calendar V1

PR #273 aperta. La UI calendario non e su main, ma i dati scheduled sono gia nel model Article e possono essere usati come **suppression source**. Non dipendere dal controller/UI #273.

#### Content Health

PR #280 draft. Non disponibile come provider finche non mergiata.

## Signal model

Contratto futuro di un `RadarSignal`:

- `type`;
- `subject_type` (`article`, `concept`, `query`, `path`, `category`);
- `subject_key` stabile;
- `period_start` / `period_end` se temporale;
- `metrics` solo aggregate;
- `evidence` leggibile;
- `source` (provider identificabile);
- `observed_at`.

Nessun signal contiene email, IP, user agent o identificatori visitatore.

## Provider architecture

Separare provider piccoli e testabili.

### Provider disponibili concettualmente da main

`ArticleCatalogSignalProvider`
- published/scheduled inventory;
- scheduled suppression facts;
- metadata temporale necessario a regole future.

`ArticleViewSignalProvider`
- view aggregate per articolo e finestra temporale;
- nessuna classificazione "decay" finche non esiste una baseline/threshold policy misurata.

`PercorsoSignalProvider`
- membership/posizioni/stato percorso;
- facts, non giudizi automatici sul numero corretto di tappe.

`NewsletterAggregateSignalProvider`
- solo aggregazioni click/open;
- raw PII mai esposto fuori dal provider.

### Provider deferred

- `SearchConsoleSignalProvider` -> #275;
- `SecondReadSignalProvider` -> #267;
- `SocialAttributionSignalProvider` -> #277;
- `ContentGraphSignalProvider` -> #279;
- `InternalSearchSignalProvider` -> futuro query analytics TROVA;
- `ContentHealthSignalProvider` -> #280 dopo merge.

## Opportunity types

Tipi target del dominio:

- `NEW_ARTICLE`;
- `UPDATE_CONTENT`;
- `CTR_IMPROVEMENT`;
- `INTERNAL_LINKING`;
- `PERCORSO_OPPORTUNITY`.

### Availability decision

#### NEW_ARTICLE

**Non implementabile in modo affidabile ora.**

Richiede almeno un gap signal credibile da Search Console, internal search o Content Graph e deve essere confrontato con published/scheduled inventory.

#### UPDATE_CONTENT

Potenzialmente derivabile da trend ArticleView, ma **non implementato** finche non esiste una baseline che definisca un calo significativo e una policy freshness.

#### CTR_IMPROVEMENT

Dipende da #275. Deferred.

#### INTERNAL_LINKING

Esiste un sistema di internal-link suggestions nel repository, ma Radar non deve duplicarlo. Un provider futuro deve consumare output/coverage del sistema esistente o Content Graph, non creare un secondo suggester.

#### PERCORSO_OPPORTUNITY

Dati Percorso disponibili, ma manca una regola editoriale repository-grounded che definisca un "gap" solo dal numero di tappe. Deferred finche non e combinabile con segnali Search/Content Graph/editorial planning.

## Opportunity contract

Una opportunity futura deve contenere:

- `type`;
- `subject` normalizzato e leggibile;
- `evidence` (lista di fatti/provider);
- `reason` in linguaggio naturale deterministico;
- `confidence` derivata da classi/regole trasparenti, non pseudo-AI;
- `suggested_action`;
- `source_signals` tracciabili;
- `created_at`;
- `status`.

## Confidence model

Non usare 0-100.

Classi consigliate:

- `HIGH`: almeno una regola forte esplicitamente definita e nessun suppressor;
- `MEDIUM`: combinazione di segnali indipendenti sufficienti secondo regola documentata;
- nessuna opportunity per evidence debole/insufficiente.

La soglia esatta per ogni signal provider deve essere definita solo dopo baseline reale. Fino ad allora il provider puo emettere metriche/facts ma non classificazioni forti.

## Workflow

Stati V1:

- `NEW`;
- `REVIEWED`;
- `PLANNED`;
- `IGNORED`;
- `RESOLVED`.

Transizioni esplicite, human-driven.

`PLANNED` non crea automaticamente Article, ProjectTask, calendar event o scheduled publication. Puo in futuro collegare un oggetto editoriale gia creato manualmente.

## Deduplication

Ordine richiesto:

1. Concept id quando Content Graph e realmente disponibile;
2. Article id per opportunity article-specific;
3. normalized query/topic key come fallback.

### Normalized topic fallback

Solo normalizzazioni deterministiche:

- lowercase Unicode;
- trim/collapse whitespace;
- punteggiatura compatibile con `SearchTokenizer`;
- nessun stemming/embedding opaco oltre alle regole gia documentate dal search tokenizer.

Se due opportunity hanno la stessa dedup key:

- unire evidence/source signals;
- mantenere una sola opportunity attiva per `(type-family, dedup_key)`;
- non moltiplicare card per provider.

Famiglie possibili:

- acquisition: SEARCH_GROWTH/HIGH_IMPRESSIONS/POSITION;
- content gap: query/concept gap;
- engagement: second-read/view/newsletter;
- structure: linking/Percorso.

La mappa finale va congelata in test prima dell'implementazione.

## Anti-noise / suppressors

Regole obbligatorie:

1. **weak signal -> silence**;
2. **insufficient data -> silence**;
3. se esiste un Article `scheduled` pertinente, nessun `NEW_ARTICLE` equivalente;
4. se il subject e un article esistente, preferire UPDATE/CTR/INTERNAL_LINKING rispetto a NEW_ARTICLE;
5. se un opportunity e gia `PLANNED`, provider refresh puo aggiornare evidence ma non creare un duplicato;
6. topic gia coperto bene -> nessun nuovo articolo solo perche una query esiste;
7. dati Search Console di un solo periodo non possono produrre GROWING_QUERY;
8. raw newsletter/social identifiers non influenzano confidence.

Il punto 3 puo essere implementato in futuro usando il model Article gia su main, senza dipendere dalla UI calendario #273.

## Admin UX design

Superficie futura: **Radar editoriale**.

Ogni card:

- type;
- subject;
- confidence class;
- "Perche lo vedo";
- evidence con periodo/metriche;
- suggested action;
- status;
- provider/source labels.

Filtri:

- type;
- status;
- confidence;
- categoria;
- Percorso;
- Concept solo quando #279 e su main.

Azioni:

- `Valuta` -> REVIEWED;
- `Pianifica` -> PLANNED (nessuna creazione automatica);
- `Ignora` -> IGNORED;
- `Risolto` -> RESOLVED.

Nessuna mutation automatica di Article/SEO.

## Persistence decision

**Nessuna migration in questa missione design-only.**

Non si introduce `radar_opportunities` finche non sono stabilizzati almeno:

- un provider acquisition (#275);
- Content Graph o altro dedup subject forte (#279);
- una policy di evidence/threshold validata.

Questo evita una tabella prematura con chiavi/semantiche destinate a cambiare.

## Privacy

Radar lavora solo su dati aggregati/editoriali.

- niente email;
- niente IP hash;
- niente user agent;
- niente fingerprinting;
- niente nuovo visitor id;
- niente cross-session identity.

Provider che leggono tabelle raw devono aggregare prima di emettere un signal.

## Test contract futuro

### Provider

- determinismo sulle stesse fixture;
- metriche aggregate corrette;
- nessun PII nel signal;
- period boundaries espliciti;
- failure isolation per provider opzionali.

### Rules

- weak signal suppression;
- insufficient evidence suppression;
- scheduled-content suppression;
- already-covered topic -> no duplicate NEW_ARTICLE;
- article-specific improvement preferito a duplicate NEW_ARTICLE;
- dedup multi-provider;
- evidence merge deterministic;
- confidence class explainable.

### Workflow

- transizioni ammesse esplicite;
- nessuna auto-transition a PLANNED/RESOLVED;
- nessuna creazione automatica Article;
- nessuna modifica SEO;
- nessuna pubblicazione/scheduling.

### Integration

- query bounded;
- provider non disponibile non rompe Radar;
- MariaDB reale se verra introdotta persistence;
- admin/browser regression solo dopo UI.

## Sblocco implementation

Ordine raccomandato:

1. merge/review #275 Search Console Opportunity Intelligence;
2. merge/review #279 Content Graph;
3. merge #267 Second Read Analytics quando pronto;
4. stabilizzare #280 Content Health;
5. ridefinire provider matrix su fresh main;
6. scegliere **un piccolo set** di segnali realmente affidabili;
7. solo allora implementare domain foundation + repository + tests.

Il Radar deve preferire nessuna card a una card poco difendibile.
