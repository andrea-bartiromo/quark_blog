<?php

namespace Tests\Feature\Admin;

use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\TrustKnowledgeStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 38 (programma "100 cantieri Kairus"): CRUD interno del modello
 * "Cosa sappiamo davvero" — verifica soprattutto che resti un modello
 * SOLO admin: nessuna route pubblica lo espone.
 */
class TrustKnowledgeStatementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function statement(array $overrides = []): TrustKnowledgeStatement
    {
        return TrustKnowledgeStatement::create(array_merge([
            'domanda' => 'Il caffè fa male al cuore?',
            'consenso' => 'Un consumo moderato non è associato a un aumento del rischio cardiovascolare in adulti sani.',
            'incertezza' => 'Gli effetti su chi ha aritmie preesistenti restano dibattuti tra gli studi disponibili.',
        ], $overrides));
    }

    public function test_guest_cannot_view_the_index(): void
    {
        $this->get(route('admin.trust-knowledge.index'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_view_the_create_form(): void
    {
        $this->get(route('admin.trust-knowledge.create'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_view_the_index(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('admin.trust-knowledge.index'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_index_with_zero_state(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.index'))
            ->assertOk()
            ->assertSee('Cosa sappiamo davvero')
            ->assertSee('Nessuna voce ancora.');
    }

    public function test_editor_sees_a_real_statement_in_the_index(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $this->statement(['domanda' => 'Domanda visibile in elenco']);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.index'))
            ->assertOk()
            ->assertSee('Domanda visibile in elenco')
            ->assertSee('Mai controllato');
    }

    public function test_editor_sees_the_create_form(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.create'))
            ->assertOk()
            ->assertSee('Nuova voce');
    }

    public function test_editor_sees_the_edit_form_prefilled(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $statement = $this->statement(['domanda' => 'Domanda da modificare']);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.edit', $statement))
            ->assertOk()
            ->assertSee('Modifica voce')
            ->assertSee('Domanda da modificare');
    }

    public function test_editor_can_create_a_statement(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [
            'domanda' => 'I vaccini causano autismo?',
            'consenso' => 'Nessuno studio metodologicamente valido ha mai replicato un legame causale.',
            'incertezza' => 'Nessuna incertezza scientifica residua su questo punto specifico.',
            'cosa_manca' => '',
        ]);

        $response->assertRedirect(route('admin.trust-knowledge.index'));
        $this->assertDatabaseHas('trust_knowledge_statements', [
            'domanda' => 'I vaccini causano autismo?',
            'created_by' => $editor->id,
        ]);
    }

    public function test_an_author_cannot_create_a_statement(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->post(route('admin.trust-knowledge.store'), [
            'domanda' => 'Domanda',
            'consenso' => 'Consenso',
            'incertezza' => 'Incertezza',
        ])->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseCount('trust_knowledge_statements', 0);
    }

    public function test_creating_a_statement_requires_domanda_consenso_and_incertezza(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [])
            ->assertSessionHasErrors(['domanda', 'consenso', 'incertezza']);

        $this->assertDatabaseCount('trust_knowledge_statements', 0);
    }

    public function test_editor_can_link_a_statement_to_a_concept_and_a_cluster(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $concept = Concept::create(['name' => 'Vaccini', 'status' => Concept::STATUS_ACTIVE]);
        $cluster = ContentCluster::create(['name' => 'Percorso salute', 'slug' => 'percorso-salute', 'is_active' => true]);

        $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [
            'domanda' => 'Domanda collegata',
            'consenso' => 'Consenso.',
            'incertezza' => 'Incertezza.',
            'concept_id' => $concept->id,
            'content_cluster_id' => $cluster->id,
        ]);

        $this->assertDatabaseHas('trust_knowledge_statements', [
            'domanda' => 'Domanda collegata',
            'concept_id' => $concept->id,
            'content_cluster_id' => $cluster->id,
        ]);
    }

    public function test_editor_can_update_a_statement_including_the_manual_last_checked_date(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $statement = $this->statement();

        $this->actingAs($editor)->put(route('admin.trust-knowledge.update', $statement), [
            'domanda' => $statement->domanda,
            'consenso' => $statement->consenso,
            'incertezza' => $statement->incertezza,
            'last_checked_at' => '2026-09-01',
            'last_checked_by' => 'Redazione scienza',
        ])->assertRedirect(route('admin.trust-knowledge.index'));

        $statement->refresh();
        $this->assertTrue($statement->hasBeenChecked());
        $this->assertSame('2026-09-01', $statement->last_checked_at->format('Y-m-d'));
        $this->assertSame('Redazione scienza', $statement->last_checked_by);
    }

    public function test_an_author_cannot_update_a_statement(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $statement = $this->statement();

        $this->actingAs($author)->put(route('admin.trust-knowledge.update', $statement), [
            'domanda' => 'Tentativo non autorizzato',
            'consenso' => $statement->consenso,
            'incertezza' => $statement->incertezza,
        ])->assertRedirect(route('redazione.dashboard'));

        $this->assertSame($statement->domanda, $statement->fresh()->domanda);
    }

    public function test_editor_can_delete_a_statement(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $statement = $this->statement();

        $this->actingAs($editor)->delete(route('admin.trust-knowledge.destroy', $statement))
            ->assertRedirect(route('admin.trust-knowledge.index'));

        $this->assertDatabaseMissing('trust_knowledge_statements', ['id' => $statement->id]);
    }

    public function test_an_author_cannot_delete_a_statement(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $statement = $this->statement();

        $this->actingAs($author)->delete(route('admin.trust-knowledge.destroy', $statement))
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseHas('trust_knowledge_statements', ['id' => $statement->id]);
    }

    /**
     * Fail-closed per costruzione: nessuna route pubblica deve mai
     * risolvere verso questo modello — è un modello interno, non un
     * pilot pubblico (si legga il docblock di TrustKnowledgeStatement).
     */
    public function test_no_public_route_exposes_trust_knowledge_statements(): void
    {
        $statement = $this->statement();

        $publicGuesses = [
            '/cosa-sappiamo-davvero',
            '/cosa-sappiamo-davvero/'.$statement->id,
            '/trust-knowledge',
            '/trust-knowledge/'.$statement->id,
        ];

        foreach ($publicGuesses as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
