<?php

namespace App\Services\ContentGraph;

/**
 * Single read-only operational summary for Content Graph diagnostics.
 *
 * Every section delegates to the Mission 56–60 source service. This class only
 * bounds, labels and links their output; it never recalculates domain rules.
 */
class ContentGraphOperationalSummaryService
{
    private const LIST_LIMIT = 50;

    public function __construct(
        private readonly ConceptHealthService $conceptHealth,
        private readonly PublicAnswerableQuestionCoverageService $questionCoverage,
        private readonly ConceptAliasIntegrityService $aliasIntegrity,
        private readonly ConceptQuestionReadinessService $questionIntegrity,
        private readonly ArticleConceptDiagnosticsService $relationshipDiagnostics,
    ) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $healthRows = $this->conceptHealth->all();
        $unhealthyConcepts = $healthRows
            ->where('health', '!=', ConceptHealthService::READY)
            ->map(fn (array $row) => [
                ...$row,
                'edit_url' => route('admin.concepts.edit', $row['concept_id']),
            ])
            ->values();

        $coverage = $this->questionCoverage->summary();
        $uncoveredConcepts = collect($coverage['detail'])
            ->where('coverage', '!=', PublicAnswerableQuestionCoverageService::ANSWERABLE)
            ->map(fn (array $row) => [
                ...$row,
                'edit_url' => route('admin.concepts.edit', $row['concept_id']),
            ])
            ->values();

        $aliasFindings = collect($this->aliasIntegrity->audit());
        $questionRows = collect($this->questionIntegrity->auditApproved());
        $incoherentQuestions = $questionRows->where('answerable', false)->values();

        $relationships = $this->relationshipDiagnostics->audit();
        $relationshipFindings = collect($relationships['findings']);
        $articleOrphans = collect($relationships['published_articles_without_concept'])
            ->map(fn (array $row) => [
                ...$row,
                'edit_url' => route('admin.articles.edit', $row['id']),
            ])
            ->values();

        $hasProblems = $unhealthyConcepts->isNotEmpty()
            || $aliasFindings->isNotEmpty()
            || $incoherentQuestions->isNotEmpty()
            || $relationshipFindings->isNotEmpty()
            || $articleOrphans->isNotEmpty();

        return [
            'status' => [
                'healthy' => ! $hasProblems,
                'code' => $hasProblems ? 'ATTENTION_REQUIRED' : 'NO_PROBLEMS',
                'label' => $hasProblems
                    ? 'Problemi Content Graph da verificare'
                    : 'Nessun problema Content Graph rilevato',
            ],
            'concept_health' => $this->section($unhealthyConcepts),
            'question_coverage' => [
                'active_concepts_total' => $coverage['active_concepts_total'],
                'with_answerable_question' => $coverage['with_answerable_question'],
                'without_answerable_question' => $coverage['without_answerable_question'],
                'items' => $uncoveredConcepts->take(self::LIST_LIMIT)->all(),
                'items_truncated' => $uncoveredConcepts->count() > self::LIST_LIMIT,
            ],
            'alias_integrity' => $this->section($aliasFindings),
            'approved_question_integrity' => $this->section($incoherentQuestions),
            'relationship_integrity' => $this->section($relationshipFindings),
            'published_articles_without_concept' => $this->section($articleOrphans),
            'policy_notes' => $relationships['policy_notes'],
        ];
    }

    private function section($items): array
    {
        return [
            'total' => $items->count(),
            'items' => $items->take(self::LIST_LIMIT)->values()->all(),
            'items_truncated' => $items->count() > self::LIST_LIMIT,
        ];
    }
}
