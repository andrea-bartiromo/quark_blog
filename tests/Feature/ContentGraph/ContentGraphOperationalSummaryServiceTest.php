<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Services\ContentGraph\ConceptHealthService;
use App\Services\ContentGraph\ConceptQuestionBalanceAuditService;
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
        $this->assertFalse($summary['question_balance']['applicable']);
        $this->assertSame(0, $summary['question_balance']['total']);
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

    public function test_question_balance_outliers_are_surfaced_with_admin_targets(): void
    {
        // Stessa distribuzione di ConceptQuestionBalanceAuditServiceTest::
        // test_flags_a_clear_over_represented_outlier — [2,2,2,3,3,3,20],
        // fence superiore 4.5, un solo outlier sopra soglia.
        foreach ([2, 2, 2, 3, 3, 3] as $index => $count) {
            $concept = Concept::create([
                'name' => 'Bilanciato '.$index,
                'slug' => 'bilanciato-'.$index,
                'status' => Concept::STATUS_ACTIVE,
            ]);
            foreach (range(1, $count) as $i) {
                ConceptQuestion::create(['concept_id' => $concept->id, 'question' => "Domanda {$index}-{$i}?"]);
            }
        }
        $outlier = Concept::create([
            'name' => 'Sovra-rappresentato',
            'slug' => 'sovra-rappresentato',
            'status' => Concept::STATUS_ACTIVE,
        ]);
        foreach (range(1, 20) as $i) {
            ConceptQuestion::create(['concept_id' => $outlier->id, 'question' => "Domanda outlier {$i}?"]);
        }

        $summary = app(ContentGraphOperationalSummaryService::class)->summary();

        $this->assertFalse($summary['status']['healthy']);
        $this->assertTrue($summary['question_balance']['applicable']);
        $this->assertSame(7, $summary['question_balance']['population']);
        $this->assertSame(1, $summary['question_balance']['total']);

        $row = $summary['question_balance']['items'][0];
        $this->assertSame($outlier->id, $row['concept_id']);
        $this->assertSame(ConceptQuestionBalanceAuditService::OVER_REPRESENTED, $row['direction']);
        $this->assertStringContainsString((string) $outlier->id, $row['edit_url']);
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

        // Cantiere 78: ConceptQuestionBalanceAuditService aggiunge una
        // query bounded (Concept::active()->withCount('questions')->get())
        // — il floor sale da 9 a 10, lo stesso margine superiore resta.
        $this->assertGreaterThanOrEqual(10, $queryCount);
        $this->assertLessThanOrEqual(12, $queryCount);
    }
}
