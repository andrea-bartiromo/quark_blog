<?php

namespace App\Services\ContentGraph;

use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ContentGraphService
{
    public function linkArticle(
        Article $article,
        Concept $concept,
        string $relationType = ArticleConcept::RELATION_SUPPORTING,
        int $weight = 50,
    ): ArticleConcept {
        if (! in_array($relationType, [
            ArticleConcept::RELATION_PRIMARY,
            ArticleConcept::RELATION_SUPPORTING,
        ], true)) {
            throw new InvalidArgumentException('Unsupported article-concept relation type.');
        }

        if ($weight < 0 || $weight > 255) {
            throw new InvalidArgumentException('Article-concept weight must be between 0 and 255.');
        }

        return ArticleConcept::query()->updateOrCreate(
            [
                'article_id' => $article->getKey(),
                'concept_id' => $concept->getKey(),
            ],
            [
                'relation_type' => $relationType,
                'weight' => $weight,
            ],
        );
    }

    public function conceptsForArticle(Article $article): Collection
    {
        return ArticleConcept::query()
            ->with(['concept.aliases'])
            ->where('article_id', $article->getKey())
            ->orderByDesc('weight')
            ->orderBy('concept_id')
            ->get();
    }

    public function discoverableConceptsForArticle(Article $article): Collection
    {
        $articleIsPublic = Article::query()
            ->published()
            ->whereKey($article->getKey())
            ->exists();

        if (! $articleIsPublic) {
            return collect();
        }

        return ArticleConcept::query()
            ->with(['concept.aliases'])
            ->where('article_id', $article->getKey())
            ->whereHas('concept', fn ($query) => $query->active())
            ->orderByDesc('weight')
            ->orderBy('concept_id')
            ->get();
    }

    public function articlesForConcept(Concept $concept): Collection
    {
        return ArticleConcept::query()
            ->with('article')
            ->where('concept_id', $concept->getKey())
            ->orderByDesc('weight')
            ->orderBy('article_id')
            ->get();
    }

    public function questionsForConcept(Concept $concept): Collection
    {
        return ConceptQuestion::query()
            ->with('targetArticle')
            ->where('concept_id', $concept->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function answerableQuestionsForConcept(Concept $concept): Collection
    {
        $conceptIsActive = Concept::query()
            ->active()
            ->whereKey($concept->getKey())
            ->exists();

        if (! $conceptIsActive) {
            return collect();
        }

        return ConceptQuestion::query()
            ->approved()
            ->with('targetArticle')
            ->where('concept_id', $concept->getKey())
            ->whereNotNull('target_article_id')
            ->whereNotNull('answer_summary')
            ->whereRaw("TRIM(answer_summary) <> ''")
            ->whereHas('targetArticle', fn ($query) => $query->published())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
