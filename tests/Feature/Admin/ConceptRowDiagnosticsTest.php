<?php

namespace Tests\Feature\Admin;

use App\Models\Concept;
use App\Models\User;
use App\Services\ContentGraph\ConceptHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConceptRowDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    public function test_index_shows_compact_health_and_verify_cta_for_incomplete_concept(): void
    {
        $concept = Concept::create([
            'name' => 'Concept incompleto',
            'slug' => 'concept-incompleto',
            'status' => Concept::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.concepts.index'));

        $response->assertOk();
        $response->assertSee('Salute operativa');
        $response->assertSee('Incompleto');
        $response->assertSee('Diagnosi (3)');
        $response->assertSee(ConceptHealthService::ACTIVE_WITHOUT_ARTICLE_LINK);
        $response->assertSee(ConceptHealthService::ACTIVE_WITHOUT_QUESTIONS);
        $response->assertSee(ConceptHealthService::NO_PUBLIC_ANSWERABLE_QUESTION);
        $response->assertSee('Verifica');
        $response->assertSee(route('admin.concepts.edit', $concept), false);
    }

    public function test_index_uses_progressive_disclosure_and_hides_diagnostics_for_ready_row(): void
    {
        Concept::create([
            'name' => 'Concept inattivo coerente',
            'slug' => 'concept-inattivo-coerente',
            'status' => Concept::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.concepts.index'));

        $response->assertOk();
        $response->assertSee('Pronto');
        $response->assertDontSee('Diagnosi (');
        $response->assertSee('Modifica');
    }
}
