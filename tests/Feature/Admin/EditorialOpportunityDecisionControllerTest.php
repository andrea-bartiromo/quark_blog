<?php

namespace Tests\Feature\Admin;

use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService;
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

    /**
     * Codex (PR #600, P2): il collegamento a "Opportunità di ricerca" aveva
     * rimosso per errore la guardia che evitava di chiamare
     * EditorialOpportunityDecisionService::decide() quando non c'è alcun
     * periodo selezionato — decide() esegue
     * OrganicDiscoveryReadinessService::auditAll() (audit dell'intero corpus
     * di articoli pubblici) all'inizio, indipendentemente da quante
     * opportunità gli vengono passate. Senza nessun import Search Console
     * questo pagherebbe quell'audit costoso solo per mostrare "Nessun
     * import disponibile".
     */
    public function test_it_never_audits_readiness_when_no_import_exists(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->partialMock(OrganicDiscoveryReadinessService::class, function ($mock) {
            $mock->shouldNotReceive('auditAll');
        });

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
