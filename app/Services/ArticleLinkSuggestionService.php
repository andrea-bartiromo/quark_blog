<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\ArticleSlugRedirect;
use App\Services\InternalLinking\ConceptCandidate;
use App\Services\InternalLinking\InternalLinkTemporalEligibility;
use App\Services\InternalLinking\ScientificConceptMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Genera suggerimenti di collegamento interno tra articoli con un ranking
 * deterministico e trasparente — nessuna chiamata AI esterna, nessun
 * embedding. Ogni suggerimento è sempre una PROPOSTA persistita con stato
 * 'proposed': non scrive mai nel body dell'articolo (quello è
 * App\Services\ArticleLinkInsertionService, invocato solo su azione umana
 * esplicita "Inserisci").
 *
 * Punteggio (0-100, soglia minima 40 per essere proposto — MIN_SCORE_THRESHOLD),
 * composto da quattro segnali indipendenti, sommati:
 *   - LEXICAL RELEVANCE — titolo: +50 se il TITOLO dell'articolo target
 *     compare per intero nel testo sorgente (segnale forte, quasi sempre
 *     >= soglia da solo);
 *   - LEXICAL RELEVANCE — termini: +15 per ciascun termine condiviso (fino
 *     a 3, +45 max) tra il testo sorgente e titolo+excerpt+un estratto del
 *     body del target (vedi extractTargetTerms());
 *   - SPECIFICITY (V2, Ago 2026) — ciascun termine condiviso vale i +15
 *     pieni solo se "specifico" (compare in meno del 20% del pool di
 *     candidati analizzato in questa chiamata — vedi
 *     GENERIC_TERM_DOCUMENT_FREQUENCY_RATIO); un termine "generico"
 *     ("nuove", "tecnologie", "settore" — onnipresenti in testi editoriali
 *     "stato dell'arte") vale solo +5 (TERM_MATCH_SCORE_GENERIC). Disattivo
 *     sotto MIN_CORPUS_SIZE_FOR_SPECIFICITY candidati (segnale statistico
 *     non significativo su un pool troppo piccolo);
 *   - CATEGORY BONUS — +10 se sorgente e target condividono la categoria
 *     (mai da solo sufficiente a superare la soglia).
 *
 * Esempi (vedi anche tests/Feature/InternalLinkingQualityTest.php):
 *   - Sole → Betelgeuse: termini specifici condivisi (betelgeuse,
 *     luminosità, supernova) → score alto, anchor "Betelgeuse" (specifico,
 *     non un sostantivo generico più lungo);
 *   - relatività → GPS: cross-category, termini specifici condivisi
 *     (relativistica, orologi, atomici) → suggerito nonostante categorie
 *     diverse (il bonus categoria non è mai un gate);
 *   - "tecnologia spaziale" → "tecnologia medica": condividono solo
 *     "nuove"/"tecnologie"/"prossimi" — tutti classificati generici nel
 *     pool → punteggio insufficiente, NON suggerito (V1 lo suggeriva
 *     erroneamente, score 45, anchor "tecnologie");
 *   - CRISPR-Cas9 → mRNA: nessun termine letteralmente condiviso nei testi
 *     → NON suggerito, limite lessicale accettato (nessuna similarità
 *     semantica in questa versione, per design).
 *
 * ANCHOR QUALITY (V2): tra i termini condivisi, l'anchor preferisce quelli
 * "specifici" (stesso segnale di cui sopra) — la lunghezza resta solo lo
 * spareggio finale fra termini della stessa classe, non più il criterio
 * primario (V1). L'anchor text proposta resta SEMPRE una frase già
 * presente nel testo sorgente (il titolo del target se vi compare,
 * altrimenti il termine condiviso più specifico trovato) — mai una frase
 * generata: "clicca qui" e simili restano strutturalmente impossibili.
 *
 * SIMMETRIA (V2): includere una porzione del body del target
 * (TARGET_BODY_EXCERPT_CHARS) nell'estrazione termini, oltre a
 * titolo+excerpt, riduce l'asimmetria per cui A→B trovava un
 * collegamento che B→A non trovava (il vocabolario condiviso compariva
 * nel body del target ma mai nel suo titolo/excerpt). Non elimina
 * l'asimmetria in ogni caso (dipende comunque da dove nel testo compare
 * il vocabolario condiviso), ma la riduce nei casi osservati empiricamente.
 *
 * NORMALIZZAZIONE MORFOLOGICA (V2): famiglie molto conservative
 * (satellite/satellitare/satelliti/satellitari) riconosciute solo tramite
 * un prefisso condiviso di almeno 8 caratteri (vedi
 * shareConservativeStem()) — non uno stemmer linguistico, nessuna regola
 * su suffissi/desinenze italiane.
 *
 * LIMITI NOTI, non risolti in questa versione (fuori scope, richiederebbero
 * nuova infrastruttura): nessuna similarità semantica/embedding (due
 * articoli concettualmente collegati ma senza vocabolario letterale
 * condiviso non vengono mai suggeriti); l'anchor resta sempre una singola
 * parola o il titolo del target, mai una locuzione multi-parola costruita
 * dai termini condivisi; l'asimmetria A→B/B→A non è eliminata in ogni
 * caso, solo ridotta.
 */
class ArticleLinkSuggestionService
{
    private const TITLE_MATCH_SCORE = 50;

    private const TERM_MATCH_SCORE = 15;

    private const MAX_SCORED_TERM_MATCHES = 3;

    private const CATEGORY_BONUS = 10;

    private const MIN_SCORE_THRESHOLD = 40;

    private const MIN_TERM_LENGTH = 4;

    /**
     * Soglia più bassa per i soli termini che contengono almeno una cifra
     * (GPT-5, H2O, 5G, CO2...): a 2-3 caratteri sono già identificatori
     * specifici, a differenza di una parola puramente alfabetica della
     * stessa lunghezza (quasi sempre una congiunzione/preposizione, già
     * coperta da STOPWORDS separatamente).
     */
    private const MIN_ALNUM_TERM_LENGTH = 2;

    private const MIN_TITLE_LENGTH_FOR_PHRASE_MATCH = 8;

    private const CONTEXT_WINDOW_CHARS = 60;

    /**
     * V2 — segnale di specificità lessicale (Ago 2026, audit su 32 casi
     * empirici). Un termine condiviso che ricorre in più di questa quota
     * del pool di candidati analizzato non è un segnale di pertinenza
     * reale ("nuove", "tecnologie", "settore" compaiono in praticamente
     * ogni articolo "stato dell'arte") — vedi buildDocumentFrequency().
     * Calcolato deterministicamente dal corpus dei candidati ad ogni
     * chiamata di analyzeForSource()/analyzeForNewTarget(), nessuna
     * persistenza, nessuna infrastruttura nuova.
     */
    private const GENERIC_TERM_DOCUMENT_FREQUENCY_RATIO = 0.20;

    /**
     * Con pochi candidati nel pool la frequenza documentale non è un
     * campione statisticamente significativo: con un solo candidato,
     * qualunque termine che condivide con il target avrebbe frequenza
     * 100% e verrebbe classificato generico anche se realmente
     * distintivo. Sotto questa soglia la classificazione resta disattivata
     * (tutti i termini "specifici", stesso comportamento di corpusSize=0)
     * — un sito con pochissimi articoli pubblicati si comporta come V1,
     * non peggio.
     */
    private const MIN_CORPUS_SIZE_FOR_SPECIFICITY = 5;

    /** Punteggio ridotto per un termine condiviso ma classificato generico (vedi sopra) — non zero: resta comunque un debole segnale di overlap, non un errore. */
    private const TERM_MATCH_SCORE_GENERIC = 5;

    /**
     * V2 — porzione di body del candidato TARGET inclusa nell'estrazione
     * termini, oltre a titolo+excerpt (che da soli causavano l'asimmetria
     * A→B / B→A documentata nell'audit: sourceTerms leggeva l'intero body,
     * targetTerms solo titolo+excerpt). Bounded per restare compatibile
     * con MAX_CANDIDATES=300 candidati per analisi — non l'intero body,
     * che per 300 candidati sarebbe un costo di memoria/CPU non
     * proporzionato al beneficio. 800 caratteri di testo semplice
     * approssimano l'introduzione editoriale, dove il vocabolario più
     * rappresentativo del pezzo compare tipicamente per primo (stile
     * "piramide rovesciata").
     */
    private const TARGET_BODY_EXCERPT_CHARS = 800;

