<?php

namespace Tests\Feature\Admin;

use App\Models\AuditFindingStatus;
use App\Models\PublicHealthBaseline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 30 (programma 100-cantieri Kairus). Superficie HTTP per
 * PublicHealthDashboardService, finora raggiungibile solo dai singoli
 * comandi Artisan dei Cantieri 22-29. Usa il servizio reale (nessun fake):
 * ogni audit sottostante ha già la propria suite dedicata, qui si prova
 * solo che la pagina risponda e applichi lo stesso controllo di accesso
 * delle altre pagine di analisi.
 *
 * Cantiere 31: l'unica scrittura possibile da questa pagina è
 * updateFindingStatus() — stesso pattern di
 * SearchOpportunityController::updateStatus() — provata separatamente
 * dalla sola visualizzazione, che resta senza effetti collaterali.
 *
 * Cantiere 35: il confronto "vs mese scorso" è solo lettura di
 * PublicHealthBaseline (mai scritto da questa pagina — solo dal comando
 * schedulato public-health:record-monthly-baseline): assente finché non
 * esiste ancora una baseline per il mese precedente.
 */
class PublicHealthDashboardControllerTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.public-health'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_page(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.public-health'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_dashboard(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.public-health'));

        $response->assertOk();
        $response->assertSee('Salute pubblica');
        $response->assertSee('SEO/canonical/JSON-LD');
        $response->assertSee('Accessibilità WCAG statica');
        $response->assertSee('Non aggregabili qui');
        $response->assertSee('Performance (Core Web Vitals)');
    }

    public function test_no_trend_is_shown_when_no_previous_baseline_exists(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.public-health'));

        $response->assertOk();
        $response->assertDontSee('vs '.Carbon::now()->subMonthNoOverflow()->format('Y-m'));
    }

    public function test_shows_a_trend_against_the_most_recent_previous_month_baseline(): void
    {
        $previousPeriod = Carbon::now()->subMonthNoOverflow()->format('Y-m');

        PublicHealthBaseline::query()->create([
            'domain' => 'seo',
            'period' => $previousPeriod,
            'finding_count' => 5,
            'open_count' => 5,
            'dismissed_count' => 0,
            'high_open_count' => 2,
            'checked_count' => 10,
            'total_count' => 10,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.public-health'));

        $response->assertOk();
        $response->assertSee('vs '.$previousPeriod);
    }

    public function test_viewing_the_page_performs_no_mutation_no_matter_how_many_times_it_is_viewed(): void
    {
        $editor = $this->editor();
        $before = $editor->refresh()->getAttributes();

        $this->actingAs($editor)->get(route('admin.public-health'));
        $this->actingAs($editor)->get(route('admin.public-health'));

        $this->assertSame($before, User::find($editor->id)->getAttributes());
        $this->assertDatabaseCount('audit_finding_statuses', 0);
    }

    public function test_guest_cannot_update_a_finding_status(): void
    {
        $this->post(route('admin.public-health.update-status'), [
            'domain' => 'seo',
            'finding_key' => 'seo|home',
            'status' => AuditFindingStatus::STATUS_IN_CARICO,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('audit_finding_statuses', 0);
    }

    public function test_author_role_cannot_update_a_finding_status(): void
    {
        $this->actingAs($this->author())->post(route('admin.public-health.update-status'), [
            'domain' => 'seo',
            'finding_key' => 'seo|home',
            'status' => AuditFindingStatus::STATUS_IN_CARICO,
        ])->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseCount('audit_finding_statuses', 0);
    }

    public function test_editor_can_take_charge_of_a_finding(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.public-health.update-status'), [
            'domain' => 'seo',
            'finding_key' => 'seo|home',
            'status' => AuditFindingStatus::STATUS_IN_CARICO,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('audit_finding_statuses', [
            'domain' => 'seo',
            'finding_key' => 'seo|home',
            'status' => AuditFindingStatus::STATUS_IN_CARICO,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_updating_a_finding_status_rejects_an_unknown_domain(): void
    {
        $this->actingAs($this->editor())->post(route('admin.public-health.update-status'), [
            'domain' => 'non-esiste',
            'finding_key' => 'seo|home',
            'status' => AuditFindingStatus::STATUS_IN_CARICO,
        ])->assertSessionHasErrors('domain');

        $this->assertDatabaseCount('audit_finding_statuses', 0);
    }

    public function test_updating_a_finding_status_rejects_an_unknown_status(): void
    {
        $this->actingAs($this->editor())->post(route('admin.public-health.update-status'), [
            'domain' => 'seo',
            'finding_key' => 'seo|home',
            'status' => 'non-esiste',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseCount('audit_finding_statuses', 0);
    }
}
