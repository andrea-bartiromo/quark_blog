# Editorial Operations Dashboard Blueprint

Missione 20 — `DESIGN COMPLETE — IMPLEMENTATION DEFERRED`.

## Principio

La dashboard editoriale deve mostrare **eccezioni e azioni**, non duplicare tutte le schermate Admin. Ogni card deve rispondere a una domanda operativa concreta e rimandare alla superficie proprietaria del dato.

## Dependency gate

Disponibile su `main` oggi:

- workflow Article e stati `draft/review/scheduled/published`;
- Editorial Quality / publication readiness;
- Internal Link Audit / graph health;
- Percorsi / ContentCluster;
- ArticleView e analytics legacy già presenti.

NON disponibile su `main` e quindi non consumabile dal runtime dashboard:

- Article Calendar: #273;
- Second Read Analytics: #267 (e #268 stacked certification);
- Search Console Opportunities: #275;
- Social Attribution: #277;
- Content Graph: #279;
- Content Health foundation: #280;
- Radar: #283 è design-only;
- Percorsi Coverage Audit: #284;
- Attribution Health: #285;
- SEO Metadata Quality Audit: #286.

Il blueprint non copia né reimplementa queste dipendenze.

## Information architecture

### DA PUBBLICARE

Obiettivo: cosa richiede attenzione temporale/editoriale immediata.

Provider futuro:
- Article status/scheduled date già su main;
- Article Calendar #273, solo dopo merge.

Mostrare soltanto eccezioni:
- review da valutare;
- scheduled con timestamp incoerente, se una policy esistente lo segnala;
- contenuti prossimi alla pubblicazione con readiness issues.

Non mostrare un secondo calendario completo.

### DA SISTEMARE

Provider:
- publication readiness già su main;
- Content Health #280 dopo merge;
- Attribution Health #285 dopo merge;
- SEO Metadata Audit #286 dopo merge.

Ogni riga deve contenere:
- articolo;
- tipo problema;
- severità/level proveniente dal provider originale;
- spiegazione breve;
- deep-link alla schermata che consente la correzione.

Niente score aggregato 0–100.

### CONTENUTI ISOLATI

Provider:
- InternalLinkAuditService già su main;
- Percorsi Coverage #284 dopo merge.

Segnali:
- published orphan;
- zero body links in/out;
- link rotti/unpublished;
- articolo published/scheduled senza Percorso;
- Percorso singleton o incoerente.

Body links, structural navigation e recommendation devono restare metriche distinte.

### OPPORTUNITÀ

Provider futuri:
- Search Console #275;
- Second Read #267;
- Radar solo dopo che i provider necessari sono su main;
- Content Graph #279 per opportunità semantiche future.

La dashboard non deve ricalcolare scoring o dedup: visualizza un sottoinsieme già prodotto dai provider proprietari.

### SEO

Provider:
- Quality Gate già su main;
- #286 dopo merge;
- #275 per opportunity Search Console dopo merge.

Mostrare eccezioni: duplicate metadata, canonical warning, noindex pubblicato, fallback problematici, opportunità CTR. Non duplicare l'intera UI Search Console.

### DISTRIBUZIONE

Provider futuri:
- #277 Social Attribution;
- newsletter/Communication aggregate metrics soltanto quando una fonte aggregata e privacy-safe è definita.

Mai mostrare email, IP hash, user agent o identificatori individuali.

## Data provider contract

Ogni sezione deve dipendere da un provider separato con un DTO minimo, per esempio:

- `key` stabile;
- `kind`;
- `severity` o status nativo del provider;
- `title`;
- `reason`;
- `action_url`;
- `fresh_at`;
- opzionale `article_id`/`cluster_id`.

Il dashboard orchestrator non deve contenere business rules: ordina e limita segnali già calcolati altrove.

## Query budget

V1 deve evitare una chiamata/query per card.

Regole:
- provider aggregati/batch, mai N+1 per articolo;
- conteggi e primi N elementi nello stesso provider quando possibile;
- default massimo 5 elementi visibili per sezione, con link “Vedi tutti” alla superficie proprietaria;
- nessun caricamento di body HTML completo se il provider può lavorare su risultati audit già aggregati;
- budget numerico definitivo soltanto dopo benchmark sul tree integrato, non inventato nel blueprint.