    /**
     * V2 — normalizzazione morfologica MOLTO conservativa (famiglia
     * satellite/satellitare/satelliti/satellitari, osservata nell'audit).
     * Non uno stemmer: due termini sono considerati la stessa "famiglia"
     * SOLO se condividono un prefisso di almeno questa lunghezza E sono
     * entrambi lunghi almeno questo tanto — una soglia di 8 caratteri
     * rende le collisioni accidentali tra parole italiane non correlate
     * rare (parole che condividono 8+ caratteri iniziali sono quasi
     * sempre la stessa radice). Applicata solo al confronto tra termini
     * in scoreLink(), mai a extractTerms(): il tokenizer (#140) resta
     * invariato, e i termini restituiti/salvati come anchor restano
     * sempre quelli realmente presenti nel testo.
     */
    private const MIN_CONSERVATIVE_STEM_PREFIX_LENGTH = 8;

    /** Limite candidati per restare compatibile con FASE 8 (calcolo on-demand, mai su ogni keypress). */
    private const MAX_CANDIDATES = 300;

    /**
     * V2.1 (Codex, PR #165, P2 round 4, 5, 6 e 11) — tetto MASSIMO,
     * INDIPENDENTE, per i candidati scheduled temporalmente sicuri: una
     * quota SEPARATA e aggiuntiva rispetto a MAX_CANDIDATES (mai sottratta
     * ad essa, mai una funzione di quanti candidati scheduled sicuri
     * esistono davvero — round 6 e 11 hanno mostrato entrambi che qualunque
     * dipendenza tra le due quote riapre lo stesso problema in una forma
     * diversa). I pubblicati mantengono sempre l'intera MAX_CANDIDATES che
     * avevano prima di questa missione; il pool totale valutato per lo
     * scoring può quindi arrivare a MAX_CANDIDATES + questo valore (350) —
     * un aumento fisso e limitato, non una crescita indefinita. Il valore
     * resta comunque più piccolo di MAX_CANDIDATES: un calendario
     * editoriale realistico ha molti meno articoli scheduled-prima-di-
     * questa-source che pubblicati in tutta la storia del sito.
     */
    private const MAX_SCHEDULED_SAFE_CANDIDATES = 50;

    private const MAX_RETROACTIVE_SOURCE_CANDIDATES = 200;

    // Stopword italiane comuni: articoli, preposizioni, congiunzioni,
    // pronomi, verbi ausiliari/comuni, avverbi — escluse dall'estrazione
    // dei termini significativi perché troppo generiche per indicare una
    // reale pertinenza tematica.
    private const STOPWORDS = [
        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'dei', 'delle', 'degli',
        'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
        'del', 'dello', 'della', 'dei', 'degli', 'delle',
        'al', 'allo', 'alla', 'ai', 'agli', 'alle',
        'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle',
        'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
        'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
        'e', 'ed', 'o', 'od', 'ma', 'pero', 'quindi', 'perche', 'come', 'che', 'se',
        'anche', 'ancora', 'gia', 'mentre', 'dove', 'quando',
        'io', 'tu', 'lui', 'lei', 'noi', 'voi', 'loro',
        'questo', 'questa', 'questi', 'queste', 'quello', 'quella', 'quelli', 'quelle',
        // Pronomi possessivi: prima del red team review V2 (Ago 2026)
        // mancavano tutti i plurali (solo le forme singolari erano
        // presenti) — "suoi"/"sue" potevano contare come termine
        // "condiviso" tra due articoli senza alcuna relazione tematica
        // reale (vedi InternalLinkingQualityTest::
        // test_unrelated_articles_sharing_only_generic_words_are_not_suggested).
        'mio', 'mia', 'miei', 'mie', 'tuo', 'tua', 'tuoi', 'tue', 'suo', 'sua', 'suoi', 'sue',
        'nostro', 'nostra', 'nostri', 'nostre', 'vostro', 'vostra', 'vostri', 'vostre',
        'essere', 'avere', 'fare', 'dire', 'potere', 'dovere', 'volere', 'stare', 'andare', 'venire',
        'sono', 'era', 'erano', 'sara', 'saranno', 'ha', 'hanno', 'aveva', 'avevano', 'deve', 'devono', 'puo', 'possono',
        'non', 'piu', 'molto', 'poco', 'tutto', 'tutti', 'tutta', 'tutte', 'ogni',
        'alcuni', 'alcune', 'altro', 'altri', 'altra', 'altre',
        'stesso', 'stessa', 'stessi', 'stesse', 'cosi', 'qui', 'qua', 'li', 'la',
        'oggi', 'ieri', 'domani', 'sempre', 'mai', 'ora', 'adesso', 'dopo', 'prima',
        'sopra', 'sotto', 'dentro', 'fuori', 'verso', 'senza', 'contro', 'secondo', 'circa',
        'cioe', 'infatti', 'inoltre', 'tuttavia', 'dunque',
        'cui', 'chi', 'cosa', 'quale', 'quali', 'quanto', 'quanti', 'quanta', 'quante',

        // Audit qualità suggerimenti (Ago 2026): forme verbali generiche
        // e aggettivi di paragone/giudizio — nessun segnale tematico,
        // ma abbastanza lunghi da vincere lo scoring o essere scelti come
        // anchor al posto di un termine realmente distintivo condiviso
        // ("potrebbe", "significa", "stanno", "maggiore" osservati in uso
        // editoriale reale).
        'potrebbe', 'potrebbero', 'dovrebbe', 'dovrebbero', 'vorrebbe', 'vorrebbero',
        'sta', 'stanno', 'stava', 'stavano',
        'sia', 'siano', 'sarebbe', 'sarebbero',
        'abbia', 'abbiano', 'avrebbe', 'avrebbero',
        'fa', 'fanno', 'farebbe', 'farebbero',
        'dice', 'dicono', 'direbbe',
        'significa', 'significano', 'significherebbe',
        'utilizza', 'utilizzano', 'utilizzato', 'utilizzata', 'utilizzati', 'utilizzate',
        'trova', 'trovano', 'trovato', 'trovata',
        'rappresenta', 'rappresentano',
        'consente', 'consentono', 'permette', 'permettono',
        'mostra', 'mostrano', 'sembra', 'sembrano',
        'maggiore', 'maggiori', 'minore', 'minori', 'migliore', 'migliori', 'peggiore', 'peggiori',
        'importante', 'importanti', 'principale', 'principali',
        'diverso', 'diversa', 'diversi', 'diverse',
        'possibile', 'possibili', 'necessario', 'necessaria', 'necessari', 'necessarie',
    ];

    /** Lunghezza minima perché un termine in "-mente" sia escluso come avverbio di modo generico (esclude il sostantivo "mente" da solo). */
    private const MIN_LENGTH_FOR_MENTE_ADVERB = 7;

    /**
     * V2 (Internal Linking V2, missione dedicata) — bonus per un CONCETTO
     * scientifico multi-parola noto (config/scientific_concepts.php,
     * es. "buco nero", "relatività generale") condiviso letteralmente tra
     * sorgente e target — vedi App\Services\InternalLinking\
     * ScientificConceptMatcher. Un concetto multi-parola è un segnale più
     * specifico di un singolo termine condiviso (TERM_MATCH_SCORE): un
     * numero di punti più alto lo riflette, senza sostituire i segnali
     * esistenti (si somma, non li rimpiazza).
     */
    private const CONCEPT_MATCH_SCORE = 20;

    /** Al più questi concetti contano ai fini del punteggio — "un buon segnale forte" è sufficiente, non serve premiare la ripetizione dello stesso tipo di segnale (stessa filosofia di MAX_SCORED_TERM_MATCHES). */
    private const MAX_SCORED_CONCEPTS = 2;

