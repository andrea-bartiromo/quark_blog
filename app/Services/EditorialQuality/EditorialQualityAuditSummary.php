<?php

namespace App\Services\EditorialQuality;

use App\Models\Article;

/**
 * Risultato completo di EditorialQualityAuditService::audit() — sola
 * lettura, mai persistito.
 */
final readonly class EditorialQualityAuditSummary
{
    /**
     * @param  array<int, array{code: string, label: string, count: int}>  $mostFrequentIssues
     * @param  array<int, array{article: Article, report: EditorialQualityReport}>  $entries
     */
    public function __construct(
        public int $analyzed,
        public int $readyCount,
        public int $attentionCount,
        public int $incompleteCount,
        public array $mostFrequentIssues,
        public array $entries,
    ) {}
}
