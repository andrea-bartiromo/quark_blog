<?php

namespace App\Services\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;

/**
 * Mission 21 — Question Status Workflow V2: audit read-only per singola
 * ConceptQuestion, itemizzando ESATTAMENTE le stesse condizioni già
 * applicate da ContentGraphService::answerableQuestionsForConcept().
 *
 * Mission 59 adds the bounded approved-question catalogue audit while keeping
 * this class as the single explanation of the public gate.
 */
class ConceptQuestionReadinessService
{
    public const ANSWER_MISSING = 'ANSWER_MISSING';

    public const TARGET_MISSING = 'TARGET_MISSING';

    public const TARGET_NOT_PUBLISHED = 'TARGET_NOT_PUBLISHED';

    public const CONCEPT_NOT_ACTIVE = 'CONCEPT_NOT_ACTIVE';

    public const STATUS_NOT_APPROVED = 'STATUS_NOT_APPROVED';

    /**
     * @return array{answerable: bool, findings: list<array{code: string, message: string}>}
     */
    public function evaluate(ConceptQuestion $question): array
    {
        $question->loadMissing(['concept']);

        $targetIsPublished = $question->target_article_id !== null
            && Article::query()->published()->whereKey($question->target_article_id)->exists();

        return $this->evaluateFacts($question, $targetIsPublished);
    }

    /**
     * Audit every approved question with a bounded query shape.
     *
     * The FK uses nullOnDelete(), therefore a deleted target is represented by
     * TARGET_MISSING rather than an impossible dangling target id.
     *
     * @return list<array{
     *     question_id:int,
     *     concept_id:int,
     *     question:string,
     *     answerable:bool,
     *     findings:list<array{code:string,message:string}>,
     *     concept_edit_url:string
     * }>
     */
    public function auditApproved(): array
    {
        $questions = ConceptQuestion::query()
            ->approved()
            ->with('concept')
            ->orderBy('concept_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $publishedTargetIds = Article::query()
            ->published()
            ->whereKey($questions->pluck('target_article_id')->filter()->unique())
            ->pluck('id')
            ->flip();

        return $questions
            ->map(function (ConceptQuestion $question) use ($publishedTargetIds) {
                $result = $this->evaluateFacts(
                    $question,
                    $question->target_article_id !== null
                        && $publishedTargetIds->has($question->target_article_id),
                );

                return [
                    'question_id' => $question->id,
                    'concept_id' => $question->concept_id,
                    'question' => $question->question,
                    'answerable' => $result['answerable'],
                    'findings' => $result['findings'],
                    'concept_edit_url' => route('admin.concepts.edit', $question->concept_id),
                ];
            })
            ->values()
            ->all();
    }

    private function evaluateFacts(ConceptQuestion $question, bool $targetIsPublished): array
    {
        $findings = [];

        if ($question->status !== ConceptQuestion::STATUS_APPROVED) {
            $findings[] = $this->finding(self::STATUS_NOT_APPROVED, 'Lo stato non è "Approvata".');
        }

        if ($question->concept === null || $question->concept->status !== Concept::STATUS_ACTIVE) {
            $findings[] = $this->finding(self::CONCEPT_NOT_ACTIVE, 'Il concetto non è attivo.');
        }

        if (trim((string) $question->answer_summary) === '') {
            $findings[] = $this->finding(self::ANSWER_MISSING, 'Manca una risposta (sintesi).');
        }

        if ($question->target_article_id === null) {
            $findings[] = $this->finding(self::TARGET_MISSING, 'Manca un articolo target.');
        } elseif (! $targetIsPublished) {
            $findings[] = $this->finding(self::TARGET_NOT_PUBLISHED, 'L\'articolo target non è pubblicato.');
        }

        return [
            'answerable' => $findings === [],
            'findings' => $findings,
        ];
    }

    private function finding(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
