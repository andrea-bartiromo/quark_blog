<?php

namespace Tests\Feature\Admin;

use App\Models\TrustKnowledgeStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 42 (programma "100 cantieri Kairus", dipende dai Cantieri 40-41).
 *
 * Pagina di sola lettura: mostra le tre condizioni del NO-GO B-45, non le
 * imposta — nessun test qui verifica una scrittura, perché nessuna
 * scrittura è possibile da questa pagina.
 */
class TrustPilotGateReadinessControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_guest_cannot_view_the_gate_readiness_page(): void
    {
        $this->get(route('admin.trust-knowledge.gate-readiness'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_view_the_gate_readiness_page(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('admin.trust-knowledge.gate-readiness'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_all_three_conditions_and_the_overall_no_go_state(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.trust-knowledge.gate-readiness'));

        $response->assertOk();
        $response->assertSee('Owner editoriale assegnato');
        $response->assertSee('Contenuto sorgente reale approvato');
        $response->assertSee('Componente Fonti pubblico disponibile');
        $response->assertSee('Il pilot Trust pubblico resta in stato NO-GO');
    }

    /**
     * Il layout admin condiviso porta sempre con sé un form di ricerca e un
     * form di logout — legittimi, non riguardano questa pagina. Qui si
     * verifica invece che NON esista alcun modo di scrivere una delle tre
     * condizioni: nessun form che posta verso questa route, nessun campo
     * riconducibile a "owner" o "approvato".
     */
    public function test_the_page_never_offers_a_form_to_set_any_condition(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.trust-knowledge.gate-readiness'));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringNotContainsString('action="'.route('admin.trust-knowledge.gate-readiness').'"', $content);
        $this->assertStringNotContainsString('name="owner', $content);
        $this->assertStringNotContainsString('name="approvato', $content);
        $this->assertStringNotContainsString('name="approved', $content);
    }

    public function test_the_page_reflects_real_content_existing_without_ever_declaring_the_pilot_go(): void
    {
        TrustKnowledgeStatement::create([
            'domanda' => 'Una domanda di prova?',
            'consenso' => 'Consenso di prova.',
            'incertezza' => 'Incertezza di prova.',
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.trust-knowledge.gate-readiness'));

        $response->assertOk();
        $response->assertSee('1 voce');
        // Owner resta comunque non determinabile: lo stato complessivo deve
        // restare NO-GO anche con contenuto presente.
        $response->assertSee('Il pilot Trust pubblico resta in stato NO-GO');
    }

    public function test_it_does_not_expose_the_page_outside_the_admin_prefix(): void
    {
        $this->get('/cosa-sappiamo-davvero/gate-pubblicazione')->assertNotFound();
    }
}
