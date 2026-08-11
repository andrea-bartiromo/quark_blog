# Internal Linking V2

Collegamenti interni tra articoli Kairus: come vengono suggeriti, come
vengono valutati, come vengono auditati. Questo documento copre sia il
sistema preesistente (suggeritore lessicale, V1→V3, PR #120/#121/#135/
#140/#141/#142) sia le aggiunte di questa missione ("Internal Linking V2":
concetti scientifici multi-parola, comando di audit).

## 1. Filosofia

> Un link interno deve aiutare il lettore a capire qualcosa. Non deve
> esistere soltanto perché due articoli condividono una parola.

Il sistema preferisce **rilevanza a quantità** e **precisione a recall**:
meglio non suggerire un collegamento che suggerirne uno semanticamente
sbagliato. Nessuna parte del sistema modifica mai automaticamente un
articolo pubblicato — ogni collegamento è **sempre** una proposta che la
redazione rivede, accetta o ignora esplicitamente.

Nessun componente di questo sistema usa AI, embedding o servizi esterni:
tutto il matching è lessicale (parole/frasi letteralmente presenti nel
testo) e deterministico.

## 2. Architettura

```
Article (source)                          Article (target, candidato)
    │                                              │
    ▼                                              ▼
plainText()                              extractTargetTerms() / targetPlainText()
    │                                              │
    ├─ extractTerms() ─────────┐        ┌──────────┤
    │  (tokenizer V3)          │        │
    │                          ▼        ▼
    │                    termini condivisi (sharedTerms)
    │                          │
    ├─ ScientificConceptMatcher┤
    │  (config/scientific_        concetti condivisi
    │   concepts.php)              (sharedConceptCanonicals)
    │                          │
    └──────────────────────────┴──► scoreLink() ──► score/anchor/reason
                                            │
                                            ▼
                              ArticleLinkSuggestion (status: proposed)
                                            │
                          redazione: "Inserisci" (esplicito) o "Ignora"
                                            │
                                            ▼
                        ArticleLinkInsertionService::insert()
                        (DOM-safe, mai una regex sull'HTML intero)
                                            │
                                            ▼
                              body salvato SOLO al "Salva modifiche"
```

File principali:

| File | Ruolo |
|---|---|
| `app/Services/ArticleLinkSuggestionService.php` | Genera/aggiorna le proposte (`analyzeForSource()`, `analyzeForNewTarget()`), tokenizer V3, scoring |
| `app/Services/InternalLinking/ScientificConceptMatcher.php` | **Nuovo (V2)** — riconosce concetti multi-parola nel testo |
| `app/Services/InternalLinking/ConceptCandidate.php` | **Nuovo (V2)** — DTO per un'occorrenza di concetto |
| `config/scientific_concepts.php` | **Nuovo (V2)** — registro alias dei concetti riconosciuti |
| `app/Services/ArticleLinkInsertionService.php` | Inserisce un link nel body (DOM-safe), conta i link presenti, estrae le occorrenze per l'audit |
| `app/Models/ArticleLinkSuggestion.php` | Persistenza delle proposte (`proposed`/`accepted`/`ignored`/`superseded`) |
| `app/Services/InternalLinking/InternalLinkAuditService.php` | **Nuovo (V2)** — audit read-only dell'intero corpus |
| `app/Console/Commands/InternalLinkAuditCommand.php` | **Nuovo (V2)** — `content:internal-link-audit` |
| `app/Services/InternalLinking/InternalLinkTemporalEligibility.php` | **Nuovo (V2.1)** — unica policy di eleggibilità temporale (`isTargetSafeForSource()`), condivisa da suggeritore e audit |
| `Article::scopeEligibleAsLinkTargetFor()` (`app/Models/Article.php`) | **Nuovo (V2.1)** — pre-filtro SQL della stessa regola, per la candidate selection del suggeritore |
| `resources/views/partials/article-link-suggestions.blade.php` | UI "Analizza / Inserisci / Ignora" nel form articolo |
| `resources/views/admin/articles.blade.php` | Badge "🔗 N articoli" sugli articoli programmati |

## 3. Tokenizer (V3, preesistente — non toccato da questa missione)

`ArticleLinkSuggestionService::extractTerms()` estrae termini a singola
parola da un testo:

- Unicode-safe (`\p{L}`, `\p{N}`), preserva identificatori alfanumerici
  come `gpt-5`, `covid-19`, `5g`;
- normalizza trattini e apici tipografici alle forme ASCII prima della
  tokenizzazione (copia-incolla da Word/Google Docs);