    public function __construct(
        private readonly ArticleLinkInsertionService $insertionService,
        private readonly ScientificConceptMatcher $conceptMatcher = new ScientificConceptMatcher,
        private readonly InternalLinkTemporalEligibility $temporalEligibility = new InternalLinkTemporalEligibility,
    ) {}

    /**
     * Analizza $source contro tutti gli altri articoli temporalmente
     * eleggibili (vedi InternalLinkTemporalEligibility) e persiste/aggiorna
     * i suggerimenti 'proposed'. Non tocca mai righe già 'accepted' o
     * 'ignored' (FASE 7: non riproporre continuamente).
     *
     * Internal Linking V2.1 — i candidati non sono più solo gli articoli
     * già pubblicati: se $source è essa stessa 'scheduled', anche un
     * articolo 'scheduled' con published_at STRETTAMENTE precedente a
     * quello di $source entra nel pool, perché sarà già pubblico quando
     * $source lo diventerà (vedi InternalLinkTemporalEligibility per la
     * regola completa e il ragionamento).
     *
     * @return Collection<int, ArticleLinkSuggestion>
     */
    public function analyzeForSource(Article $source): Collection
    {
        $sourcePlainBody = $this->plainText((string) $source->body);

        if (trim($sourcePlainBody) === '') {
            return collect();
        }

        $sourceTerms = $this->extractTerms($sourcePlainBody);

        // V2 (Internal Linking V2) — calcolato UNA VOLTA qui, non dentro
        // scoreLink(): $sourcePlainBody non cambia tra un candidato e
        // l'altro in questo metodo (a differenza di analyzeForNewTarget(),
        // dove ogni candidato ha un proprio body e quindi un proprio
        // scan), quindi ripetere la scansione dei concetti fino a
        // MAX_CANDIDATES volte per la STESSA stringa sarebbe lavoro
        // sprecato — stesso principio già applicato a $sourceTerms sopra.
        $sourceConceptMatches = $this->conceptMatcher->conceptsPresentIn($sourcePlainBody);

        $alreadyLinkedSlugs = $this->insertionService->linkedArticleSlugsInBody((string) $source->body);

        // Article::published()/scopeScheduledSafeAsLinkTargetFor() sono
        // pre-filtri SQL (evitano di caricare — e di far competere per uno
        // slot nel LIMIT sotto — candidati che non potrebbero mai essere
        // eleggibili). Il filter() subito dopo il fetch riapplica la STESSA
        // regola (isTargetSafeForSource()) come garanzia definitiva sui
        // modelli realmente caricati: la correttezza non dipende dalla
        // query SQL essere scritta esattamente giusta, solo dalla policy
        // PHP (single source of truth), che l'audit usa allo stesso modo.
        //
        // Codex (PR #165, P2 round 4, 5, 6 e 11): una singola query con
        // ORDER BY + LIMIT condiviso tra pubblicati e scheduled sicuri non
        // può mai essere corretta in entrambe le direzioni contemporaneamente
        // — qualunque criterio di ordinamento fa sì che UN gruppo, se
        // abbastanza numeroso, riempia da solo l'intera finestra del LIMIT
        // ed escluda sistematicamente l'altro (round 4: senza priorità gli
        // scheduled sicuri, sempre nel futuro, scalzavano i pubblicati;
        // round 5: dando priorità ai pubblicati, un corpus maturo con
        // >= MAX_CANDIDATES pubblicati scalzava a sua volta ogni scheduled
        // sicuro). Anche far dipendere la quota dei pubblicati dal NUMERO
        // di scheduled sicuri trovati (round 6) resta scorretto (round 11):
        // quel numero non dice nulla sulla RILEVANZA dei candidati scheduled
        // — con 50 scheduled sicuri ma irrilevanti per questa query, la
        // quota pubblicati si riduceva comunque a 250, scalzando un
        // pubblicato genuinamente pertinente in posizione 251-300 ancora
        // prima che venga mai valutato per la rilevanza. L'unica soluzione
        // davvero corretta è due quote COMPLETAMENTE INDIPENDENTI, nessuna
        // funzione dell'altra: i pubblicati mantengono sempre l'intera
        // MAX_CANDIDATES che avevano prima di questa missione, gli scheduled
        // sicuri restano un tetto SEPARATO e aggiuntivo di
        // MAX_SCHEDULED_SAFE_CANDIDATES — il pool totale valutato per lo
        // scoring può quindi arrivare a MAX_CANDIDATES +
        // MAX_SCHEDULED_SAFE_CANDIDATES (350), un aumento fisso e limitato,
        // non una crescita indefinita, e comunque una singola query per
        // gruppo (nessun N+1).
        $publishedCandidates = Article::published()
            ->where('id', '!=', $source->id)
            ->limit(self::MAX_CANDIDATES)
            ->get(['id', 'title', 'slug', 'excerpt', 'category', 'body', 'status', 'published_at']);

        $scheduledSafeCandidates = collect();

        if ($source->isScheduled() && $source->published_at !== null) {
            $scheduledSafeCandidates = Article::query()
                ->scheduledSafeAsLinkTargetFor($source)
                ->where('id', '!=', $source->id)
                ->orderByDesc('published_at')
                ->limit(self::MAX_SCHEDULED_SAFE_CANDIDATES)
                ->get(['id', 'title', 'slug', 'excerpt', 'category', 'body', 'status', 'published_at']);
        }

        $candidates = $publishedCandidates->concat($scheduledSafeCandidates)
            ->filter(fn (Article $candidate) => $this->temporalEligibility->isTargetSafeForSource($source, $candidate))
            ->values();

        // V2 — un'unica passata sull'intero pool di candidati (stessa
        // Collection già caricata sopra, nessuna query aggiuntiva) per
        // calcolare quanti candidati condividono ciascun termine: la base
        // del segnale di specificità lessicale usato da scoreLink() più
        // sotto (vedi GENERIC_TERM_DOCUMENT_FREQUENCY_RATIO).
        $documentFrequency = $this->buildDocumentFrequency($candidates);
        $corpusSize = $candidates->count();

        // targetArticle:id,status,published_at eager-caricato qui (non in
        // un lazy load dentro il loop sotto, N+1): serve al controllo di
        // staleness temporale subito dopo.
        $existing = ArticleLinkSuggestion::forSource($source->id)
            ->with('targetArticle:id,status,published_at')
            ->get()
            ->keyBy('target_article_id');

        // Codex (PR #165, P1): un suggerimento 'proposed' il cui target ha
        // perso l'eleggibilità temporale da quando fu proposto (riprogrammato
        // DOPO $source, o retrocesso a bozza/revisione) non deve restare
        // "proposed" indefinitamente in attesa che il loop sotto lo
        // incontri — per costruzione non lo incontrerà mai più, perché il
        // filter() sopra lo esclude già a monte da $candidates. Senza
        // questo passaggio esplicito resterebbe silenziosamente inseribile
        // (ArticleLinkSuggestionController::insert() lo riverifica comunque,
        // vedi lì, ma un suggerimento stantio non deve nemmeno restare
        // visibile nel pannello).
        foreach ($existing as $existingSuggestion) {
            if (! $existingSuggestion->isActionable()) {
                continue;
            }

            $target = $existingSuggestion->targetArticle;

            if ($target === null || ! $this->temporalEligibility->isTargetSafeForSource($source, $target)) {
                $this->supersedeIfActionable($existingSuggestion);
            }
        }

        $results = collect();

        foreach ($candidates as $candidate) {
            $existingSuggestion = $existing->get($candidate->id);

            if (in_array($candidate->slug, $alreadyLinkedSlugs, true)) {
                $this->supersedeIfActionable($existingSuggestion);

                continue;
            }

            // Una decisione già presa dalla redazione non viene mai
            // ricalcolata o riproposta.
            if ($existingSuggestion && ! $existingSuggestion->isActionable()) {
                continue;
            }

            $match = $this->scoreLink($source->category, (string) $source->body, $sourcePlainBody, $sourceTerms, $candidate, $sourceConceptMatches, $documentFrequency, $corpusSize);

            if ($match === null) {
                // Un suggerimento "proposed" che non passa più la soglia
                // (es. testo modificato) viene marcato superato, non
                // lasciato a proporre un collegamento non più pertinente.
                $this->supersedeIfActionable($existingSuggestion);

                continue;
            }

            $attributes = [
                'anchor_text' => $match['anchor'],
                'context_excerpt' => $match['context'],
                'reason' => $match['reason'],
                'confidence_score' => $match['score'],
                'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
            ];

            // updateOrCreate (non un controllo di esistenza seguito da un
            // create separato) resta corretto anche se due richieste
            // "Analizza" concorrenti per lo stesso articolo colpiscono la
            // stessa coppia sorgente/target: senza questo, un doppio click
            // potrebbe far scattare il vincolo unique(source,target) e far
            // fallire la richiesta con un errore 500.
            $results->push(ArticleLinkSuggestion::updateOrCreate(
                ['source_article_id' => $source->id, 'target_article_id' => $candidate->id],
                $attributes
            ));
        }

        return $results;
    }

