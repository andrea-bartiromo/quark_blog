<?php

namespace App\Services\Editorial;

use App\Models\Article;

/**
 * Metriche di avanzamento del Piano Editoriale calcolate ON-DEMAND da un
 * EditorialCalendarReconciliationReport — mai persistite, mai calcolate al
 * salvataggio di qualcosa: sono sempre lo specchio esatto dello stato
 * reale corrente (articoli + calendario), non un valore che può
 * disallinearsi da essi. Deliberatamente separate da Project::progress
 * (ProjectProgressService, basato sulle ProjectTask): un progetto
 * editoriale può non avere task, o averne per motivi non correlati al
 * calendario — usare la stessa colonna per due concetti diversi
 * confonderebbe entrambi.
 *
 * Deliberatamente NON una singola percentuale: "quanto è avanti il piano"
 * è una domanda con più risposte legittime e diverse (quanto è coperto da
 * un articolo qualsiasi? quanto è già pubblicato? quanto richiede
 * attenzione?) — comprimerle in un solo numero nasconderebbe proprio le
 * differenze che la missione chiede di rendere esplicite.
 */
final readonly class EditorialCalendarProgress
{
    public function __construct(
        public int $totalPlanned,
        public int $publishedCount,
        public int $scheduledCount,
        public int $inProgressCount,
        public int $missingArticleCount,
        public int $needsReviewCount,
        public int $coveragePercent,
        public int $publishedPercent,
    ) {}

    public static function fromReport(EditorialCalendarReconciliationReport $report): self
    {
        $total = $report->totalEntries();

        $published = 0;
        $scheduled = 0;
        $inProgress = 0;

        foreach ($report->entries as $entry) {
            $article = $entry->match->article;

            if ($article === null) {
                continue;
            }

            match ($article->status) {
                Article::STATUS_PUBLISHED => $published++,
                Article::STATUS_SCHEDULED => $scheduled++,
                default => $inProgress++,
            };
        }

        $missingArticle = count($report->missingArticles());
        $needsReview = count($report->requiringReview());
        $matched = $published + $scheduled + $inProgress;

        return new self(
            totalPlanned: $total,
            publishedCount: $published,
            scheduledCount: $scheduled,
            inProgressCount: $inProgress,
            missingArticleCount: $missingArticle,
            needsReviewCount: $needsReview,
            coveragePercent: $total === 0 ? 0 : (int) round(100 * $matched / $total),
            publishedPercent: $total === 0 ? 0 : (int) round(100 * $published / $total),
        );
    }
}