- riduce le elisioni italiane (`dell'universo` → `universo`) tramite un
  elenco chiuso di prefissi, non uno split indiscriminato su ogni apice;
- riconosce un piccolo elenco chiuso di acronimi corti tutto-maiuscolo
  (`DNA`, `RNA`, `ESA`, `ML`, `EU`, `ISS`) che altrimenti la lunghezza
  minima scarterebbe — **deliberatamente esclusi**: `AI`/`IA` (collidono
  con la preposizione articolata `ai`, rischio di falsi positivi troppo
  alto per un'allowlist generale);
- scarta stopword italiane (articoli, preposizioni, pronomi, verbi
  ausiliari comuni) e avverbi in `-mente`;
- lunghezza minima 4 caratteri (2 se il token contiene una cifra — `5g`,
  `h2o` sono già identificatori specifici a 2-3 caratteri).

**Limite noto, accettato per design**: un termine è sempre una singola
parola. Non esiste alcuna nozione di "frase" in questo layer — colmata
solo in parte dal match esatto del titolo (`findPhrase()`) e ora dal
Concept Matcher (§4).

## 4. Concetti scientifici multi-parola (V2, nuovo)

`ScientificConceptMatcher` riconosce **frasi** note (`config/
scientific_concepts.php`) che il tokenizer a singola parola non può
rappresentare come unità — "buco nero" tokenizzato diventa i due termini
indipendenti "buco" e "nero", indistinguibile da un testo che li menziona
senza relazione tra loro.

- Ricerca di frase letterale (stesso approccio di `findPhrase()` per il
  titolo), con un controllo di **confine di parola**: un alias non può mai
  combaciare con una sotto-stringa dentro una parola più lunga, né essere
  "incollato" a un altro carattere alfanumerico ai bordi.
- **Longest match first** (alias più lunghi cercati e reclamati per primi):
  un intervallo di testo già assegnato a un concetto non può essere
  ri-consumato da un alias più corto.
- Ogni concetto ha una forma **canonica** (es. `"buco nero"`) e uno o più
  **alias** (es. `"buchi neri"`) che vi si risolvono — il confronto tra
  concetto-nel-source e concetto-nel-target avviene sulla forma canonica,
  ma l'anchor proposta resta **sempre** il testo verbatim trovato nel
  sorgente (mai la forma canonica), per rispettare l'invariante esistente
  "l'anchor deve essere presente letteralmente nel testo".
- Registro **deliberatamente piccolo e conservativo** (13 concetti, tutti
  multi-parola): non un'enciclopedia. Alias corti tutto-maiuscolo
  ambigui (`IA`, `AI`, `GPT` da soli) sono esclusi per lo stesso motivo
  già documentato per il tokenizer V3 — vedi i commenti in
  `config/scientific_concepts.php`.

### Integrazione nello scoring

Un concetto condiviso (stessa forma canonica trovata sia nel testo
sorgente sia nel titolo/excerpt/body del target) aggiunge
`CONCEPT_MATCH_SCORE` (20 punti) allo score, fino a
`MAX_SCORED_CONCEPTS` (2) concetti per coppia — **additivo**, non
sostituisce i segnali esistenti (titolo, termini condivisi, categoria).
Il concetto compare anche nella spiegazione (`reason`) del suggerimento:
*"Concetto scientifico riconosciuto: buco nero"*.

Un concetto multi-parola è preferito come anchor rispetto a un singolo
termine generico (provato subito dopo il match del titolo, prima dei
termini a parola singola) — un'anchor più specifica e comprensibile fuori
contesto.

## 5. Scoring (preesistente, esteso in V2)

Punteggio 0-100, soglia minima 40 (`MIN_SCORE_THRESHOLD`) per essere
proposto, composto da segnali **indipendenti e sommati**:

| Segnale | Punti | Note |
|---|---|---|
| Titolo del target compare nel testo sorgente | +50 | segnale forte, quasi sempre sufficiente da solo |
| Concetto scientifico multi-parola condiviso (V2) | +20 ciascuno, max 2 | vedi §4 |
| Termine condiviso, specifico | +15 ciascuno, max 3 | "specifico" = compare in <20% del pool di candidati analizzato |
| Termine condiviso, generico | +5 ciascuno | onnipresente nel pool ("nuove", "tecnologie"...) |
| Stessa categoria | +10 | **mai** da solo sufficiente a superare la soglia |

Il ranking è **deterministico**: stesso input, stesso output, sempre — è
anche cosa lo rende auditabile e testabile senza flakiness.

## 6. HTML safety

`ArticleLinkInsertionService` opera esclusivamente via API DOM
(`DOMDocument`/`DOMXPath`), mai concatenazione di stringhe o regex
sull'HTML intero:

- un'anchor non può mai finire dentro un `<a>`, `<h1>-<h6>` o
  `<blockquote>` esistenti;
- gli script/style vengono saltati durante la ricerca del punto di
  inserimento;
- l'anchor deve trovarsi per intero in un **singolo nodo di testo** — se è
  spezzata da un tag inline (`<strong>`) o non è più presente
  letteralmente, l'inserimento fallisce esplicitamente (torna `null`),
  mai un inserimento approssimato;
- l'href è validato prima di essere passato a `setAttribute()` (schema
  http/https, host coincidente con `app.url`, nessun apice/backtick/
  backslash) — l'escaping non viene mai delegato al solo serializzatore
  DOM sottostante.

