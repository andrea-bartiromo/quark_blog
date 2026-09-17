<?php

namespace Tests\Feature;

use App\Models\TuringChapterView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 68 (programma "100 cantieri Kairus"). Verifica che
 * TuringNavigationMetricsService::recordView() sia davvero collegato ai
 * controller pubblici — solo quando lo Speciale è davvero pubblico
 * (config('turing.chapters_public'), Cantieri 57/63): la landing "In
 * arrivo" non è la stessa esperienza e non deve mai generare un evento.
 */
class TuringNavigationMetricsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const CHAPTER_ROUTES = [
        'turing.enigma' => 'enigma',
        'turing.ai' => 'ai',
        'turing.legacy' => 'legacy',
        'turing.computation' => 'computation',
        'turing.intelligence' => 'intelligence',
    ];

    public function test_visiting_the_coming_soon_landing_records_no_event(): void
    {
        $this->assertFalse((bool) config('turing.chapters_public'));

        $this->get(route('turing'))->assertOk();

        $this->assertSame(0, TuringChapterView::count());
    }

    public function test_visiting_a_gated_chapter_route_records_no_event(): void
    {
        $this->assertFalse((bool) config('turing.chapters_public'));

        foreach (array_keys(self::CHAPTER_ROUTES) as $routeName) {
            $this->get(route($routeName));
        }

        $this->assertSame(0, TuringChapterView::count());
    }

    public function test_visiting_the_real_hub_once_public_records_exactly_one_hub_event(): void
    {
        config(['turing.chapters_public' => true]);

        $this->get(route('turing'))->assertOk();

        $this->assertSame(1, TuringChapterView::where('chapter', 'hub')->count());
        $this->assertSame(1, TuringChapterView::count());
    }

    public function test_visiting_each_real_chapter_once_public_records_exactly_one_event_per_chapter(): void
    {
        config(['turing.chapters_public' => true]);

        foreach (self::CHAPTER_ROUTES as $routeName => $chapter) {
            $this->get(route($routeName))->assertOk();

            $this->assertSame(
                1,
                TuringChapterView::where('chapter', $chapter)->count(),
                "atteso esattamente un evento per il capitolo '{$chapter}'"
            );
        }

        $this->assertSame(count(self::CHAPTER_ROUTES), TuringChapterView::count());
    }

    /**
     * Codex (PR #623, P1): gli audit interni (SEO/WCAG, dashboard salute
     * pubblica + baseline mensile programmata) raggiungono ogni pagina
     * Turing abilitata con un vero GET in-process
     * (App\Services\PublicPages\InProcessPageFetcher, marcato con questo
     * stesso header) — senza l'esclusione, ogni esecuzione dell'audit
     * gonfierebbe le metriche di navigazione reali con traffico
     * sintetico, esattamente come per le analytics degli articoli (vedi
     * ArticleController::show()).
     */
    public function test_visiting_the_real_hub_with_the_internal_audit_header_records_no_event(): void
    {
        config(['turing.chapters_public' => true]);

        $this->withHeaders(['X-Kairus-Internal-Audit' => '1'])
            ->get(route('turing'))
            ->assertOk();

        $this->assertSame(0, TuringChapterView::count());
    }

    public function test_visiting_each_real_chapter_with_the_internal_audit_header_records_no_event(): void
    {
        config(['turing.chapters_public' => true]);

        foreach (array_keys(self::CHAPTER_ROUTES) as $routeName) {
            $this->withHeaders(['X-Kairus-Internal-Audit' => '1'])
                ->get(route($routeName))
                ->assertOk();
        }

        $this->assertSame(0, TuringChapterView::count());
    }

    public function test_admin_turing_edit_page_shows_the_aggregated_navigation_panel(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        config(['turing.chapters_public' => true]);
        $this->get(route('turing.enigma'));

        $response = $this->actingAs($editor)->get(route('admin.turing'));

        $response->assertOk();
        $response->assertSeeText('Navigazione aggregata');
        $response->assertSeeText('enigma');
    }
}
