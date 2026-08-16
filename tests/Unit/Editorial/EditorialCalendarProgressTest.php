<?php

namespace Tests\Unit\Editorial;

use App\Models\Article;
use App\Models\Project;
use App\Services\Editorial\EditorialCalendarEntry;
use App\Services\Editorial\EditorialCalendarMatch;
use App\Services\Editorial\EditorialCalendarMatchingService;
use App\Services\Editorial\EditorialCalendarProgress;
use App\Services\Editorial\EditorialCalendarReconciliationEntry;
use App\Services\Editorial\EditorialCalendarReconciliationReport;
use Carbon\Carbon;
use Tests\TestCase;

class EditorialCalendarProgressTest extends TestCase
{
    private int $nextArticleId = 1;

    private function article(string $status): Article
    {
        return (new Article)->forceFill([
            'id' => $this->nextArticleId++,
            'title' => 'Articolo '.uniqid(),
            'status' => $status,
        ]);
    }

    private function entry(int $position = 1): EditorialCalendarEntry
    {
        return new EditorialCalendarEntry(
            position: $position,
            date: Carbon::parse('2026-08-28'),
            title: 'Titolo '.$position,
            filone: null,
            status: null,
            section: null,
            lineNumber: $position,
            rawLine: '',
        );
    }

    private function matchedReconciliationEntry(string $status, string $discrepancy, int $position = 1): EditorialCalendarReconciliationEntry
    {
        $article = $this->article($status);
        $match = new EditorialCalendarMatch(
            entry: $this->entry($position),
            matchType: EditorialCalendarMatchingService::MATCH_EXACT,
            article: $article,
            candidates: [$article],
            alreadyLinkedToProject: true,
        );

        return new EditorialCalendarReconciliationEntry($match, $discrepancy);
    }

    private function missingReconciliationEntry(int $position = 1): EditorialCalendarReconciliationEntry
    {
        $match = new EditorialCalendarMatch(
            entry: $this->entry($position),
            matchType: EditorialCalendarMatchingService::MATCH_NONE,
            article: null,
            candidates: [],
            alreadyLinkedToProject: false,
        );

        return new EditorialCalendarReconciliationEntry($match, EditorialCalendarReconciliationEntry::DISCREPANCY_MISSING_ARTICLE);
    }

    private function needsReviewReconciliationEntry(int $position = 1): EditorialCalendarReconciliationEntry
    {
        $candidateA = $this->article(Article::STATUS_DRAFT);
        $candidateB = $this->article(Article::STATUS_DRAFT);
        $match = new EditorialCalendarMatch(
            entry: $this->entry($position),
            matchType: EditorialCalendarMatchingService::MATCH_AMBIGUOUS,
            article: null,
            candidates: [$candidateA, $candidateB],
            alreadyLinkedToProject: false,
        );

        return new EditorialCalendarReconciliationEntry($match, EditorialCalendarReconciliationEntry::DISCREPANCY_REQUIRES_REVIEW);
    }

    /** @param  list<EditorialCalendarReconciliationEntry>  $entries */
    private function report(array $entries): EditorialCalendarReconciliationReport
    {
        return new EditorialCalendarReconciliationReport(
            Project::factory()->make(),
            1,
            $entries,
            [],
            []
        );
    }

    public function test_an_empty_calendar_has_zeroed_metrics_and_never_divides_by_zero(): void
    {
        $progress = EditorialCalendarProgress::fromReport($this->report([]));

        $this->assertSame(0, $progress->totalPlanned);
        $this->assertSame(0, $progress->coveragePercent);
        $this->assertSame(0, $progress->publishedPercent);
    }

    public function test_counts_are_broken_down_by_real_article_status_not_a_single_percentage(): void
    {
        $entries = [
            $this->matchedReconciliationEntry(Article::STATUS_PUBLISHED, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 1),
            $this->matchedReconciliationEntry(Article::STATUS_SCHEDULED, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 2),
            $this->matchedReconciliationEntry(Article::STATUS_DRAFT, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 3),
            $this->matchedReconciliationEntry(Article::STATUS_REVIEW, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 4),
            $this->missingReconciliationEntry(5),
            $this->needsReviewReconciliationEntry(6),
        ];

        $progress = EditorialCalendarProgress::fromReport($this->report($entries));

        $this->assertSame(6, $progress->totalPlanned);
        $this->assertSame(1, $progress->publishedCount);
        $this->assertSame(1, $progress->scheduledCount);
        $this->assertSame(2, $progress->inProgressCount);
        $this->assertSame(1, $progress->missingArticleCount);
        $this->assertSame(1, $progress->needsReviewCount);
    }

    public function test_coverage_percent_counts_any_matched_article_regardless_of_its_status(): void
    {
        $entries = [
            $this->matchedReconciliationEntry(Article::STATUS_DRAFT, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 1),
            $this->matchedReconciliationEntry(Article::STATUS_PUBLISHED, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 2),
            $this->missingReconciliationEntry(3),
            $this->missingReconciliationEntry(4),
        ];

        $progress = EditorialCalendarProgress::fromReport($this->report($entries));

        $this->assertSame(50, $progress->coveragePercent);
    }

    public function test_published_percent_only_counts_actually_published_articles(): void
    {
        $entries = [
            $this->matchedReconciliationEntry(Article::STATUS_PUBLISHED, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 1),
            $this->matchedReconciliationEntry(Article::STATUS_SCHEDULED, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 2),
            $this->matchedReconciliationEntry(Article::STATUS_DRAFT, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 3),
            $this->matchedReconciliationEntry(Article::STATUS_DRAFT, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 4),
        ];

        $progress = EditorialCalendarProgress::fromReport($this->report($entries));

        $this->assertSame(25, $progress->publishedPercent);
    }

    public function test_a_fully_published_plan_reaches_one_hundred_percent_on_both_metrics(): void
    {
        $entries = [
            $this->matchedReconciliationEntry(Article::STATUS_PUBLISHED, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 1),
            $this->matchedReconciliationEntry(Article::STATUS_PUBLISHED, EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, 2),
        ];

        $progress = EditorialCalendarProgress::fromReport($this->report($entries));

        $this->assertSame(100, $progress->coveragePercent);
        $this->assertSame(100, $progress->publishedPercent);
    }
}