## 7. Override manuale (mai sovrascritto)

- Un suggerimento diventa `accepted` solo quando l'articolo viene
  **davvero salvato** — non al click su "Inserisci" (che modifica solo il
  form). Un editor che abbandona la modifica senza salvare lascia il
  suggerimento `proposed`, riproponibile.
- Un suggerimento già `accepted` o `ignored` (decisione presa dalla
  redazione) **non viene mai ricalcolato o riproposto** da una successiva
  "Analizza".
- Un link già presente nel testo (`linkedArticleSlugsInBody()`) fa
  marcare `superseded` (non cancellare) l'eventuale proposta corrispondente
  ancora `proposed` — mai una doppia proposta per lo stesso collegamento
  già fatto manualmente dalla redazione.

## 8. Destinazioni valide

Un candidato target viene analizzato se **pubblicato**, oppure — dalla
missione "Internal Linking V2.1", targeting temporale — se **programmato
temporalmente sicuro**: `App\Services\InternalLinking\
InternalLinkTemporalEligibility::isTargetSafeForSource()` è l'unica
definizione di questa regola, condivisa dal suggeritore e dall'audit:

- un target già **pubblicato** resta sempre eleggibile, qualunque sia lo
  stato della sorgente (comportamento V2 preesistente, invariato);
- altrimenti, eleggibile **solo se** la sorgente è essa stessa
  `scheduled` (con `published_at` valorizzato) **e** il target è
  `scheduled` (con `published_at` valorizzato) **e** il `published_at`
  del target è **strettamente precedente** a quello della sorgente — mai
  `<=`, per non dipendere dall'ordine di esecuzione dello scheduler
  quando i due istanti coincidono (vedi `App\Console\Commands\
  PublishScheduledArticles`, che pubblica gli articoli scheduled dovuti
  in ordine di `published_at` crescente).

Bozze/revisione non entrano mai nel pool, in nessun caso. Un articolo
`scheduled` con `published_at` **successivo** a quello della sorgente (o
una sorgente non `scheduled` — draft/review/già pubblicata) non entra
nel pool: la query del suggeritore
(`Article::eligibleAsLinkTargetFor($source)`, pre-filtro SQL) e la
verifica finale in memoria (`isTargetSafeForSource()`, applicata di
nuovo dopo il fetch come garanzia definitiva — la correttezza non
dipende dalla query SQL, solo da questo metodo) le escludono entrambe a
monte.

L'**audit** (§10), a differenza del suggeritore, osserva anche i link
*già presenti* nel body: un target non pubblico e non temporalmente
sicuro viene classificato `unpublished` (mai impediito, solo segnalato)
— casi che possono capitare per modifica manuale dell'editor HTML, o per
un target che era sicuro ed è stato poi riprogrammato/retrocesso, o per
una sorgente pubblicata manualmente in anticipo mentre il target è
ancora programmato. Un target non pubblico ma temporalmente sicuro viene
invece classificato `scheduled_safe` — reso esplicito, non un sinonimo
di `valid` (che significa "raggiungibile in questo momento"): vedi §10
per la classificazione completa.

## 9. Admin — copertura link (badge)

*Non introdotto da questa missione: già presente.* La lista Admin
Articoli mostra, per i soli articoli **programmati**
(`ArticleController::index()`), un badge "🔗 N articoli" —
`ArticleLinkInsertionService::countArticleLinks()`, conteggio degli
articoli Kairus distinti raggiunti dal body, calcolato sul body già
caricato dalla query principale (nessuna query aggiuntiva). Limitato ai
soli programmati per contenere il costo di parsing DOM per riga su liste
non paginate (vedi commento in `ArticleController::index()`).

