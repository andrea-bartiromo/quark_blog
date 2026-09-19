<?php

namespace Tests\Feature\Admin;

use App\Models\TuringChapterSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 69 (programma "100 cantieri Kairus", dipende dai Cantieri 62,
 * 67, 68).
 *
 * Pagina di sola lettura: mostra le condizioni per la revisione beta
 * interna, non le imposta — nessun test qui verifica una scrittura,
 * perché nessuna scrittura è possibile da questa pagina.
 */
class TuringInternalBetaReadinessControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_guest_cannot_view_the_readiness_page(): void
    {
        $this->get(route('admin.turing.internal-beta-readiness'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_view_the_readiness_page(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('admin.turing.internal-beta-readiness'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_all_five_conditions_and_the_overall_state(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.internal-beta-readiness'));

        $response->assertOk();
        $response->assertSee('Anteprima amministrativa disponibile');
        $response->assertSee('Nessun hub/capitolo strutturalmente vuoto');
        $response->assertSee('Report completezza disponibile per la revisione');
        $response->assertSee('Fonti registrate per almeno un capitolo');
        $response->assertSee('Owner editoriale che approva la revisione interna');
        $response->assertSee('La revisione beta interna non è ancora pronta');
    }

    /**
     * Stesso principio già verificato per TrustPilotGateReadinessController:
     * nessun modo di scrivere una delle condizioni da questa pagina.
     */
    public function test_the_page_never_offers_a_form_to_set_any_condition(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.internal-beta-readiness'));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringNotContainsString('action="'.route('admin.turing.internal-beta-readiness').'"', $content);
        $this->assertStringNotContainsString('name="owner', $content);
    }

    public function test_the_page_reflects_real_sources_without_ever_declaring_the_beta_ready(): void
    {
        TuringChapterSource::create([
            'chapter' => 'enigma',
            'label' => 'On Computable Numbers',
            'url' => 'https://example.com/on-computable-numbers',
            'year' => 1936,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.turing.internal-beta-readiness'));

        $response->assertOk();
        $response->assertSee('1 fonte');
        // Owner resta comunque non determinabile: lo stato complessivo deve
        // restare "non pronto" anche con una fonte registrata.
        $response->assertSee('La revisione beta interna non è ancora pronta');
    }
}
