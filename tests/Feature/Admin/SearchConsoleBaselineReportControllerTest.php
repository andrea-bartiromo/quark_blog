<?php

namespace Tests\Feature\Admin;

use App\Models\SearchConsoleQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 1 (programma "Kairus Organic Discovery"). La logica del report
 * è già coperta da SearchConsoleBaselineReportServiceTest: qui si prova
 * solo la superficie HTTP (autorizzazione, selezione periodo, rendering).
 */
class SearchConsoleBaselineReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function row(array $overrides = []): SearchConsoleQuery
    {
        return SearchConsoleQuery::create(array_merge([
            'query' => 'query di prova',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 5,
            'impressions' => 100,
            'ctr' => 0.05,
            'position' => 8.0,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-1',
            'imported_at' => now(),
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.search-console-baseline-report'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_page(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.search-console-baseline-report'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_shows_an_empty_state_when_nothing_has_been_imported(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.search-console-baseline-report'));

        $response->assertOk();
        $response->assertSee('Nessun dato Search Console importato finora.');
    }

    public function test_editor_sees_totals_and_top_landing_pages_for_the_latest_period(): void
    {
        $this->row(['clicks' => 7, 'impressions' => 200]);

        $response = $this->actingAs($this->editor())->get(route('admin.search-console-baseline-report'));

        $response->assertOk();
        $response->assertSee('Baseline Search Console');
        $response->assertSee('kairus.it/notizie');
    }

    public function test_a_different_period_can_be_selected(): void
    {
        $this->row(['period_start' => '2026-07-01', 'period_end' => '2026-07-07', 'query' => 'periodo vecchio']);
        $this->row(['period_start' => '2026-08-01', 'period_end' => '2026-08-07', 'query' => 'periodo nuovo']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.search-console-baseline-report', ['periodo' => 1]));

        $response->assertOk();
        $response->assertSee('periodo vecchio');
        $response->assertDontSee('periodo nuovo');
    }

    public function test_viewing_the_report_performs_no_mutation(): void
    {
        $row = $this->row();
        $before = $row->refresh()->getAttributes();

        $this->actingAs($this->editor())->get(route('admin.search-console-baseline-report'));

        $this->assertSame($before, SearchConsoleQuery::find($row->id)->getAttributes());
    }
}