    /**
     * FASE 6 — modalità retroattiva: per un articolo appena pubblicato
     * ($target), individua quali articoli GIÀ pubblicati potrebbero
     * collegarlo dal proprio testo. Stessa logica di scoring, ruoli
     * sorgente/target invertiti nel loop: qui il candidato è la sorgente
     * (serve il suo body) e $target resta fisso.
     *
     * @return Collection<int, ArticleLinkSuggestion>
     */
    public function analyzeForNewTarget(Article $target): Collection
    {
        // Un'unica query per tutti i suggerimenti esistenti verso $target
        // (invece di una query per candidato dentro il loop — N+1) e
        // cursor() per non tenere in memoria contemporaneamente i body HTML
        // di tutti i candidati (fino a MAX_RETROACTIVE_SOURCE_CANDIDATES).
        // NON lazy(): pagina internamente con forPage() e IGNORA il limit()
        // già applicato alla query, continuando a richiedere pagine finché
        // non esaurisce l'intera tabella — vanificherebbe silenziosamente
        // MAX_RETROACTIVE_SOURCE_CANDIDATES. cursor() usa invece un singolo
        // statement/generatore PHP che rispetta LIMIT/OFFSET.
        $existingByCandidateId = ArticleLinkSuggestion::where('target_article_id', $target->id)
            ->get()
            ->keyBy('source_article_id');

        $candidates = Article::published()
            ->where('id', '!=', $target->id)
            ->orderByDesc('published_at')
            ->limit(self::MAX_RETROACTIVE_SOURCE_CANDIDATES)
            ->select(['id', 'title', 'slug', 'excerpt', 'category', 'body'])
            ->cursor();

        $targetAsCandidate = (object) [
            'id' => $target->id,
            'title' => $target->title,
            'slug' => $target->slug,
            'excerpt' => $target->excerpt,
            'category' => $target->category,
            // V2 — $target è un singolo Article già in memoria (non parte
            // del cursor() sui candidati sotto): includerne il body non
            // introduce alcun costo aggiuntivo, e risolve la stessa
            // asimmetria di analyzeForSource() anche su questo percorso.
            'body' => $target->body,
        ];

        $results = collect();

        foreach ($candidates as $candidateSource) {
            $sourcePlainBody = $this->plainText((string) $candidateSource->body);

            if (trim($sourcePlainBody) === '') {
                continue;
            }

            $alreadyLinkedSlugs = $this->insertionService->linkedArticleSlugsInBody((string) $candidateSource->body);
            $existingSuggestion = $existingByCandidateId->get($candidateSource->id);

            if (in_array($target->slug, $alreadyLinkedSlugs, true)) {
                $this->supersedeIfActionable($existingSuggestion);

                continue;
            }

            if ($existingSuggestion && ! $existingSuggestion->isActionable()) {
                continue;
            }

            $sourceTerms = $this->extractTerms($sourcePlainBody);
            $sourceConceptMatches = $this->conceptMatcher->conceptsPresentIn($sourcePlainBody);
            $match = $this->scoreLink($candidateSource->category, (string) $candidateSource->body, $sourcePlainBody, $sourceTerms, $targetAsCandidate, $sourceConceptMatches);

            if ($match === null) {
                $this->supersedeIfActionable($existingSuggestion);

                continue;
            }

            $attributes = [
                'anchor_text' => $match['anchor'],
                'context_excerpt' => $match['context'],
                'reason' => $match['reason'],
                'confidence_score' => $match['score'],
                'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
            ];

            // updateOrCreate: al sicuro anche in caso di richieste
            // concorrenti sulla stessa coppia (vincolo unique).
            $results->push(ArticleLinkSuggestion::updateOrCreate(
                ['source_article_id' => $candidateSource->id, 'target_article_id' => $target->id],
                $attributes
            ));
        }

        return $results;
    }

