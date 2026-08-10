# Editorial Quality Gate V1

Un sistema di controllo qualità editoriale deterministico: verifica se un
articolo presenta gli elementi tecnici/editoriali attesi prima della
pubblicazione — mai se è "bello" o "scientificamente corretto".

## 1. Scopo e non-scopo

**Scopo**: automatizzare ciò che una macchina può verificare con certezza
(presenza, lunghezza, coerenza, formato), così l'essere umano può dedicare
tempo a ciò che richiede giudizio.

**Non-scopo, esplicito**:
- non giudica se un contenuto è "bello" o "ben scritto";
- non dichiara mai "scientificamente corretto" — un controllo automatico
  non può dimostrarlo (vedi §11);
- non usa AI, embedding o servizi esterni — completamente deterministico;
- non riscrive articoli;
- non impedisce mai automaticamente la pubblicazione — è un ASSISTENTE,
  mai un blocco (vedi §2);
- non introduce colonne `quality_score`/`quality_status` nel DB — ogni
  risultato è calcolato al volo, mai persistito (§9).

## 2. Principio di prodotto

> Kairus non deve automatizzare il giudizio editoriale. Deve automatizzare
> ciò che una macchina può verificare con certezza.

Il Quality Gate può dire *"manca una cover"*, mai *"questa cover è
brutta"*. Può dire *"non rilevo fonti"*, mai *"questo articolo è
scientificamente sbagliato"*. Può dire *"questo articolo programmato
presenta 3 anomalie"*, ma la decisione di pubblicarlo resta sempre umana.

Nessun messaggio è mai accusatorio: ogni testo descrive un fatto
verificabile ("Il sommario è molto corto"), mai un giudizio sulla persona
che ha scritto l'articolo.

## 3. Architettura

```
Article
    │
    ▼
EditorialQualityChecker::check($article)
    │
    ├─ 18 controlli modulari, ciascuno un metodo privato
    │  (CONTENT, MEDIA, SEO, STRUCTURE, SOURCES, DISCOVERY, PUBLISHING)
    │
    ▼
EditorialQualityReport
    ├─ results: array<EditorialQualityCheckResult>
    ├─ level(): READY | ATTENTION | INCOMPLETE
    ├─ passedCount() / applicableCount()
    └─ issues(): solo WARNING/FAIL

EditorialQualityAuditService::audit($articleId?, $status?)
    │  (per il comando CLI — bulk, precalcola gli indici condivisi
    │   per evitare N+1: vedi §8)
    ▼
EditorialQualityAuditSummary
```

File principali:

| File | Ruolo |
|---|---|
| `app/Services/EditorialQuality/EditorialQualityCheckResult.php` | Esito di UN controllo (code/label/status/importance/category/message/details) |
| `app/Services/EditorialQuality/EditorialQualityReport.php` | Tutti i risultati per UN articolo + algoritmo di readiness |
| `app/Services/EditorialQuality/EditorialQualityChecker.php` | I 18 controlli |
| `app/Services/EditorialQuality/EditorialQualityAuditService.php` | Esegue il checker su più articoli, aggrega, evita N+1 |
| `app/Services/EditorialQuality/EditorialQualityAuditSummary.php` | Risultato aggregato per il comando CLI |
| `app/Console/Commands/EditorialQualityAuditCommand.php` | `content:quality-audit` |
| `resources/views/partials/editorial-quality-gate.blade.php` | Card "Qualità editoriale" nel form Admin |

## 4. Audit del modello Article (prima di scrivere codice)

Il repository è la fonte di verità: nessun campo qui sotto è stato
assunto, tutti sono stati verificati nel modello/migrazioni esistenti
prima di scrivere i controlli.

