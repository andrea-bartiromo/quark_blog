<?php

namespace Tests\Feature\Admin;

use App\Models\SearchConsoleQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialOpportunityDecisionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_decisions(): void
    {
        $this->get(route('admin.editorial-opportunity-decisions'))->assertRedirect(route('login'));
    }

    public function test_editor_sees_empty_state_without_imports(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor)->get(route('admin.editorial-opportunity-decisions'))
            ->assertOk()->assertSee('Nessun import Search Console disponibile.');
    }

    public function test_editor_can_filter_by_decision_without_side_effects(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        SearchConsoleQuery::create([
            'query' => 'query senza pagina', 'page_url' => '', 'article_id' => null,
            'clicks' => 0, 'impressions' => 100, 'ctr' => 0.0, 'position' => 15.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);

        $this->actingAs($editor)->get(route('admin.editorial-opportunity-decisions', ['decision' => 'verify_data']))
            ->assertOk()->assertSee('query senza pagina');
        $this->assertSame(1, SearchConsoleQuery::count());
    }
}
