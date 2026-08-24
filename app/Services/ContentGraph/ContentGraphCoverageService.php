<?php

namespace App\Services\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;

/**
 * Mission 19 — Content Graph Coverage Metrics: aggregati read-only su
 * quanto il Content Graph copre davvero il catalogo editoriale — nessun
 * elenco di singoli articoli/concetti orfani qui (quella è una diagnostica
 * separata), solo i numeri per un pannello di sintesi. Stesso principio di
 * EditorialOperationsDashboardService: mai ricalcolare una regola di
 * pubblicazione già espressa altrove (Article::published(),
 * ContentGraphService::answerableQuestionsForConcept()), solo contare.
 */
class ContentGraphCoverageService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'articles' => $this->articleCoverage(),
            'concepts' => $this->conceptCoverage(),
            'questions' => $this->questionCoverage(),
        ];
    }

    private function articleCoverage(): array
    {
        $publishedTotal = Article::query()->published()->count();
        $publishedWithLink = Article::query()
            ->published()
            ->whereHas('contentConcepts')
            ->count();

        return [
            'published_total' => $publishedTotal,
            'published_with_concept_link' => $publishedWithLink,
            'published_without_concept_link' => $publishedTotal - $publishedWithLink,
            'coverage_percent' => $this->percent($publishedWithLink, $publishedTotal),
        ];
    }

    private function conceptCoverage(): array
    {
        $byStatus = Concept::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $activeTotal = $byStatus[Concept::STATUS_ACTIVE] ?? 0;
        $activeWithLink = Concept::query()
            ->active()
            ->whereHas('articleLinks')
            ->count();

        return [
            'total' => array_sum($byStatus),
            'by_status' => [
                Concept::STATUS_DRAFT => $byStatus[Concept::STATUS_DRAFT] ?? 0,
                Concept::STATUS_ACTIVE => $activeTotal,
                Concept::STATUS_INACTIVE => $byStatus[Concept::STATUS_INACTIVE] ?? 0,
            ],
            'active_with_article_link' => $activeWithLink,
            'active_without_article_link' => $activeTotal - $activeWithLink,
        ];
    }

    private function questionCoverage(): array
    {
        $byStatus = ConceptQuestion::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $conceptsWithoutQuestions = Concept::query()
            ->active()
            ->whereDoesntHave('questions')
            ->count();

        // Il catalogo di concetti è curato a mano (unità, non migliaia): un
        // conteggio per concetto tramite il contratto pubblico riusabile
        // resta accettabile qui, stesso ragionamento già documentato per
        // EditorialOperationsDashboardService::percorsiReadinessSummary().
        $contentGraph = app(ContentGraphService::class);
        $publiclyAnswerable = Concept::query()->active()->get()
            ->sum(fn (Concept $concept) => $contentGraph->answerableQuestionsForConcept($concept)->count());

        return [
            'total' => array_sum($byStatus),
            'by_status' => [
                ConceptQuestion::STATUS_DRAFT => $byStatus[ConceptQuestion::STATUS_DRAFT] ?? 0,
                ConceptQuestion::STATUS_APPROVED => $byStatus[ConceptQuestion::STATUS_APPROVED] ?? 0,
                ConceptQuestion::STATUS_INACTIVE => $byStatus[ConceptQuestion::STATUS_INACTIVE] ?? 0,
            ],
            'active_concepts_without_questions' => $conceptsWithoutQuestions,
            'publicly_answerable_total' => $publiclyAnswerable,
        ];
    }

    private function percent(int $part, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}