| Dato | Campo/metodo reale |
|---|---|
| Stati editoriali | `Article::STATUS_DRAFT/REVIEW/SCHEDULED/PUBLISHED` |
| Coerenza stato/data | `Article::isScheduleOverdue()` (già esistente, riusato) |
| Cover | `cover_image`, `cover_alt`, `cover_caption`, `cover_credit` |
| SEO — valore RENDERIZZATO | `metaTitle()`, `metaDescription()`, `metaCanonicalUrl()`, `metaRobots()` (già esistenti, con fallback — mai i campi grezzi, sempre cosa finisce davvero in pagina) |
| Fonti | `primary_sources` — campo di testo libero, non struttura dedicata |
| Verifica scientifica (già esistente, distinta) | `verification_status`/`verification_notes` — un flusso EDITORIALE separato ("qualcuno ha controllato i fatti"), non sovrapposto al Quality Gate (che verifica solo completezza tecnica) |
| Collegamenti interni | `ArticleLinkInsertionService::countArticleLinks()` (già esistente, riusato — nessun parser nuovo) |

## 5. I 18 controlli

| Categoria | Codice | Importanza | Cosa verifica |
|---|---|---|---|
| CONTENT | `title_present` | Essenziale | Titolo presente, non solo whitespace, lunghezza minima |
| CONTENT | `slug_present` | Essenziale | Slug presente, formato valido per la route pubblica |
| CONTENT | `excerpt_present` | Consigliato | Sommario presente e non troppo corto |
| CONTENT | `body_present` | Essenziale | Corpo con testo reale (non solo markup vuoto/immagini), almeno 50 parole |
| CONTENT | `no_placeholder_markers` | Essenziale | Nessun segnaposto (Lorem ipsum, TODO, FIXME...) in titolo/sommario/corpo |
| MEDIA | `cover_present` | Essenziale | Cover impostata |
| MEDIA | `cover_alt_present` | Consigliato | Alt text della cover (NOT_APPLICABLE senza cover) |
| MEDIA | `body_images_alt` | Consigliato | Immagini nel corpo con alt text (NOT_APPLICABLE senza immagini) |
| SEO | `seo_title` | Consigliato | Titolo SEO effettivo entro 70 caratteri (NOT_APPLICABLE per bozza/revisione) |
| SEO | `meta_description` | Consigliato | Meta description effettiva 50–200 caratteri |
| SEO | `indexability` | Consigliato | Nessun "noindex" inatteso su un articolo pubblicato |
| STRUCTURE | `structure_headings` | Consigliato | Sottotitoli (H2/H3) presenti in articoli lunghi (≥600 parole; NOT_APPLICABLE sotto soglia) |
| SOURCES | `sources_present` | Consigliato | Almeno una fonte identificabile (mai FAIL, sempre WARNING se assente) |
| DISCOVERY | `internal_links_present` | Consigliato | Almeno un collegamento verso un altro articolo Kairus |
| DISCOVERY | `duplicate_title` | Consigliato | Nessun altro articolo con lo stesso titolo (normalizzato) |
| PUBLISHING | `author_present` | Essenziale | Autore valido associato |
| PUBLISHING | `category_valid` | Essenziale | Categoria presente e riconosciuta |
| PUBLISHING | `publishing_coherence` | Essenziale | Stato "Pubblicato" con data di pubblicazione; programmato in ritardo → avviso |

**Essential vs Recommended**: dedotto dal prodotto esistente, non imposto
a priori — i controlli ESSENTIAL corrispondono a condizioni che, se
violate, renderebbero l'articolo strutturalmente rotto per la route
pubblica o il workflow editoriale (nessun autore, nessuna categoria valida,
corpo vuoto, titolo assente, un segnaposto dimenticato) — non giudizi di
qualità, fatti che impedirebbero all'articolo di funzionare.

## 6. Readiness

```
Un FAIL su un controllo ESSENTIAL         → INCOMPLETE
Nessun FAIL essenziale, ma ≥1 WARNING/FAIL → ATTENTION
Tutto PASS (o NOT_APPLICABLE)              → READY
```

Non è una media di severità: **un solo** controllo essenziale non
superato basta per INCOMPLETE, indipendentemente da quanti altri
controlli sono PASS — un articolo con 17 controlli su 18 superati ma
senza autore resta INCOMPLETE, perché quella singola condizione impedisce
all'articolo di funzionare correttamente.