## Freshness & caching

Dati editoriali mutabili (review/scheduled/readiness) devono essere freschi alla request o avere cache molto breve con invalidazione definita.

Analytics/opportunity aggregati possono avere freshness esplicita per periodo/import, ma il dashboard non deve introdurre una seconda cache se il provider ne possiede già una.

Ogni sezione mostra, quando significativo, il periodo o `fresh_at`; mai far apparire un dato storico come realtime.

## Empty states

Un empty state deve distinguere:

- `NO_ISSUES` — provider disponibile e nessun elemento;
- `NO_DATA` — provider disponibile ma manca ingest/catalogo sufficiente;
- `DEPENDENCY_UNAVAILABLE` — feature non installata/non mergiata;
- `ERROR` — provider non disponibile in quella request.

Non trasformare `NO_DATA` in “Tutto bene”.

## Permissions

V1 usa il perimetro `auth + editor` già esistente per Admin. I link devono rispettare l'autorizzazione della destinazione; il dashboard non conferisce capacità aggiuntive.

Se in futuro la Redazione riceve una vista analoga, deve avere provider/output separati o filtrati secondo le policy già presenti, non riusare ciecamente l'Admin view.

## Mobile behavior

- una colonna su viewport stretto;
- sezioni ordinate per urgenza, non per estetica;
- card compatte con reason e action accessibili senza hover;
- nessuna tabella orizzontale obbligatoria;
- massimo N limitato per evitare pagine infinite.

## Accessibility

- heading hierarchy reale;
- landmark/main coerenti con layout Admin;
- status non comunicato solo tramite colore;
- link/action con label descrittive;
- focus order DOM naturale;
- nessun auto-refresh che sposti il focus;
- eventuali aggiornamenti asincroni futuri con live-region solo quando utili.

## Incremental PR plan

1. **Provider contract + shell vuoto** solo dopo che almeno due provider indipendenti sono stabili su main. Nessun business rule nel controller.
2. **DA PUBBLICARE**: Article/readiness + Calendar, dopo #273.
3. **DA SISTEMARE**: integrare provider Content Health/Attribution/SEO solo dopo i rispettivi merge e runtime gate.
4. **CONTENUTI ISOLATI**: Internal Link Audit + Percorsi audit dopo #284.
5. **OPPORTUNITÀ**: Search Console/Second Read, dopo #275/#267; Radar solo in una PR successiva se il suo dominio sarà implementato.
6. **DISTRIBUZIONE**: Social/newsletter aggregate, solo dopo provider privacy-safe.
7. Benchmark query/browser accessibility prima di aggiungere altre sezioni.

Ogni step è una PR separata e reversibile.

## Dependency graph

```text
main readiness -------------------> DA PUBBLICARE / DA SISTEMARE
main internal-link audit ---------> CONTENUTI ISOLATI
main Percorsi --------------------> CONTENUTI ISOLATI
#273 Article Calendar ------------> DA PUBBLICARE
#280 Content Health --------------> DA SISTEMARE
#285 Attribution Health ----------> DA SISTEMARE
#286 SEO Metadata Audit ----------> SEO / DA SISTEMARE
#284 Percorsi Coverage -----------> CONTENUTI ISOLATI
#275 Search Console --------------> OPPORTUNITÀ / SEO
#267 Second Read -----------------> OPPORTUNITÀ
#277 Social Attribution ----------> DISTRIBUZIONE
#279 Content Graph ---------------> future semantic opportunities
#283 Radar design ----------------> future OPPORTUNITÀ only after runtime providers mature
```

## Runtime decision

**Do not implement the dashboard now.** Too many intended providers are still open PRs, and implementing against them would either assume unmerged code or duplicate their logic. The next correct action is human review/runtime certification/merge of independent provider PRs, followed by a fresh-state dependency audit.

## Safety

Docs-only. Nessuna route/controller/view, migration, data mutation, merge o deploy.