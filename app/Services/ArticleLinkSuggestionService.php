<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
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

    public function __construct(
        private readonly ArticleLinkInsertionService $insertionService,
    ) {}

    /**
     * Analizza $source contro tutti gli altri articoli pubblicati e
     * persiste/aggiorna i suggerimenti 'proposed'. Non tocca mai righe già
     * 'accepted' o 'ignored' (FASE 7: non riproporre continuamente).
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
        $alreadyLinkedSlugs = $this->linkedSlugsInBody((string) $source->body);

        $candidates = Article::published()
            ->where('id', '!=', $source->id)
            ->orderByDesc('published_at')
            ->limit(self::MAX_CANDIDATES)
            ->get(['id', 'title', 'slug', 'excerpt', 'category', 'body']);

        // V2 — un'unica passata sull'intero pool di candidati (stessa
        // Collection già caricata sopra, nessuna query aggiuntiva) per
        // calcolare quanti candidati condividono ciascun termine: la base
        // del segnale di specificità lessicale usato da scoreLink() più
        // sotto (vedi GENERIC_TERM_DOCUMENT_FREQUENCY_RATIO).
        $documentFrequency = $this->buildDocumentFrequency($candidates);
        $corpusSize = $candidates->count();

        $existing = ArticleLinkSuggestion::forSource($source->id)->get()->keyBy('target_article_id');

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

            $match = $this->scoreLink($source->category, (string) $source->body, $sourcePlainBody, $sourceTerms, $candidate, $documentFrequency, $corpusSize);

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

            $alreadyLinkedSlugs = $this->linkedSlugsInBody((string) $candidateSource->body);
            $existingSuggestion = $existingByCandidateId->get($candidateSource->id);

            if (in_array($target->slug, $alreadyLinkedSlugs, true)) {
                $this->supersedeIfActionable($existingSuggestion);

                continue;
            }

            if ($existingSuggestion && ! $existingSuggestion->isActionable()) {
                continue;
            }

            $sourceTerms = $this->extractTerms($sourcePlainBody);
            $match = $this->scoreLink($candidateSource->category, (string) $candidateSource->body, $sourcePlainBody, $sourceTerms, $targetAsCandidate);

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
     * @param  array<string,int>  $documentFrequency  V2 — quanti candidati del pool corrente contengono ciascun termine (vedi buildDocumentFrequency()). Vuoto = nessuna classificazione generico/specifico, tutti i termini condivisi restano a punteggio pieno (fallback usato da analyzeForNewTarget(), che itera i candidati via cursor() e non può costruire questa mappa senza una seconda passata sul DB — vedi docblock di analyzeForNewTarget()).
     * @param  int  $corpusSize  Dimensione del pool usato per calcolare $documentFrequency — 0 disabilita la classificazione (stesso motivo sopra).
     * @return array{score:int,anchor:string,context:?string,reason:string}|null
     */
    private function scoreLink(string $sourceCategory, string $sourceBodyHtml, string $sourcePlainBody, array $sourceTerms, object $candidateTarget, array $documentFrequency = [], int $corpusSize = 0): ?array
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
            'reason' => $this->buildReason($titleMatched, $matchedTerms, $categoryMatched, $candidateTarget->category),
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
        $bodyExcerpt = '';

        if (! empty($candidateTarget->body)) {
            $bodyExcerpt = mb_substr(
                $this->plainText((string) $candidateTarget->body),
                0,
                self::TARGET_BODY_EXCERPT_CHARS,
                'UTF-8'
            );
        }

        return $this->extractTerms(
            ($candidateTarget->title ?? '').' '.($candidateTarget->excerpt ?? '').' '.$bodyExcerpt
        );
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

    private function buildReason(bool $titleMatched, array $matchedTerms, bool $categoryMatched, string $category): string
    {
        $parts = [];

        if ($titleMatched) {
            $parts[] = 'il titolo dell\'articolo collegato compare nel testo';
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
        $text = mb_strtolower($this->plainText($text), 'UTF-8');

        // Il token inizia e finisce sempre su un carattere alfanumerico
        // (mai un trattino/apostrofo pendente): a differenza della regex
        // precedente ('\p{L}[\p{L}\'-]*', solo lettere), questa include
        // anche le cifre nel corpo del token — necessario per preservare
        // identificatori alfanumerici come "gpt-5", "covid-19", "h2o", "5g"
        // invece di troncarli al primo carattere numerico.
        preg_match_all("/[\\p{L}\\p{N}](?:[\\p{L}\\p{N}'-]*[\\p{L}\\p{N}])?/u", $text, $matches);

        $terms = [];

        foreach ($matches[0] ?? [] as $word) {
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

            if (mb_strlen($word, 'UTF-8') < $minLength) {
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
     * @param  array<int, int|string>  $suggestionIds
     */
    public function markAccepted(Article $article, array $suggestionIds, int $reviewerId): void
    {
        if (empty($suggestionIds)) {
            return;
        }

        ArticleLinkSuggestion::where('source_article_id', $article->id)
            ->whereIn('id', $suggestionIds)
            ->proposed()
            ->update([
                'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewerId,
            ]);
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

    /**
     * Slug degli articoli già collegati nel body (href verso /articolo/{slug})
     * — evita di riproporre un collegamento già presente nel testo (FASE 7).
     *
     * @return array<int, string>
     */
    private function linkedSlugsInBody(string $html): array
    {
        if (trim($html) === '' || strip_tags($html) === $html) {
            return [];
        }

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $slugs = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            /** @var \DOMElement $anchor */
            $href = $anchor->getAttribute('href');

            if ($href !== '' && preg_match('~/articolo/([^/?#]+)~', $href, $m)) {
                $slugs[] = $m[1];
            }
        }

        return array_unique($slugs);
    }
}
