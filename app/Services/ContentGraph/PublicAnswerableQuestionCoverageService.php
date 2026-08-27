<?php

namespace App\Services\ContentGraph;

use App\Models\Concept;
use App\Models\ConceptQuestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only coverage of active Concepts by the canonical public-answerable
 * question rule.
 */
class PublicAnswerableQuestionCoverageService
{
    public const ANSWERABLE = 'ANSWERABLE';

    public const APPROVED_NOT_PUBLIC = 'APPROVED_NOT_PUBLIC';

    public const DRAFT_ONLY = 'DRAFT_ONLY';

    public const NO_QUESTIONS = 'NO_QUESTIONS';

    public const OTHER_NON_ANSWERABLE = 'OTHER_NON_ANSWERABLE';

    /**
     * @return array{
     *     active_concepts_total:int,
     *     with_answerable_question:int,
     *     without_answerable_question:int,
     *     detail:list<array{
     *         concept_id:int,
     *         name:string,
     *         slug:string,
     *         coverage:string,
     *         questions_total:int,
     *         draft_questions:int,
     *         approved_questions:int,
     *         answerable_questions:int
     *     }>
     * }
     */
    public function summary(): array
    {
        $detail = Concept::query()
            ->active()
            ->withCount([
                'questions',
                'questions as draft_questions_count' => fn (Builder $query) => $query
                    ->where('status', ConceptQuestion::STATUS_DRAFT),
                'questions as approved_questions_count' => fn (Builder $query) => $query
                    ->approved(),
                'questions as answerable_questions_count' => fn (Builder $query) => $query
                    ->publiclyAnswerable(),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Concept $concept) {
                $answerable = (int) $concept->answerable_questions_count;
                $approved = (int) $concept->approved_questions_count;
                $draft = (int) $concept->draft_questions_count;
                $total = (int) $concept->questions_count;

                return [
                    'concept_id' => $concept->id,
                    'name' => $concept->name,
                    'slug' => $concept->slug,
                    'coverage' => $this->coverage($total, $draft, $approved, $answerable),
                    'questions_total' => $total,
                    'draft_questions' => $draft,
                    'approved_questions' => $approved,
                    'answerable_questions' => $answerable,
                ];
            })
            ->values();

        $withAnswerable = $detail
            ->where('coverage', self::ANSWERABLE)
            ->count();

        return [
            'active_concepts_total' => $detail->count(),
            'with_answerable_question' => $withAnswerable,
            'without_answerable_question' => $detail->count() - $withAnswerable,
            'detail' => $detail->all(),
        ];
    }

    private function coverage(int $total, int $draft, int $approved, int $answerable): string
    {
        if ($answerable > 0) {
            return self::ANSWERABLE;
        }

        if ($approved > 0) {
            return self::APPROVED_NOT_PUBLIC;
        }

        if ($total === 0) {
            return self::NO_QUESTIONS;
        }

        if ($draft === $total) {
            return self::DRAFT_ONLY;
        }

        return self::OTHER_NON_ANSWERABLE;
    }
}