## 10. Comando di audit (V2, nuovo)

```
php artisan content:internal-link-audit [--article=ID] [--status=STATO] [--json]
```

**Rigorosamente read-only**: nessuna scrittura, nessun `save()`/
`update()`/`delete()` in `InternalLinkAuditService` — verificato da
`tests/Feature/InternalLinkAuditCommandTest.php::
test_the_command_never_modifies_any_article()` (confronta `updated_at`,
`body`, `status` prima e dopo l'esecuzione).

Un **solo passaggio** sull'intero corpus (indipendentemente dai filtri)
costruisce contemporaneamente:

1. **classificazione dei link uscenti** di ogni articolo:
   - `valid` — il target esiste ed è pubblicato;
   - `scheduled_safe` (V2.1) — il target non è ancora pubblico, ma la
     sorgente è essa stessa `scheduled` e il target diventerà pubblico
     PRIMA di lei (§8) — non un'anomalia, solo non ancora raggiungibile
     in questo momento;
   - `unpublished` — il target esiste ma non è pubblico né temporalmente
     sicuro;
   - `redirected` — lo slug non esiste più, ma un `ArticleSlugRedirect`
     storico lo risolve (funziona per il lettore, o è temporalmente
     sicuro come sopra — in entrambi i casi andrebbe comunque aggiornato
     allo slug corrente);
   - `missing` — nessun articolo né redirect risolve lo slug: link rotto;
   - `self` — l'articolo collega se stesso;
2. **incoming links**: per ogni articolo, quanti ALTRI articoli distinti
   lo collegano — calcolato su tutto il corpus anche quando `--article=`
   limita quali righe vengono mostrate (un articolo è isolato rispetto a
   tutta Kairus, non solo al sottoinsieme filtrato);
3. **anchor ambigui**: la stessa frase-anchor (case/spazi normalizzati)
   che punta a destinazioni diverse nello stesso articolo.

I filtri `--article=`/`--status=` limitano **quali righe vengono
mostrate**, mai il corpus usato per la risoluzione/i conteggi — servirebbe
altrimenti a poco chiedere "questo articolo è isolato?" se la risposta
dipendesse da quali altri articoli il comando ha deciso di guardare.

### Articolo "isolato"

Un articolo **pubblicato** con `incoming_links_count === 0`. Bozze,
revisione e programmati non sono mai riportati come isolati — non hanno
ancora un pubblico da servire, "isolato" non è una categoria significativa
per loro finché non sono pubblici.

### Top opportunità

- pubblicati senza incoming links (i candidati più forti per un lavoro di
  collegamento retroattivo);
- programmati senza alcun link interno uscente (l'occasione più a buon
  mercato: l'articolo non è ancora pubblico, non c'è fretta ma nemmeno
  motivo di aspettare);
- suggerimenti `proposed` con `confidence_score >= 70` mai rivisti dalla
  redazione (segnale che la coda "Analizza collegamenti interni" non è
  stata guardata di recente per quell'articolo).

### Output JSON

Stabile e machine-readable (`summary`/`articles`/`top_opportunities`),
mai il body completo dell'articolo — solo id/title/slug/status e i link
classificati.

## 11. Related content (`Article::related()`)

**Auditato, non riscritto** in questa missione (fuori scope: la richiesta
era di preparare un'interfaccia riutilizzabile, non ampliare `related()`).
Oggi `related()` è puramente per categoria (`byCategory($this->category)`,
esclude sé stesso, limite 3) — non usa alcun segnale del suggeritore.
Un'integrazione futura potrebbe farlo restituire i target con lo score più
alto da `ArticleLinkSuggestionService`/`ScientificConceptMatcher` invece
della sola categoria condivisa; richiederebbe però decidere se calcolarlo
on-demand (costo per ogni pagina articolo pubblica) o precalcolarlo — una
decisione architetturale che allargherebbe sensibilmente lo scope di
questa missione, quindi deliberatamente rinviata.

## 12. Content Graph readiness

Questa missione **non** costruisce il Content Graph, le pagine
`/argomenti/{slug}`, la ricerca semantica o un registro Concetti
persistito — solo le fondamenta riutilizzabili:

```
Article
  │
  ▼
ConceptCandidate (DTO in-memory, questa missione)
  — canonicalTerm, matchedText, position, wordCount, source
  │
  ▼  (evoluzione futura, NON implementata qui)
Concept (persistito: tabella concepts, un record per concetto)
  │
  ▼
Alias (persistito: tabella concept_aliases, o colonna JSON su Concept)
  │
  ▼
Question (futuro: "quali domande risponde questo concetto?")
  │
  ▼
Topic Page (futuro: /argomenti/{slug}, aggrega articoli per Concept)
```

**Cosa V2 rende riutilizzabile**:
- `ConceptCandidate` ha già la forma che un Concept persistito
  userebbe (`canonicalTerm` ≈ `Concept.name`, `source` distingue già
  `'config'` da una futura `'content_graph'` senza cambiare la forma del
  DTO);
- `ScientificConceptMatcher::conceptsPresentIn()` è già l'interfaccia che
  un Content Graph consumerebbe — solo la sorgente dei concetti
  cambierebbe (da `config()` a una query), non il modo in cui vengono
  cercati nel testo;
- `InternalLinkAuditService` già calcola incoming/outgoing link count per
  articolo — la stessa informazione un futuro Content Graph
  userebbe per un "grado" di ogni nodo.

**Cosa resta da progettare** (esplicitamente NON deciso qui):
- se un Concept persistito sostituisce interamente
  `config/scientific_concepts.php` o convive con esso (es. config per i
  concetti "core" curati editorialmente, tabella per quelli scoperti);
- come un articolo si associa esplicitamente a un Concept (relazione
  many-to-many? tag derivato dal matching testuale? entrambi?);
- l'algoritmo di clustering/scoperta di nuovi concetti (NON il compito di
  questa missione: "meglio non inserire un link che inserirne uno
  sbagliato" si applica anche alla scoperta di concetti, non solo al
  linking).

**Cosa NON deve essere retrofittato**: nessuna migrazione dei 13 concetti
di `config/scientific_concepts.php` in una tabella "solo perché potrebbe
servire" — la migrazione va fatta quando il Content Graph è una missione
reale con un consumatore reale, non preventivamente.

## 13. Performance

Nessuna query `Article::all()` ripetuta per riga. Il badge Admin opera sul
body già caricato dalla query principale della lista (nessuna query
aggiuntiva). L'audit fa **un solo passaggio** sull'intero corpus
indipendentemente dai filtri — vedi
`tests/Feature/InternalLinkAuditPerformanceTest.php`, che verifica sia una
soglia assoluta (< 20 query su 60 articoli) sia, più significativamente,
che il conteggio query **non cresca** passando da 20 a 100 articoli.

Nessuna cache introdotta: il calcolo (parsing DOM di un body già in
memoria, per riga) è già economico: introdurla ora sarebbe prematuro
(nessun problema misurato da risolvere).

## 14. Limiti noti

- Puramente **lessicale**: due articoli concettualmente collegati ma
  senza vocabolario letterale condiviso non vengono mai suggeriti (nessun
  embedding/similarità semantica — fuori scope per design, non un bug).
- L'anchor resta sempre una singola parola, il titolo del target, o un
  concetto multi-parola dal registro §4 — mai una locuzione arbitraria
  costruita dai termini condivisi.
- Il registro concetti (§4) è piccolo e curato a mano: non scopre
  automaticamente nuovi concetti dal corpus (nessun clustering/NLP).
- "Anchor ambigui" (§10) rileva solo ambiguità **all'interno dello stesso
  articolo** — due articoli diversi che usano la stessa frase per
  destinazioni diverse non vengono confrontati tra loro (costo
  quadratico non giustificato per questa versione).
- L'audit classifica solo i link verso `/articolo/{slug}` — link verso
  altre pagine Kairus (categorie, home, pagine statiche) non fanno parte
  di questo audit (fuori perimetro: "collegamento ad articolo" ha sempre
  significato solo articolo→articolo in questo sistema, vedi
  `linkedArticleSlugsInBody()`).
- L'asimmetria A→B / B→A (un collegamento trovato in un verso ma non
  nell'altro) è ridotta ma non eliminata (dipende da dove nel testo
  compare il vocabolario condiviso) — limite preesistente, non toccato.

## 15. Rollback

Nessuna migration introdotta da questa missione (nessuna modifica allo
schema). Per tornare al comportamento pre-V2:

1. Revert dei file elencati in §2 aggiunti/modificati da questa PR.
2. Nessun dato da ripulire: `ArticleLinkSuggestion` non ha una colonna
   dedicata al match concettuale — il bonus V2 è già incluso nello stesso
   `confidence_score` di sempre, revertare il codice riporta lo score al
   comportamento precedente senza bisogno di una migrazione a ritroso.
3. Il comando `content:internal-link-audit` è puramente additivo
   (read-only): rimuoverlo non ha effetti collaterali su nient'altro.
