<?php

namespace Tests\Feature\Admin;

use App\Models\SearchZeroResultQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 31 — Search Zero-Result Diagnostics: superficie admin di sola
 * lettura, stessa gate 'editor' di ogni altra rotta sotto /admin.
 */
class SearchZeroResultDiagnosticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.search-zero-result-diagnostics'))->assertRedirect(route('login'));
    }

    public function test_a_non_editor_is_redirected_away_from_the_admin_panel(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('admin.search-zero-result-diagnostics'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_an_editor_sees_the_recorded_diagnostics(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        SearchZeroResultQuery::create(['normalized_query' => 'buco nero rotante', 'hit_count' => 5]);

        $response = $this->actingAs($editor)->get(route('admin.search-zero-result-diagnostics'));

        $response->assertOk();
        $response->assertSee('buco nero rotante');
        $response->assertSee('5');
    }

    public function test_an_editor_sees_an_empty_state_with_no_diagnostics_yet(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor)->get(route('admin.search-zero-result-diagnostics'));

        $response->assertOk();
        $response->assertSee('Nessuna ricerca a zero risultati registrata ancora.');
    }
}
