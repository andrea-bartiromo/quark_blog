<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Batch 05 Mission 8 — Percorsi con publish_at (docs/PERCORSI_SCHEDULING_V1_SPEC.md,
 * PR #320) entrano nel Calendario articoli esistente, mai in un secondo
 * calendario. La pagina resta "sola visualizzazione": nessuna scrittura da
 * qui.
 */
class ArticleCalendarPercorsiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_an_active_percorso_scheduled_in_the_future_appears_as_programmato(): void
    {
        $editor = $this->editor();
        // Ancorato a un mese intero nel futuro: publish_at resta un istante
        // futuro rispetto a "adesso" indipendentemente dal giorno del mese in
        // cui gira il test (a differenza di "startOfMonth()->addDays(N)",
        // che puo' cadere prima di "oggi" a seconda della data corrente).
        $anchor = now()->addMonth()->startOfMonth();
        ContentCluster::factory()->create([
            'name' => 'Percorso del futuro',
            'is_active' => true,
            'publish_at' => $anchor->clone()->addDays(5)->setTime(10, 0),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'list', 'data' => $anchor->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('Percorso del futuro');
        $response->assertSee('Percorso programmato');
    }

    public function test_an_active_percorso_scheduled_in_the_past_appears_as_pubblicato(): void
    {
        $editor = $this->editor();
        // Ancorato a un mese intero nel passato: publish_at resta un istante
        // passato rispetto a "adesso" indipendentemente dal giorno del mese
        // in cui gira il test.
        $anchor = now()->subMonth()->startOfMonth();
        ContentCluster::factory()->create([
            'name' => 'Percorso già visibile',
            'is_active' => true,
            'publish_at' => $anchor->clone()->addDays(2)->setTime(9, 0),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'list', 'data' => $anchor->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('Percorso già visibile');
        $response->assertSee('Percorso pubblicato');
    }

    public function test_an_inactive_percorso_never_appears_even_with_a_publish_at_in_range(): void
    {
        $editor = $this->editor();
        ContentCluster::factory()->create([
            'name' => 'Percorso disattivato',
            'is_active' => false,
            'publish_at' => now()->startOfMonth()->addDays(3),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'list']));

        $response->assertOk();
        $response->assertDontSee('Percorso disattivato');
    }

    public function test_an_active_percorso_with_no_publish_at_never_appears_on_the_calendar(): void
    {
        // Comportamento legacy (pubblico subito, nessuna data specifica da
        // collocare sul calendario) — nessun evento a cui agganciarlo.
        $editor = $this->editor();
        ContentCluster::factory()->create([
            'name' => 'Percorso legacy sempre attivo',
            'is_active' => true,
            'publish_at' => null,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'list']));

        $response->assertOk();
        $response->assertDontSee('Percorso legacy sempre attivo');
    }

    public function test_a_percorso_scheduled_outside_the_visible_range_is_not_shown(): void
    {
        $editor = $this->editor();
        ContentCluster::factory()->create([
            'name' => 'Percorso fuori mese',
            'is_active' => true,
            'publish_at' => now()->startOfMonth()->subMonths(2),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'list']));

        $response->assertOk();
        $response->assertDontSee('Percorso fuori mese');
    }

    public function test_status_filter_pubblicato_hides_scheduled_percorsi(): void
    {
        $editor = $this->editor();
        $anchor = now()->addMonth()->startOfMonth();
        ContentCluster::factory()->create([
            'name' => 'Percorso futuro filtrato',
            'is_active' => true,
            'publish_at' => $anchor->clone()->addDays(5),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', [
            'vista' => 'list',
            'data' => $anchor->format('Y-m-d'),
            'stato' => Article::STATUS_PUBLISHED,
        ]));

        $response->assertOk();
        $response->assertDontSee('Percorso futuro filtrato');
    }

    public function test_month_view_renders_a_percorso_chip(): void
    {
        $editor = $this->editor();
        ContentCluster::factory()->create([
            'name' => 'Percorso in vetrina',
            'is_active' => true,
            'publish_at' => now()->startOfMonth()->addDays(4)->setTime(8, 0),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar'));

        $response->assertOk();
        $response->assertSee('Percorso in vetrina');
    }

    public function test_calendar_still_never_exposes_a_write_endpoint_for_percorsi(): void
    {
        $this->assertFalse(Route::has('admin.articles.calendar.percorsi.update'));
    }
}
