<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Concept;
use App\Services\ContentGraph\ConceptHealthService;
use App\Services\ContentGraph\ContentGraphOperationalSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentGraphOperationalSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_catalogue_concludes_no_problems_detected(): void
    {
        $summary = app(ContentGraphOperationalSummaryService::class)->summary();

        $this->assertTrue($summary['status']['healthy']);
        $this->assertSame('NO_PROBLEMS', $summary['status']['code']);
        $this->assertSame('Nessun problema Content Graph rilevato', $summary['status']['label']);
        $this->assertSame(0, $summary['concept_health']['total']);
        $this->assertSame(0, $summary['alias_integrity']['total']);
        $this->assertSame(0, $summary['approved_question_integrity']['total']);
        $this->assertSame(0, $summary['relationship_integrity']['total']);
    }

    public function test_summary_exposes_codes_labels_counts_and_admin_targets(): void
    {
        $concept = Concept::create([
            'name' => 'Incompleto',
            'slug' => 'incompleto',
            'status' => Concept::STATUS_ACTIVE,
        ]);

        $summary = app(ContentGraphOperationalSummaryService::class)->summary();

        $this->assertFalse($summary['status']['healthy']);
        $this->assertSame('ATTENTION_REQUIRED', $summary['status']['code']);
        $this->assertSame(1, $summary['concept_health']['total']);

        $row = $summary['concept_health']['items'][0];
        $this->assertSame(ConceptHealthService::INCOMPLETE, $row['health']);
        $this->assertContains(ConceptHealthService::ACTIVE_WITHOUT_ARTICLE_LINK, $row['codes']);
        $this->assertStringContainsString((string) $concept->id, $row['edit_url']);

        $this->assertSame(1, $summary['question_coverage']['active_concepts_total']);
        $this->assertSame(1, $summary['question_coverage']['without_answerable_question']);
    }

    public function test_lists_are_bounded_and_report_truncation(): void
    {
        foreach (range(1, 51) as $number) {
            Concept::create([
                'name' => 'Concept '.$number,
                'slug' => 'concept-'.$number,
                'status' => Concept::STATUS_ACTIVE,
            ]);
        }

        $summary = app(ContentGraphOperationalSummaryService::class)->summary();

        $this->assertSame(51, $summary['concept_health']['total']);
        $this->assertCount(50, $summary['concept_health']['items']);
        $this->assertTrue($summary['concept_health']['items_truncated']);
        $this->assertCount(50, $summary['question_coverage']['items']);
        $this->assertTrue($summary['question_coverage']['items_truncated']);
    }

    public function test_summary_query_shape_is_bounded(): void
    {
        Concept::create([
            'name' => 'Uno',
            'slug' => 'uno',
            'status' => Concept::STATUS_ACTIVE,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ContentGraphOperationalSummaryService::class)->summary();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertGreaterThanOrEqual(9, $queryCount);
        $this->assertLessThanOrEqual(12, $queryCount);
    }
}
