<?php

namespace Tests\Feature\Admin;

use App\Models\SearchZeroResultQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 7 (programma "Kairus Organic Discovery"): report operativo —
 * sola lettura.
 */
class OrganicDiscoveryOperationalReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_report(): void
    {
        $this->get(route('admin.organic-discovery-operational-report'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_view_the_report(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('admin.organic-discovery-operational-report'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_report_with_zero_state_and_no_errors(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get(route('admin.organic-discovery-operational-report'))
            ->assertOk()
            ->assertSee('Report ricerca organica')
            ->assertSee('Nessun import Search Console disponibile.');
    }

    public function test_editor_sees_a_real_opportunity_count_in_the_report(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        SearchZeroResultQuery::create(['normalized_query' => 'query report reale', 'hit_count' => 5]);

        $this->actingAs($editor)->get(route('admin.organic-discovery-operational-report'))
            ->assertOk()
            ->assertSeeText('Totali: 1');
    }
}
