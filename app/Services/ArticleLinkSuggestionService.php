<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Genera suggerimenti di collegamento interno tra articoli con un ranking
 * deterministico e trasparente — nessuna chiamata AI esterna. Ogni
 * suggerimento è sempre una PROPOSTA persistita con stato 'proposed': non
 * scrive mai nel body dell'articolo (quello è
 * App\Services\ArticleLinkInsertionService, invocato solo su azione umana
 * esplicita "Inserisci").
 *
 * Punteggio (0-100, soglia minima 30 per essere proposto):
 *   - +50 se il TITOLO dell'articolo target compare per intero nel testo
 *     sorgente (segnale forte e specifico — quasi sempre >= soglia da solo);
 *   - +15 per ciascun termine significativo condiviso tra il testo sorgente
 *     e titolo/sommario del target (fino a 3 termini, +45 max);
 *   - +10 se sorgente e target condividono la stessa categoria (mai da
 *     solo sufficiente a superare la soglia: evita corrispondenze basate
 *     solo su "stessa categoria" senza alcun segnale testuale reale).
 *
 * L'anchor text proposta è SEMPRE una frase già presente nel testo sorgente
 * (il titolo del target se vi compare, altrimenti il termine condiviso più
 * lungo trovato) — mai una frase generata o generica: soddisfa il
 * requisito di non introdurre anchor artificiali ("clicca qui" e simili
 * sono strutturalmente impossibili con questo approccio).
 */
class ArticleLinkSuggestionService
{
    private const TITLE_MATCH_SCORE = 50;

    private const TERM_MATCH_SCORE = 15;

    private const MAX_SCORED_TERM_MATCHES = 3;

    private const CATEGORY_BONUS = 10;

    private const MIN_SCORE_THRESHOLD = 40;

    private const MIN_TERM_LENGTH = 4;

    private const MIN_TITLE_LENGTH_FOR_PHRASE_MATCH = 8;

    private const CONTEXT_WINDOW_CHARS = 60;

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
        'mio', 'mia', 'tuo', 'tua', 'suo', 'sua', 'nostro', 'nostra', 'vostro', 'vostra',
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
            ->get(['id', 'title', 'slug', 'excerpt', 'category']);

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

            $match = $this->scoreLink($source->category, (string) $source->body, $sourcePlainBody, $sourceTerms, $candidate);

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
     * @param  object{id:int,title:string,slug:string,excerpt:?string,category:string}  $candidateTarget
     * @return array{score:int,anchor:string,context:?string,reason:string}|null
     */
    private function scoreLink(string $sourceCategory, string $sourceBodyHtml, string $sourcePlainBody, array $sourceTerms, object $candidateTarget): ?array
    {
        $score = 0;
        $matchedTerms = [];
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

        $targetTerms = $this->extractTerms(($candidateTarget->title ?? '').' '.($candidateTarget->excerpt ?? ''));
        $sharedTerms = array_values(array_intersect($targetTerms, $sourceTerms));

        // Termine più lungo per primo: se serve un'anchor dai soli termini
        // (nessun match sul titolo), la frase più specifica è preferibile.
        usort($sharedTerms, fn ($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        $scoredTerms = array_slice($sharedTerms, 0, self::MAX_SCORED_TERM_MATCHES);
        $score += count($scoredTerms) * self::TERM_MATCH_SCORE;
        $matchedTerms = $scoredTerms;

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
        // termini condivisi dal più lungo al più corto.
        $anchorCandidates = [];

        if ($titleOccurrence !== null) {
            $anchorCandidates[] = $titleOccurrence;
        }

        foreach ($sharedTerms as $term) {
            $occurrence = $this->findPhrase($sourcePlainBody, $term);

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

        preg_match_all('/\p{L}[\p{L}\'-]*/u', $text, $matches);

        $terms = [];

        foreach ($matches[0] ?? [] as $word) {
            $normalized = $this->stripAccents($word);

            if (mb_strlen($word, 'UTF-8') < self::MIN_TERM_LENGTH) {
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