Il denominatore di "N/M controlli superati" (`applicableCount()`) esclude
sempre i NOT_APPLICABLE — un articolo breve senza immagini non viene mai
penalizzato per controlli che semplicemente non lo riguardano.

## 7. UI Admin

Card "Qualità editoriale" nel form di modifica articolo (`admin.article-
form`), accanto alla card esistente "Collegamenti interni suggeriti" —
stessa posizione, stesso stile visivo, nessun ridisegno della pagina.

- Sempre visibile: livello (Pronto/Attenzione/Da completare) + "N/M
  controlli superati";
- **Progressive disclosure**: l'elenco dei singoli controlli è dietro un
  `<details>` HTML nativo (nessun JavaScript necessario), chiuso di
  default — non occupa spazio finché l'editor non lo apre;
- mai un blocco: nessun controllo qui impedisce il salvataggio o la
  pubblicazione (verificato esplicitamente,
  `test_the_quality_gate_never_blocks_saving_an_incomplete_article`);
- non mostrata in fase di creazione (`$article` è null finché non è
  salvato per la prima volta — stessa logica già in uso per i
  "Collegamenti interni suggeriti").

**Non introdotto in questa versione**: un indicatore nella lista Admin
Articoli (FASE 32 della missione) — calcolare `check()` per ogni riga di
una lista non paginata avrebbe un costo non trascurabile (18 controlli,
alcuni con parsing DOM, per ogni articolo mostrato). Rimandato finché la
lista non sarà paginata o il calcolo non sarà reso incrementale — vedi
§13 "Limiti noti".

**Non ancora nell'area Redazione**: la card è presente solo in
`admin.article-form`, non nel form equivalente di Redazione — la missione
chiedeva esplicitamente l'integrazione "nella schermata admin più utile"
(FASE 31); estenderla a Redazione è un'estensione naturale ma fuori
perimetro per V1.

## 8. CLI: `content:quality-audit`

```
php artisan content:quality-audit [--article=ID] [--status=STATO] [--json]
```

Rigorosamente **read-only** — nessun `save()`/`update()`/`delete()` in
tutto `app/Services/EditorialQuality/` (verificato anche per grep, oltre
che da `tests/Feature/EditorialQualityAuditCommandTest.php::
test_the_command_never_modifies_any_article()`).

Output testuale: riepilogo (analizzati/ready/attention/incomplete),
problemi più frequenti (aggregati per codice controllo, ordinati per
frequenza), elenco degli articoli non READY con il loro punteggio.

Output JSON (`--json`): `summary` / `articles[]` (ciascuno con `checks[]`
completo) — mai il body HTML dell'articolo, solo id/title/status/level e
i risultati dei controlli.

## 9. Nessuna migration

Nessun campo `quality_score`/`quality_status`/`quality_checks` nel
database — ogni risultato è calcolato al volo da dati già presenti sul
modello `Article`. Un articolo modificato è immediatamente riflesso nel
prossimo calcolo, senza bisogno di invalidare o ricalcolare una cache.

## 10. Compatibilità con contenuti legacy

Un articolo storico che non rispetta le nuove raccomandazioni (nessuna
fonte, nessun alt cover, titolo SEO assente) non genera mai un'eccezione:
appare semplicemente come ATTENTION o INCOMPLETE quando appropriato, senza
rompere la modifica o la pubblicazione (`tests/Feature/
EditorialQualityAuditCommandTest.php::
test_a_legacy_article_missing_many_optional_fields_never_crashes_the_command`).

## 11. Cosa il Quality Gate NON può sapere

Sezione obbligatoria per non generare falsa fiducia:

- se una spiegazione scientifica nell'articolo è corretta;
- se una fonte citata supporta davvero l'affermazione a cui è associata;
- se il titolo è editorialmente brillante o efficace;
- se un'immagine è semanticamente appropriata al contenuto (solo se
  esiste, non se è quella giusta);
- se un articolo merita davvero di essere pubblicato;
- se il tono è adeguato al pubblico di Kairus;
- se due articoli "simili" per titolo trattano davvero lo stesso
  argomento o solo condividono parole comuni.

Il Quality Gate risponde solo a "questo articolo presenta gli elementi
tecnici attesi?" — mai a "questo articolo è buono?".