    /**
     * @param  object{id:int,title:string,slug:string,excerpt:?string,category:string,body?:?string}  $candidateTarget
     * @param  array<int, ConceptCandidate>  $sourceConceptMatches  V2 — concetti trovati in $sourcePlainBody, calcolato dal chiamante UNA VOLTA (non qui): $sourcePlainBody resta lo stesso per ogni candidato dentro analyzeForSource(), ricalcolarlo per ciascuno dei fino a MAX_CANDIDATES candidati sarebbe lavoro ripetuto e sprecato (stesso principio già applicato a $sourceTerms).
     * @param  array<string,int>  $documentFrequency  V2 — quanti candidati del pool corrente contengono ciascun termine (vedi buildDocumentFrequency()). Vuoto = nessuna classificazione generico/specifico, tutti i termini condivisi restano a punteggio pieno (fallback usato da analyzeForNewTarget(), che itera i candidati via cursor() e non può costruire questa mappa senza una seconda passata sul DB — vedi docblock di analyzeForNewTarget()).
     * @param  int  $corpusSize  Dimensione del pool usato per calcolare $documentFrequency — 0 disabilita la classificazione (stesso motivo sopra).
     * @return array{score:int,anchor:string,context:?string,reason:string}|null
     */
    private function scoreLink(string $sourceCategory, string $sourceBodyHtml, string $sourcePlainBody, array $sourceTerms, object $candidateTarget, array $sourceConceptMatches, array $documentFrequency = [], int $corpusSize = 0): ?array
    {
        $score = 0;
        $titleMatched = false;
        $titleOccurrence = null;

        $title = trim((string) $candidateTarget->title);

        if (mb_strlen($title, 'UTF-8') >= self::MIN_TITLE_LENGTH_FOR_PHRASE_MATCH) {
            $titleOccurrence = $this->findPhrase($sourcePlainBody, $title);

            if ($titleOccurrence !== null) {
                $score += self::TITLE_MATCH_SCORE;
                $titleMatched = true;
            }
        }

        // V2 — titolo+excerpt+porzione di body (non più solo titolo+excerpt,
        // vedi TARGET_BODY_EXCERPT_CHARS): risolve l'asimmetria A→B / B→A
        // documentata nell'audit, dove il vocabolario condiviso compariva
        // nel body del target ma mai nel suo titolo/excerpt.
        $targetTerms = $this->extractTargetTerms($candidateTarget);

        // V2 — intersezione esatta PIÙ famiglie morfologiche molto
        // conservative (satellite/satellitare/satelliti/satellitari): vedi
        // shareConservativeStem().
        $sharedTerms = $this->sharedTerms($targetTerms, $sourceTerms);

        // V2 — specifico prima di generico (segnale di document frequency),
        // lunghezza solo come spareggio finale fra termini della stessa
        // classe: sostituisce l'ordinamento V1 (solo lunghezza), che
        // permetteva a un termine generico ma lungo di vincere su uno più
        // corto ma realmente distintivo.
        $rankedTerms = $this->rankSharedTerms($sharedTerms, $documentFrequency, $corpusSize);

        $scoredTerms = array_slice($rankedTerms, 0, self::MAX_SCORED_TERM_MATCHES);
        $score += array_sum(array_map(fn (array $t) => $t['points'], $scoredTerms));
        $matchedTerms = array_column($scoredTerms, 'term');

        // V2 (Internal Linking V2) — concetti scientifici multi-parola
        // (config/scientific_concepts.php) menzionati per intero SIA nel
        // testo sorgente SIA nel target: un segnale più specifico di un
        // singolo termine condiviso, mai un sostituto (si somma).
        // $sourceConceptMatches è un parametro (vedi sopra), non ricalcolato qui.
        $sourceConceptCanonicals = array_values(array_unique(array_map(
            fn (ConceptCandidate $c) => $c->canonicalTerm,
            $sourceConceptMatches
        )));
        $targetConceptCanonicals = $this->conceptMatcher->canonicalTermsPresentIn($this->targetPlainText($candidateTarget));
        $sharedConceptCanonicals = array_slice(
            array_values(array_intersect($sourceConceptCanonicals, $targetConceptCanonicals)),
            0,
            self::MAX_SCORED_CONCEPTS
        );
        $score += count($sharedConceptCanonicals) * self::CONCEPT_MATCH_SCORE;

        $categoryMatched = $sourceCategory === $candidateTarget->category;

        if ($categoryMatched) {
            $score += self::CATEGORY_BONUS;
        }

        $score = min(100, $score);

        if ($score < self::MIN_SCORE_THRESHOLD) {
            return null;
        }

        // L'anchor scelta deve essere non solo presente nel testo appiattito
        // usato per lo scoring, ma davvero inseribile: un singolo nodo di
        // testo idoneo, non spezzato da un tag inline (es. <strong>) e non
        // dentro un link/titolo/citazione esistente — altrimenti "Inserisci"
        // fallirebbe sempre per un suggerimento che sembrava valido.
        // Si prova prima il titolo (se ha contribuito al punteggio), poi i
        // termini condivisi in ordine di specificità (V2: non più solo
        // lunghezza) dal più al meno specifico.
        $anchorCandidates = [];

        if ($titleOccurrence !== null) {
            $anchorCandidates[] = $titleOccurrence;
        }

        // V2 — un concetto multi-parola condiviso è un'anchor più
        // specifica/descrittiva di un singolo termine (FASE 23,
        // accessibilità: l'anchor deve restare comprensibile fuori
        // contesto) — provato subito dopo il titolo, prima dei termini
        // singoli. Il testo resta SEMPRE quello verbatim trovato nella
        // sorgente ($c->matchedText, es. "buchi neri"), mai la forma
        // canonica.
        foreach ($sourceConceptMatches as $conceptMatch) {
            if (in_array($conceptMatch->canonicalTerm, $sharedConceptCanonicals, true)) {
                $anchorCandidates[] = ['position' => $conceptMatch->position, 'text' => $conceptMatch->matchedText];
            }
        }

        foreach ($rankedTerms as $ranked) {
            $occurrence = $this->findPhrase($sourcePlainBody, $ranked['term']);

            if ($occurrence !== null) {
                $anchorCandidates[] = $occurrence;
            }
        }

        $anchor = null;
        $anchorPosition = null;

        foreach ($anchorCandidates as $occurrence) {
            if ($this->insertionService->canInsert($sourceBodyHtml, $occurrence['text'])) {
                $anchor = $occurrence['text'];
                $anchorPosition = $occurrence['position'];

                break;
            }
        }

        if ($anchor === null) {
            return null;
        }

        return [
            'score' => $score,
            'anchor' => $anchor,
            'context' => $this->buildContextExcerpt($sourcePlainBody, $anchorPosition, mb_strlen($anchor, 'UTF-8')),
            'reason' => $this->buildReason($titleMatched, $matchedTerms, $sharedConceptCanonicals, $categoryMatched, $candidateTarget->category),
        ];
    }

    /**
     * V2 — termini del ruolo "target": titolo + excerpt (invariato da V1)
     * più una porzione limitata del body in chiaro, se disponibile (vedi
     * TARGET_BODY_EXCERPT_CHARS). $candidate->body è assente/null per i
     * candidati caricati da analyzeForNewTarget() nel ruolo di sorgente
     * (quelli usano già il body intero altrove) — qui serve solo quando
     * l'oggetto gioca il ruolo di TARGET.
     */
    private function extractTargetTerms(object $candidateTarget): array
    {
        return $this->extractTerms($this->targetPlainText($candidateTarget));
    }

    /**
     * V2 (Internal Linking V2) — testo semplice del ruolo "target" (titolo +
     * excerpt + porzione di body, vedi extractTargetTerms()), estratto qui
     * come stringa invece che già tokenizzato: ScientificConceptMatcher
     * lavora su FRASI multi-parola, non sui singoli termini restituiti da
     * extractTerms() — non può riutilizzare l'array di token già tokenizzati
     * senza perdere l'adiacenza delle parole che compongono un concetto
     * ("buco nero" tokenizzato diventerebbe due termini indipendenti,
     * indistinguibile da "buco" e "nero" comparsi altrove nel testo senza
     * relazione tra loro).
     */
    private function targetPlainText(object $candidateTarget): string
    {
        $bodyExcerpt = '';

        if (! empty($candidateTarget->body)) {
            $bodyExcerpt = mb_substr(
                $this->plainText((string) $candidateTarget->body),
                0,
                self::TARGET_BODY_EXCERPT_CHARS,
                'UTF-8'
            );
        }

        return ($candidateTarget->title ?? '').' '.($candidateTarget->excerpt ?? '').' '.$bodyExcerpt;
    }

    /**
     * V2 — costruisce la mappa "termine => in quanti candidati compare",
     * base del segnale di specificità lessicale. Una sola passata sulla
     * Collection già caricata in memoria da analyzeForSource() (nessuna
     * query aggiuntiva): per ogni candidato vengono estratti gli stessi
     * targetTerms che scoreLink() userebbe se quel candidato fosse il
     * target di un confronto, così la mappa è coerente con ciò che viene
     * effettivamente confrontato.
     *
     * @param  Collection<int, object>  $candidates
     * @return array<string, int>
     */
    private function buildDocumentFrequency(Collection $candidates): array
    {
        $frequency = [];

        foreach ($candidates as $candidate) {
            foreach (array_unique($this->extractTargetTerms($candidate)) as $term) {
                $frequency[$term] = ($frequency[$term] ?? 0) + 1;
            }
        }

        return $frequency;
    }

