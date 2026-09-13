<?php

namespace Tests\Feature\Admin;

use App\Models\SearchConsoleCoverageImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchConsoleCoverageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_editor_can_open_the_empty_coverage_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $this->actingAs($user)->get(route('admin.search-console-coverage'))->assertOk()->assertSee('Nessun export Coverage importato');
    }

    public function test_unauthenticated_visitors_cannot_open_the_dashboard(): void
    {
        $this->get(route('admin.search-console-coverage'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_the_latest_private_import_without_external_calls(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $import = SearchConsoleCoverageImport::create(['property' => 'sc-domain:example.test', 'observed_at' => '2026-09-13', 'source' => 'manual_csv', 'import_batch' => 'test', 'imported_at' => now()]);
        $import->issues()->create(['reason' => 'Pagina con reindirizzamento', 'page_count' => 5, 'classification' => 'expected', 'recommendation' => 'Verificare solo se necessario.']);

        $this->actingAs($user)->get(route('admin.search-console-coverage'))->assertOk()->assertSee('sc-domain:example.test')->assertSee('Pagina con reindirizzamento');
    }
}
