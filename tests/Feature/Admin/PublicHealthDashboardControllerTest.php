<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 30 (programma 100-cantieri Kairus). Superficie HTTP per
 * PublicHealthDashboardService, finora raggiungibile solo dai singoli
 * comandi Artisan dei Cantieri 22-29. Usa il servizio reale (nessun fake):
 * ogni audit sottostante ha già la propria suite dedicata, qui si prova
 * solo che la pagina risponda, applichi lo stesso controllo di accesso
 * delle altre pagine di analisi e non scriva mai sul database.
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

    public function test_the_page_performs_no_mutation_no_matter_how_many_times_it_is_viewed(): void
    {
        $editor = $this->editor();
        $before = $editor->refresh()->getAttributes();

        $this->actingAs($editor)->get(route('admin.public-health'));
        $this->actingAs($editor)->get(route('admin.public-health'));

        $this->assertSame($before, User::find($editor->id)->getAttributes());
    }
}