    /**
     * @return array<int, string> termini condivisi (esatti + famiglie morfologiche conservative), senza ordine specifico
     */
    private function sharedTerms(array $targetTerms, array $sourceTerms): array
    {
        $matched = array_values(array_intersect($targetTerms, $sourceTerms));

        // V2 — famiglia morfologica conservativa: un termine del target
        // senza corrispondenza esatta può comunque condividere un prefisso
        // lungo con un termine sorgente. Si usa sempre il termine SORGENTE
        // (deve trovarsi letteralmente nel body sorgente per poter
        // diventare anchor — invariante preesistente mai violato).
        foreach ($targetTerms as $targetTerm) {
            if (in_array($targetTerm, $matched, true)) {
                continue;
            }

            foreach ($sourceTerms as $sourceTerm) {
                if ($this->shareConservativeStem($targetTerm, $sourceTerm)) {
                    $matched[] = $sourceTerm;

                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * V2 — normalizzazione morfologica MOLTO conservativa (vedi
     * MIN_CONSERVATIVE_STEM_PREFIX_LENGTH): NON uno stemmer linguistico,
     * nessuna regola su suffissi/desinenze italiane. Due termini sono
     * considerati la stessa famiglia solo se sono entrambi lunghi almeno
     * la soglia E condividono un prefisso di almeno la stessa lunghezza —
     * una soglia di 8 caratteri rende estremamente rare le collisioni tra
     * parole italiane non correlate (parole che condividono 8+ caratteri
     * iniziali sono quasi sempre la stessa radice: "satellite"/
     * "satellitare"/"satelliti"/"satellitari" condividono "satellit",
     * 8 caratteri). Applicata solo qui, mai a extractTerms(): il
     * tokenizer resta quello di #140, invariato.
     */
    private function shareConservativeStem(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $prefixLength = self::MIN_CONSERVATIVE_STEM_PREFIX_LENGTH;

        if (mb_strlen($a, 'UTF-8') < $prefixLength || mb_strlen($b, 'UTF-8') < $prefixLength) {
            return false;
        }

        return mb_substr($a, 0, $prefixLength, 'UTF-8') === mb_substr($b, 0, $prefixLength, 'UTF-8');
    }

    /**
     * V2 — classifica ogni termine condiviso come specifico o generico in
     * base a quanti candidati del pool corrente lo contengono (vedi
     * GENERIC_TERM_DOCUMENT_FREQUENCY_RATIO), poi ordina: specifici prima
     * di generici, lunghezza decrescente come spareggio solo fra termini
     * della stessa classe. $corpusSize === 0 (nessuna mappa disponibile,
     * vedi scoreLink()) rende ogni termine "specifico" — nessuna
     * classificazione, punteggio pieno per tutti, comportamento identico a
     * V1.
     *
     * @return array<int, array{term:string, points:int, is_generic:bool}>
     */
    private function rankSharedTerms(array $sharedTerms, array $documentFrequency, int $corpusSize): array
    {
        $ranked = array_map(function (string $term) use ($documentFrequency, $corpusSize) {
            $frequency = $documentFrequency[$term] ?? 0;
            $isGeneric = $corpusSize >= self::MIN_CORPUS_SIZE_FOR_SPECIFICITY
                && ($frequency / $corpusSize) >= self::GENERIC_TERM_DOCUMENT_FREQUENCY_RATIO;

            return [
                'term' => $term,
                'is_generic' => $isGeneric,
                'points' => $isGeneric ? self::TERM_MATCH_SCORE_GENERIC : self::TERM_MATCH_SCORE,
            ];
        }, $sharedTerms);

        usort($ranked, function (array $a, array $b) {
            if ($a['is_generic'] !== $b['is_generic']) {
                return $a['is_generic'] <=> $b['is_generic'];
            }

            return mb_strlen($b['term'], 'UTF-8') <=> mb_strlen($a['term'], 'UTF-8');
        });

        return $ranked;
    }

    /**
     * @return array{position:int,text:string}|null
     */
    private function findPhrase(string $haystack, string $phrase): ?array
    {
        $phrase = trim($phrase);

        if ($phrase === '') {
            return null;
        }

        $position = mb_stripos($haystack, $phrase, 0, 'UTF-8');

        if ($position === false) {
            return null;
        }

        return [
            'position' => $position,
            'text' => mb_substr($haystack, $position, mb_strlen($phrase, 'UTF-8'), 'UTF-8'),
        ];
    }

    private function buildContextExcerpt(string $plainText, int $position, int $length): string
    {
        $totalLength = mb_strlen($plainText, 'UTF-8');
        $start = max(0, $position - self::CONTEXT_WINDOW_CHARS);
        $end = min($totalLength, $position + $length + self::CONTEXT_WINDOW_CHARS);

        $excerpt = trim(mb_substr($plainText, $start, $end - $start, 'UTF-8'));

        return ($start > 0 ? '… ' : '').$excerpt.($end < $totalLength ? ' …' : '');
    }

    /**
     * @param  array<int, string>  $matchedConcepts  forme canoniche (config/scientific_concepts.php) condivise, es. ["buco nero"]
     */
    private function buildReason(bool $titleMatched, array $matchedTerms, array $matchedConcepts, bool $categoryMatched, string $category): string
    {
        $parts = [];

        if ($titleMatched) {
            $parts[] = 'il titolo dell\'articolo collegato compare nel testo';
        }

        if (! empty($matchedConcepts)) {
            $parts[] = 'concetto scientifico riconosciuto: '.implode(', ', $matchedConcepts);
        }

        if (! empty($matchedTerms)) {
            $parts[] = 'termini in comune: '.implode(', ', $matchedTerms);
        }

        if ($categoryMatched) {
            $parts[] = 'stessa categoria: '.(config('laboratorio.categories.'.$category, $category));
        }

        return ucfirst(implode('; ', $parts));
    }

    /**
     * @return array<int, string> parole/termini unici, minuscoli, senza stopword
     */
    private function extractTerms(string $text): array
    {
        // V3 — il case ORIGINALE viene preservato fino a dopo la
        // tokenizzazione (l'abbassamento a minuscolo diventa per-token più
        // sotto, non più sull'intero testo qui): serve a riconoscere un
        // acronimo corto tutto maiuscolo (vedi isShortUppercaseAcronym())
        // prima che quell'informazione vada persa. La regex di
        // tokenizzazione stessa è invariata (l'uso di \p{L}/\p{N} non
        // dipende dal case).
        $text = $this->normalizeUnicodePunctuation($this->plainText($text));

        // Il token inizia e finisce sempre su un carattere alfanumerico
        // (mai un trattino/apostrofo pendente): a differenza della regex
        // precedente ('\p{L}[\p{L}\'-]*', solo lettere), questa include
        // anche le cifre nel corpo del token — necessario per preservare
        // identificatori alfanumerici come "gpt-5", "covid-19", "h2o", "5g"
        // invece di troncarli al primo carattere numerico.
        preg_match_all("/[\\p{L}\\p{N}](?:[\\p{L}\\p{N}'-]*[\\p{L}\\p{N}])?/u", $text, $matches);

        $terms = [];

        foreach ($matches[0] ?? [] as $rawWord) {
            $isShortUppercaseAcronym = $this->isShortUppercaseAcronym($rawWord);

            $word = mb_strtolower($rawWord, 'UTF-8');
            $word = $this->stripItalianElision($word);

            if ($word === '') {
                continue;
            }

            $normalized = $this->stripAccents($word);
            $hasDigit = (bool) preg_match('/\p{N}/u', $word);

            // Un numero puro isolato ("11", "2026") non e' mai una parola
            // chiave, indipendentemente dalla lunghezza — invariante
            // preesistente, preservato: la regex ora accetta anche cifre
            // nel corpo del token, ma un token senza ALCUNA lettera resta
            // sempre escluso.
            if (preg_match('/\p{L}/u', $word) !== 1) {
                continue;
            }

            $minLength = $hasDigit ? self::MIN_ALNUM_TERM_LENGTH : self::MIN_TERM_LENGTH;

            // V3 — un acronimo corto tutto maiuscolo nel testo originale
            // (DNA, RNA, ESA, AI, ML, EU, ISS...) bypassa la soglia minima
            // ordinaria: vedi isShortUppercaseAcronym() per il
            // ragionamento completo. Sotto comunque a STOPWORDS/regola
            // -mente come qualunque altro termine — nessuna eccezione a
            // quei controlli.
            if (! $isShortUppercaseAcronym && mb_strlen($word, 'UTF-8') < $minLength) {
                continue;
            }

            if (in_array($normalized, self::STOPWORDS, true)) {
                continue;
            }

            // Avverbi di modo in "-mente" (profondamente, chiaramente, ...):
            // pattern morfologico italiano affidabile, sempre un
            // modificatore generico, mai un segnale tematico.
            if (mb_strlen($normalized, 'UTF-8') >= self::MIN_LENGTH_FOR_MENTE_ADVERB && str_ends_with($normalized, 'mente')) {
                continue;
            }

            $terms[$word] = true;
        }

        return array_keys($terms);
    }

    /**
     * Str::ascii() (non iconv con //TRANSLIT) perché la traslitterazione
     * di iconv dipende dalla libreria di sistema e dalla locale — sotto
     * musl (es. Alpine) //TRANSLIT non è supportato, e in locale "C" può
     * restituire "?" al posto della lettera accentata, facendo sfuggire
     * stopword accentate al confronto con STOPWORDS in modo diverso da
     * ambiente ad ambiente.
     */
    private function stripAccents(string $word): string
    {
        return mb_strtolower(Str::ascii($word));
    }

    /**
     * Tokenizer V3 (consolidamento post-#142) — normalizza le varianti
     * Unicode di trattino e apice alle rispettive forme ASCII PRIMA che
     * la regex di tokenizzazione (invariata da #140) veda il testo.
     * Puro preprocessing ortografico, non una regola di matching: non
     * introduce alcuna nuova classe di caratteri accettata dal tokenizer,
     * si limita a far sì che varianti tipografiche del "-" e dell'apice
     * — plausibili da copia-incolla editoriale via Word/Google Docs, che
     * le sostituiscono automaticamente — producano lo STESSO risultato
     * della loro controparte ASCII, invece di spezzare silenziosamente
     * il token (vedi ArticleLinkTokenizerV3Test per la caratterizzazione
     * del comportamento precedente).
     *
     * Trattini normalizzati a "-" (U+002D): U+2010 HYPHEN, U+2011
     * NON-BREAKING HYPHEN, U+2012 FIGURE DASH, U+2013 EN DASH, U+2014 EM
     * DASH, U+2212 MINUS SIGN. Tutti questi, se usati come punteggiatura
     * (circondati da spazi, il loro uso tipografico standard per un
     * inciso), restano separatori di token esattamente come oggi — la
     * normalizzazione ha effetto solo quando compaiono ADIACENTI a
     * caratteri alfanumerici, l'unico caso in cui oggi spezzano un
     * identificatore che dovrebbe restare unito.
     *
     * Apice normalizzato a "'" (U+0027): U+2019 RIGHT SINGLE QUOTATION
     * MARK, l'apice tipografico che Word/Google Docs inseriscono al posto
     * dell'apice dritto per le elisioni italiane (dell'universo).
     */
    private function normalizeUnicodePunctuation(string $text): string
    {
        return strtr($text, [
            "\u{2010}" => '-',
            "\u{2011}" => '-',
            "\u{2012}" => '-',
            "\u{2013}" => '-',
            "\u{2014}" => '-',
            "\u{2212}" => '-',
            "\u{2019}" => "'",
        ]);
    }

    /**
     * Tokenizer V3 — elenco CHIUSO di preposizioni articolate/elisioni
     * italiane comuni davanti a un sostantivo/aggettivo. Deliberatamente
     * NON uno split indiscriminato su ogni apostrofo: solo questi prefissi
     * esatti (già in minuscolo, il testo è normalizzato a monte) vengono
     * riconosciuti, ordinati dal più lungo al più corto perché nessuno di
     * questi è prefisso di un altro letto dall'inizio della parola (es.
     * "dell'" e "d'" non collidono mai: "dell'universo" non inizia per
     * "d'", il terzo carattere è "l" non l'apice).
     */
    private const ITALIAN_ELISION_PREFIXES = [
        "dell'", "dall'", "nell'", "sull'", "all'", "quell'", "quest'",
        "gl'", "un'", "l'", "d'", "c'", "s'",
    ];

    /**
     * Riduce "dell'universo"/"l'intelligenza"/... al solo sostantivo
     * ("universo"/"intelligenza"), così da condividere il termine con un
     * articolo che scrive la stessa parola senza l'articolo elisio (es. un
     * titolo che inizia con "Universo in espansione"). Prima di questo,
     * l'intera locuzione con apostrofo era un unico token indivisibile,
     * mai equivalente al sostantivo nudo scritto altrove — causa nota di
     * mancati collegamenti (vedi ArticleLinkTokenizerV3Test).
     *
     * Il resto della pipeline (lunghezza minima, STOPWORDS, regola -mente)
     * si applica IDENTICO al risultato: "un'AI" si riduce a "ai" e viene
     * comunque scartato per lunghezza, "c'era" si riduce a "era" e viene
     * comunque scartato perché è già una stopword — questo metodo non
     * introduce alcuna eccezione a quelle regole, si limita a normalizzare
     * il prefisso prima che vengano applicate.
     *
     * Se nessun prefisso noto corrisponde, la parola torna invariata: un
     * apostrofo interno non riconosciuto (nessun caso reale trovato in
     * questo dominio) non viene mai toccato.
     */
    private function stripItalianElision(string $word): string
    {
        foreach (self::ITALIAN_ELISION_PREFIXES as $prefix) {
            if (str_starts_with($word, $prefix)) {
                return mb_substr($word, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8');
            }
        }

        return $word;
    }

    /**
     * V3 — elenco CHIUSO e curato di acronimi scientifici corti (2-3
     * lettere) altrimenti scartati da MIN_TERM_LENGTH=4. Deliberatamente
     * una ALLOWLIST esplicita, non una regola generale "2-4 lettere tutte
     * maiuscole": un primo tentativo con una regola generale (qualunque
     * token corto tutto maiuscolo) ha superato i casi positivi ma FALLITO
     * più casi negativi realistici — in particolare "POI" (avverbio
     * comune, "poi"), mai stato in STOPWORDS perché la sua lunghezza lo
     * escludeva già da sé, diventava una falsa keyword non appena scritto
     * in maiuscolo. Un elenco chiuso elimina strutturalmente questa
     * classe di rischio: solo le stringhe qui elencate possono mai
     * bypassare la soglia minima, non un pattern generale su "tutto
     * maiuscolo" che richiederebbe di enumerare (e mantenere aggiornato
     * per sempre) ogni parola funzionale italiana corta per essere
     * davvero sicuro.
     *
     * "AI" (Intelligenza Artificiale, categoria stessa di Kairus) è
     * deliberatamente ESCLUSA: collide con "ai" (preposizione articolata,
     * "a"+"i"), già in STOPWORDS da prima di questa missione. Provare a
     * fare un'eccezione qui riaprirebbe esattamente il rischio appena
     * escluso con "POI" — limite noto e documentato, non un difetto da
     * aggirare con un'eccezione ad hoc.
     */
    private const SHORT_ACRONYM_ALLOWLIST = ['dna', 'rna', 'esa', 'ml', 'eu', 'iss'];

    /**
     * Un token catturato dalla regex bypassa MIN_TERM_LENGTH solo se è
     * scritto INTERAMENTE in maiuscolo nel testo ORIGINALE (prima di
     * qualunque abbassamento di case) E la sua forma minuscola è nella
     * allowlist curata sopra. Il requisito "tutto maiuscolo" resta come
     * ulteriore livello di sicurezza anche con un elenco chiuso: un
     * editoriale che scrivesse per ipotesi "l'esa di un problema" (parola
     * italiana comune, non l'agenzia spaziale) in minuscolo non
     * attiverebbe comunque l'eccezione.
     *
     * Bypassa SOLO il controllo di lunghezza minima in extractTerms():
     * resta comunque soggetto a STOPWORDS e alla regola -mente come ogni
     * altro termine, e al segnale di document-frequency già esistente in
     * scoreLink() — un acronimo che risultasse "generico" (presente in
     * una grossa quota del pool di candidati) verrebbe comunque svalutato
     * a punteggio ridotto, stessa protezione di qualunque altra parola.
     */
    private function isShortUppercaseAcronym(string $rawWord): bool
    {
        if (preg_match('/^\p{L}+$/u', $rawWord) !== 1) {
            return false;
        }

        if (mb_strtoupper($rawWord, 'UTF-8') !== $rawWord) {
            return false;
        }

        return in_array(mb_strtolower($rawWord, 'UTF-8'), self::SHORT_ACRONYM_ALLOWLIST, true);
    }

    /**
     * Marca "superseded" un suggerimento ancora 'proposed' che non è più
     * valido (testo modificato, o target ora già collegato manualmente) —
     * lo fa sparire dalle proposte attive senza cancellarne lo storico e
     * senza toccare 'accepted'/'ignored' (decisioni già prese dalla
     * redazione, mai sovrascritte automaticamente).
     */
    private function supersedeIfActionable(?ArticleLinkSuggestion $suggestion): void
    {
        if ($suggestion && $suggestion->isActionable()) {
            $suggestion->update(['status' => ArticleLinkSuggestion::STATUS_SUPERSEDED]);
        }
    }

    /**
     * Marca "accepted" i suggerimenti applicati nell'editor durante questa
     * modifica, ma SOLO ora che l'articolo è stato davvero salvato — non al
     * momento di "Inserisci" (che modifica solo il testo nel form). Se la
     * redazione inserisce un collegamento e poi abbandona la modifica senza
     * salvare, il suggerimento resta 'proposed' e può essere riproposto,
     * invece di risultare "gestito" per sempre senza che il link sia mai
     * arrivato nell'articolo pubblicato.
     *
     * Va chiamata DOPO $article->update($data) da parte del chiamante
     * (Admin/Redazione ArticleController::update()): $article deve già
     * riflettere lo stato/published_at appena salvati, non quelli
     * precedenti. Questo è essenziale per la revalidazione qui sotto
     * (Codex, PR #165, P1 round 2): "Inserisci" verifica l'eleggibilità
     * temporale contro l'articolo COM'ERA PERSISTITO in quel momento, ma
     * nella stessa modifica la redazione può spostare la programmazione
     * della source stessa PRIMA di premere "Salva" — un target che era
     * sicuro contro la vecchia data potrebbe non esserlo più contro quella
     * appena salvata. Va quindi riverificato qui, con lo stato reale
     * post-salvataggio: se non è più sicuro, il suggerimento non va
     * accettato, e il link — già fisicamente presente nel body inviato dal
     * form — va tolto (non basta lasciarlo "non accettato": resterebbe
     * comunque un <a> reale verso un target ancora non pubblico).
     *
     * NON filtra per stato 'proposed' in query (Codex, PR #165, P1 round 3):
     * se tra "Inserisci" e questo salvataggio una nuova "Analizza" ha già
     * superato il suggerimento (perché il target è nel frattempo diventato
     * non sicuro per un'altra ragione), il suo stato in DB è già
     * 'superseded' — ma il link è comunque fisicamente presente nel body
     * appena inviato dal form (era stato inserito lato client prima). Va
     * quindi rivalutato e il link ripulito comunque, non saltato solo
     * perché non è più 'proposed'.
     *
     * Codex, PR #165, P1 round 10: 'accepted' NON è più un vicolo cieco per
     * la rivalidazione. Un suggerimento già accettato in un salvataggio
     * PRECEDENTE (quindi non più tra $suggestionIds in QUESTO salvataggio)
     * può comunque avere il proprio target diventato non sicuro nel
     * frattempo (riprogrammato dopo la source, o retrocesso) — senza questo
     * secondo passaggio, nulla lo avrebbe più rilevato: il loop di
     * staleness in analyzeForSource() ignora le righe non 'actionable'
     * (isActionable() è vero solo per 'proposed'), e questo salvataggio
     * processa solo gli ID passati dal form corrente. Il link accettato
     * sarebbe rimasto nel body fino alla pubblicazione della source con un
     * 404 dentro. Ad OGNI salvataggio, quindi, si rivalutano anche tutte le
     * righe 'accepted' di questa source, non solo quelle appena applicate —
     * un salvataggio qualunque dell'articolo (anche per una modifica non
     * correlata) diventa così un punto di controllo naturale, coerente con
     * lo stesso principio già applicato ovunque in questa missione
     * (rivalidare ad ogni touchpoint esistente, mai un trigger nuovo).
     *
     * @param  array<int, int|string>  $suggestionIds
     */
    public function markAccepted(Article $article, array $suggestionIds, int $reviewerId): void
    {
        $body = $article->body;
        $bodyChanged = false;

        if (! empty($suggestionIds)) {
            $suggestions = ArticleLinkSuggestion::where('source_article_id', $article->id)
                ->whereIn('id', $suggestionIds)
                ->whereIn('status', [ArticleLinkSuggestion::STATUS_PROPOSED, ArticleLinkSuggestion::STATUS_SUPERSEDED])
                ->with('targetArticle')
                ->get();

            foreach ($suggestions as $suggestion) {
                $target = $suggestion->targetArticle;

                if ($target !== null && $this->temporalEligibility->isTargetSafeForSource($article, $target)) {
                    $suggestion->update([
                        'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
                        'reviewed_at' => now(),
                        'reviewed_by' => $reviewerId,
                    ]);

                    continue;
                }

                $newBody = $this->supersedeAndStripIfUnsafe($article, $suggestion, $target, $body);

                if ($newBody !== $body) {
                    $body = $newBody;
                    $bodyChanged = true;
                }
            }
        }

        $previouslyAccepted = ArticleLinkSuggestion::where('source_article_id', $article->id)
            ->where('status', ArticleLinkSuggestion::STATUS_ACCEPTED)
            ->with('targetArticle')
            ->get();

        foreach ($previouslyAccepted as $suggestion) {
            $target = $suggestion->targetArticle;

            if ($target !== null && $this->temporalEligibility->isTargetSafeForSource($article, $target)) {
                continue;
            }

            $newBody = $this->supersedeAndStripIfUnsafe($article, $suggestion, $target, $body);

            if ($newBody !== $body) {
                $body = $newBody;
                $bodyChanged = true;
            }
        }

        if ($bodyChanged) {
            $article->update(['body' => $body]);
        }
    }

    /**
     * Marca $suggestion superata e toglie dal body ogni link verso lo slug
     * attuale o storico del suo target — helper condiviso da entrambi i
     * passaggi di markAccepted() (suggerimenti appena applicati e
     * suggerimenti già accettati in precedenza). Il chiamante ha già
     * verificato che il target NON è (più) temporalmente sicuro.
     */
    private function supersedeAndStripIfUnsafe(Article $article, ArticleLinkSuggestion $suggestion, ?Article $target, string $body): string
    {
        if ($suggestion->status !== ArticleLinkSuggestion::STATUS_SUPERSEDED) {
            $suggestion->update(['status' => ArticleLinkSuggestion::STATUS_SUPERSEDED]);
        }

        if ($target === null) {
            return $body;
        }

        // Codex, PR #165, P2 round 3: l'href già nel body può ancora
        // puntare a un vecchio slug del target, se è stato rinominato nel
        // frattempo — cerca anche negli slug storici, non solo in quello
        // attuale.
        $historicalSlugs = ArticleSlugRedirect::where('article_id', $target->id)->pluck('old_slug');

        // Codex, PR #165, P2 round 4 e round 5: un vecchio slug del target
        // può nel frattempo essere stato reclamato come slug ATTUALE di un
        // ARTICOLO DIVERSO (gli slug si liberano quando l'articolo che li
        // usava ne cambia) — la rotta pubblica risolve sempre prima lo slug
        // corrente di un articolo (Article::published(), vedi
        // ArticleController::show()), quindi un simile href punterebbe
        // DAVVERO all'altro articolo, non più al target di questo
        // suggerimento. Va escluso dagli slug storici "sicuri da ripulire"
        // SOLO se il nuovo proprietario è a sua volta temporalmente sicuro
        // per questa source (round 5): la semplice esistenza di un
        // reclamante non basta — Article::published() richiede
        // status=published, quindi un reclamante ancora draft/review/
        // scheduled non risolverebbe comunque l'href ora, e il redirect
        // storico (che punta ancora al VECCHIO target, non sicuro) lo
        // farebbe fallire ugualmente: lasciare lo slug nel body
        // produrrebbe lo stesso 404 che questa pulizia esiste per evitare.
        if ($historicalSlugs->isNotEmpty()) {
            $reclaimingArticles = Article::whereIn('slug', $historicalSlugs)
                ->get(['slug', 'status', 'published_at']);

            $safelyReclaimedSlugs = $reclaimingArticles
                ->filter(fn (Article $reclaimer) => $this->temporalEligibility->isTargetSafeForSource($article, $reclaimer))
                ->pluck('slug');

            $historicalSlugs = $historicalSlugs->diff($safelyReclaimedSlugs);
        }

        $targetSlugs = [$target->slug, ...$historicalSlugs->all()];

        return $this->insertionService->removeLinksToSlugs($body, $targetSlugs);
    }

    private function plainText(string $html): string
    {
        $stripped = strip_tags($html);

        return html_entity_decode(
            preg_replace('/\s+/u', ' ', $stripped) ?? $stripped,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
}
