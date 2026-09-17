<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 59 (programma "100 cantieri Kairus"). La mappa concettuale è
 * uno strumento interno per l'editor (sola lettura), mai una pagina
 * pubblica: nessuna rotta pubblica la espone, solo l'area admin dietro
 * auth+editor.
 */
class TuringConceptMapPageTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_guest_cannot_view_the_concept_map(): void
    {
        $this->get(route('admin.turing.concept-map'))->assertRedirect(route('login'));
    }

    public function test_editor_can_view_the_concept_map(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.concept-map'));

        $response->assertOk();
        $response->assertSeeText('Mappa concettuale');
        $response->assertSeeText('Enigma (la macchina)');
        $response->assertSeeText('Persecuzione (1952)');
    }

    public function test_the_page_groups_concepts_under_every_real_chapter_heading(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.concept-map'));

        $response->assertOk();
        foreach (['enigma', 'computation', 'intelligence', 'ai', 'legacy'] as $chapter) {
            $response->assertSeeText($chapter);
        }
    }

    public function test_the_page_shows_cross_chapter_richiami_with_their_qualificatori(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.concept-map'));

        $response->assertOk();
        // "Macchina universale" ha richiami sia in AI (cenno) che in Legacy
        // (teaser) — il qualificatore non deve mai sparire dalla resa
        // (Codex PR #625, P2).
        $response->assertSeeText('Ai (cenno) · Legacy (teaser)');
    }

    public function test_the_turing_lite_editor_links_to_the_concept_map(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing'));

        $response->assertOk();
        $response->assertSee(route('admin.turing.concept-map'), false);
    }
}
