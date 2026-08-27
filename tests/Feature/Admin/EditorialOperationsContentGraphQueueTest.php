<?php

namespace Tests\Feature\Admin;

use App\Models\Concept;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialOperationsContentGraphQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_content_graph_what_why_and_where(): void
    {
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor'])->save();

        $concept = Concept::create([
            'name' => 'Concept da verificare',
            'slug' => 'concept-da-verificare',
            'status' => Concept::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($editor)
            ->get(route('admin.editorial-operations'));

        $response->assertOk();
        $response->assertSee('Content Graph da verificare');
        $response->assertSee('NO_QUESTIONS');
        $response->assertSee($concept->name);
        $response->assertSee('Il Concept attivo non ha domande.');
        $response->assertSee(route('admin.concepts.edit', $concept), false);
        $this->assertSame(1, substr_count($response->getContent(), 'Pubblicati senza Concept'));
    }
}
