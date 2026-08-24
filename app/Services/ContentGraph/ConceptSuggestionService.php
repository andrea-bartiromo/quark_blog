<?php

namespace App\Services\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Services\InternalLinking\ConceptCandidate;
use App\Services\InternalLinking\ScientificConceptMatcher;
use Illuminate\Support\Collection;

/**
 * Mission 20 — Article Editor Concept Suggestions V1: mentre un editor
 * scrive/rivede un articolo, suggerisce quali Concept già attivi
 * potrebbe voler collegare, riconoscendo il loro nome/alias nel testo
 * dell'articolo — mai un collegamento automatico, solo un suggerimento
 * che l'editor accetta esplicitamente tramite l'azione "Collega" già
 * esistente (ArticleController::linkConcept()).
 *
 * Riusa l'identica pipeline di matching testata di ScientificConceptMatcher
 * (word-boundary, alias più lungo prima, niente sovrapposizioni) tramite
 * conceptsPresentInTerms() — esattamente il gancio che
 * ConceptCandidate::$source aveva già anticipato per una futura origine
 * 'content_graph'. Nessuna seconda implementazione dell'algoritmo di
 * riconoscimento testo.
 */
class ConceptSuggestionService
{
    public function __construct(
        private readonly ScientificConceptMatcher $matcher,
    ) {}

    /**
     * @return list<array{concept: Concept, matched_text: string, word_count: int}>
     */
    public function suggestForArticle(Article $article): array
    {
        $linkedConceptIds = $article->contentConcepts()->pluck('concept_id')->all();

        $concepts = Concept::query()
            ->active()
            ->when($linkedConceptIds !== [], fn ($query) => $query->whereNotIn('id', $linkedConceptIds))
            ->with('aliases')
            ->get();

        if ($concepts->isEmpty()) {
            return [];
        }

        $conceptsByName = $concepts->keyBy('name');

        $terms = $concepts->map(fn (Concept $concept) => [
            'canonical' => $concept->name,
            'aliases' => array_merge([$concept->name], $concept->aliases->pluck('alias')->all()),
        ])->values()->all();

        $plainText = trim(strip_tags(
            $article->title."\n".$article->excerpt."\n".$article->body
        ));

        $candidates = $this->matcher->conceptsPresentInTerms($plainText, $terms, 'content_graph');

        return $this->toSuggestions($candidates, $conceptsByName);
    }

    /**
     * @param  array<int, ConceptCandidate>  $candidates
     * @param  Collection<string, Concept>  $conceptsByName
     * @return list<array{concept: Concept, matched_text: string, word_count: int}>
     */
    private function toSuggestions(array $candidates, Collection $conceptsByName): array
    {
        $suggestions = [];
        $seenConceptIds = [];

        foreach ($candidates as $candidate) {
            $concept = $conceptsByName->get($candidate->canonicalTerm);

            if ($concept === null || in_array($concept->id, $seenConceptIds, true)) {
                continue;
            }

            $seenConceptIds[] = $concept->id;
            $suggestions[] = [
                'concept' => $concept,
                'matched_text' => $candidate->matchedText,
                'word_count' => $candidate->wordCount,
            ];
        }

        usort($suggestions, fn (array $a, array $b) => $b['word_count'] <=> $a['word_count']);

        return $suggestions;
    }
}
