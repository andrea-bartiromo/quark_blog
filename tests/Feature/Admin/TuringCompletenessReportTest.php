<?php

namespace Tests\Feature\Admin;

use App\Models\TuringChapterSource;
use App\Models\TuringChapterView;
use App\Models\User;
use App\Services\Turing\TuringCompletenessReportService;
use App\Services\Turing\TuringConceptMapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 67 (programma "100 cantieri Kairus"): "Report completezza
 * Turing". Vedi il docblock di TuringCompletenessReportService per il
 * razionale — nessuna riga qui nasce da un'ipotesi, ogni asserzione
 * verifica un aggregato reale (DB/mappa concettuale statica già
 * esistente) contro un caso costruito esplicitamente nel test.
 */
class TuringCompletenessReportTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_service_counts_real_sources_per_chapter(): void
    {
        TuringChapterSource::create(['chapter' => 'enigma', 'label' => 'On Computable Numbers', 'url' => 'https://example.com/1', 'year' => 1936, 'sort_order' => 1]);
        TuringChapterSource::create(['chapter' => 'enigma', 'label' => 'Altra fonte', 'url' => 'https://example.com/2', 'year' => 1940, 'sort_order' => 2]);
        TuringChapterSource::create(['chapter' => 'legacy', 'label' => 'Scuse pubbliche', 'url' => 'https://example.com/3', 'year' => 2009, 'sort_order' => 1]);

        $report = app(TuringCompletenessReportService::class)->build();

        $this->assertSame(2, $report['chapters']['enigma']['sources_count']);
        $this->assertSame(1, $report['chapters']['legacy']['sources_count']);
        $this->assertSame(0, $report['chapters']['ai']['sources_count']);
    }

    public function test_service_reuses_the_real_concept_map_without_inventing_a_score(): void
    {
        $report = app(TuringCompletenessReportService::class)->build();
        $expected = TuringConceptMapService::conceptsByChapter();

        foreach (['enigma', 'ai', 'legacy', 'computation', 'intelligence'] as $chapter) {
            $this->assertSame(
                array_map(
                    fn (array $c) => ['argomento' => $c['argomento'], 'livello_approfondimento' => $c['livello_approfondimento']],
                    $expected[$chapter]
                ),
                $report['chapters'][$chapter]['concepts_as_main_chapter']
            );
        }
    }

    public function test_service_reports_real_publication_state(): void
    {
        config(['turing.chapters_public' => false]);
        $this->assertFalse(app(TuringCompletenessReportService::class)->build()['chapters_public']);

        config(['turing.chapters_public' => true]);
        $this->assertTrue(app(TuringCompletenessReportService::class)->build()['chapters_public']);
    }

    public function test_service_reports_real_navigation_counts_not_a_fabricated_zero(): void
    {
        TuringChapterView::create(['chapter' => 'enigma']);
        TuringChapterView::create(['chapter' => 'enigma']);

        $report = app(TuringCompletenessReportService::class)->build();

        $this->assertSame(2, $report['chapters']['enigma']['navigation']['count']);
        $this->assertSame(0, $report['chapters']['ai']['navigation']['count']);
    }

    public function test_editor_can_view_the_completeness_report(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.completeness-report'));

        $response->assertOk()->assertSeeText('Report completezza');
    }

    public function test_completeness_report_requires_authentication(): void
    {
        $this->get(route('admin.turing.completeness-report'))->assertRedirect(route('login'));
    }

    public function test_completeness_report_renders_every_real_chapter(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.completeness-report'));

        $response->assertOk();

        // Il testo sottostante resta minuscolo: "text-transform:capitalize"
        // e' solo visuale (CSS), non cambia il contenuto letto da assertSeeText.
        foreach (['enigma', 'ai', 'legacy', 'computation', 'intelligence'] as $chapter) {
            $response->assertSeeText($chapter);
        }
    }
}