## 12. Performance

`EditorialQualityAuditService::audit()` precalcola due indici UNA VOLTA
per l'intero corpus, indipendentemente dal numero di articoli analizzati:

- un indice titoli normalizzati → conteggio (evita che
  `duplicate_title` esegua una query per ogni articolo — N+1 misurato in
  self-review: 181 query su 60 articoli prima del fix);
- eager loading dell'autore (`with('author:id')` — altrimenti
  `author_present` avrebbe lazy-caricato una query per articolo);
- memoizzazione di `Category::options()` per la durata di un singolo
  audit (altrimenti `category_valid` avrebbe ripetuto la stessa query
  identica per ogni articolo).

Dopo questi tre fix: query costanti indipendentemente dal numero di
articoli — verificato confrontando 20 vs 100 articoli
(`tests/Feature/EditorialQualityAuditPerformanceTest.php::
test_the_query_count_does_not_grow_linearly_with_the_number_of_articles`).

Nessuna cache introdotta: il calcolo (18 controlli su dati già in
memoria, con qualche parsing DOM leggero) è già economico a query
costanti — introdurre una cache ora sarebbe prematuro senza un problema
misurato da risolvere.

## 13. Limiti noti

- L'indicatore nella lista Admin Articoli non è stato introdotto in V1
  (vedi §7) — la lista non è paginata oggi, calcolare 18 controlli per
  riga avrebbe un costo non proporzionato.
- Il rilevamento titoli duplicati è per uguaglianza esatta normalizzata
  (case/spazi), non per similarità — due titoli quasi identici ma non
  identici non vengono rilevati (deliberatamente: nessun algoritmo di
  similarità testuale introdotto, per restare deterministico e semplice).
- Il riconoscimento "fonte istituzionale" (§sources) è un elenco chiuso
  di 10 domini, puramente informativo — non influenza mai lo stato del
  controllo, solo i `details`.
- Il controllo struttura verifica solo la presenza di H2/H3 in articoli
  lunghi, non la qualità della gerarchia (una sequenza H2→H4 senza H3 non
  viene segnalata — fuori scope per "non trasformare questo in un
  validator HTML completo", per esplicita richiesta della missione).
- Nessuna integrazione con Project Next Action V2 (PR #157) in questa
  versione — un segnale futuro "articolo programmato non pronto"
  richiederebbe di collegare due domini (Article e Project) che oggi
  restano volutamente separati; documentato come estensione futura, non
  implementato per non allargare lo scope di questa missione (Article
  Quality, non Project Automation V3).
- Non integrato con Internal Linking V2 (branch/PR separati, non ancora
  mergiati al momento di questa missione): `internal_links_present` usa
  `ArticleLinkInsertionService::countArticleLinks()`, già presente su
  main prima di questa missione — non richiede né duplica nulla del
  lavoro di Internal Linking V2. Se in futuro l'audit di Internal Linking
  V2 (classificazione link rotti/orfani) sarà disponibile, potrebbe
  arricchire questo controllo (es. "collegamenti presenti ma rotti"),
  ma è un'estensione, non un prerequisito.

## 14. Rollback

Nessuna migration introdotta. Per tornare al comportamento precedente:

1. Revert dei file elencati in §3.
2. Nessun dato da ripulire: nessuna colonna nuova, nessuna riga
   persistita da questo sistema.
3. Il comando `content:quality-audit` è puramente additivo (read-only):
   rimuoverlo non ha effetti collaterali su nient'altro.
4. La card "Qualità editoriale" è un `@include` isolato nel form Admin:
   rimuoverla non tocca il resto del form.

## 15. Estensioni future

- Indicatore discreto nella lista Admin Articoli, una volta paginata.
- Estensione della card al form Redazione.
- Integrazione con Project Next Action V2 per un segnale
  "Articolo programmato non pronto" nella dashboard Progettazione.
- Un layer AI reviewer separato e opzionale (esplicitamente fuori scope
  per V1 — "vietato usare AI/embeddings/LLM/API esterne" nel Quality Gate
  deterministico).
