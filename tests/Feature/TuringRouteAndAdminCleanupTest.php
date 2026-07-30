<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TuringRouteAndAdminCleanupTest extends TestCase
{
    use RefreshDatabase;

    // Questi test esercitano /turing e i capitoli /turing/* assumendo
    // che siano pubblici (contenuto renderizzato, non un redirect):
    // stato futuro dietro config('turing.chapters_public'), attivato qui
    // esplicitamente. Il default di produzione (false, landing "In
    // arrivo" + redirect) e' coperto da TuringReleaseGateTest.
    protected function setUp(): void
    {
        parent::setUp();

        config(['turing.chapters_public' => true]);
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    // App\Providers\TuringServiceProvider registrava un secondo /turing/ai
    // (URI /turing/ia) con lo stesso nome route: route('turing.ai') doveva
    // risolvere qualunque URI vincesse l'ordine di boot dei provider. Con il
    // provider rimosso, routes/web.php resta l'unica fonte e il nome produce
    // sempre l'URI canonica.
    public function test_turing_ai_route_name_always_resolves_to_the_canonical_uri(): void
    {
        $this->assertSame(url('/turing/ai'), route('turing.ai'));
    }

    public function test_turing_ai_page_is_reachable(): void
    {
        $this->get(route('turing.ai'))->assertOk();
    }

    // L'URI duplicata registrata dal provider rimosso non deve piu' rispondere.
    public function test_the_duplicate_turing_ia_uri_no_longer_resolves(): void
    {
        $this->get('/turing/ia')->assertNotFound();
    }

    // Nessuna route pubblica duplicata deve restare registrata per /turing.
    public function test_no_duplicate_public_routes_remain_for_turing(): void
    {
        $turingUriRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->uri() === 'turing' && in_array('GET', $route->methods(), true));

        $this->assertCount(1, $turingUriRoutes);

        $enigmaUriRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->uri() === 'turing/enigma' && in_array('GET', $route->methods(), true));

        $this->assertCount(1, $enigmaUriRoutes);
    }

    public function test_all_public_turing_pages_still_render_successfully(): void
    {
        $this->get(route('turing'))->assertOk();
        $this->get(route('turing.enigma'))->assertOk();
        $this->get(route('turing.ai'))->assertOk();
        $this->get(route('turing.legacy'))->assertOk();
        $this->get(route('turing.computation'))->assertOk();
        $this->get(route('turing.intelligence'))->assertOk();
    }

    // L'editor admin realmente instradato (admin.turing -> admin.turing-lite)
    // deve continuare a funzionare invariato dopo la rimozione delle viste morte.
    public function test_the_currently_routed_turing_admin_editor_still_works(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing'));

        $response->assertOk();
        $response->assertViewIs('admin.turing-lite');
    }

    public function test_the_dead_admin_turing_views_have_been_removed(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/admin/turing.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/admin/turing-v2.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/admin/partials/turing-hero-advanced.blade.php'));
        $this->assertFileExists(resource_path('views/admin/turing-lite.blade.php'));
    }

    public function test_the_duplicate_route_service_provider_has_been_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Providers/TuringServiceProvider.php'));
    }
}
