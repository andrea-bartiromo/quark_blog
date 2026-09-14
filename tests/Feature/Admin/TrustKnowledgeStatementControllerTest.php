<?php

namespace Tests\Feature\Admin;

use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\TrustKnowledgeStatement;
use App\Models\User;
use Carbon\Carbon;
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

    /**
     * Cantiere 39, Codex (PR #596): il form non deve offrire opzioni che
     * la validazione poi rifiuta — un Concept nasce "bozza" per default,
     * quindi questo era un caso comune, non un edge case.
     */
    public function test_the_create_form_never_offers_an_inactive_concept_or_cluster(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        Concept::create(['name' => 'Concetto bozza', 'status' => Concept::STATUS_DRAFT]);
        ContentCluster::create(['name' => 'Percorso archiviato', 'slug' => 'percorso-archiviato-form', 'is_active' => false]);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.create'))
            ->assertOk()
            ->assertDontSee('Concetto bozza')
            ->assertDontSee('Percorso archiviato');
    }

    public function test_the_edit_form_preserves_an_already_linked_inactive_concept_marked_as_archived(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $concept = Concept::create(['name' => 'Concetto poi archiviato', 'status' => Concept::STATUS_ACTIVE]);
        $statement = $this->statement(['concept_id' => $concept->id]);
        $concept->update(['status' => Concept::STATUS_INACTIVE]);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.edit', $statement))
            ->assertOk()
            ->assertSee('Concetto poi archiviato (archiviato)');
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

    /**
     * Cantiere 39: last_checked_at non può essere una data futura — stesso
     * principio già in uso per ArticleSearchProfile::last_editorial_review_at.
     */
    public function test_creating_a_statement_rejects_a_future_last_checked_date(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [
            'domanda' => 'Domanda',
            'consenso' => 'Consenso.',
            'incertezza' => 'Incertezza.',
            'last_checked_at' => now()->addDay()->format('Y-m-d'),
        ])->assertSessionHasErrors(['last_checked_at']);

        $this->assertDatabaseCount('trust_knowledge_statements', 0);
    }

    public function test_creating_a_statement_accepts_todays_last_checked_date(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [
            'domanda' => 'Domanda',
            'consenso' => 'Consenso.',
            'incertezza' => 'Incertezza.',
            'last_checked_at' => now()->format('Y-m-d'),
        ])->assertSessionDoesntHaveErrors(['last_checked_at']);

        $this->assertDatabaseCount('trust_knowledge_statements', 1);
    }

    /**
     * Cantiere 39, Codex (PR #596): l'app gira in UTC ma il fuso
     * editoriale è Europe/Rome — durante la prima ora/due dopo
     * mezzanotte a Roma, "oggi" a Roma è già il giorno dopo rispetto a
     * "oggi" in UTC. Alle 23:30 UTC del 14/09 sono le 01:30 CEST del
     * 15/09: un editor che inserisce "15/09" (oggi, a Roma) non deve
     * vedersela rifiutata come data futura.
     */
    public function test_creating_a_statement_accepts_todays_date_in_the_editorial_timezone_even_when_utc_is_still_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 23:30:00', 'UTC'));

        try {
            $editor = User::factory()->create(['role' => 'editor']);

            $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [
                'domanda' => 'Domanda',
                'consenso' => 'Consenso.',
                'incertezza' => 'Incertezza.',
                'last_checked_at' => '2026-09-15',
            ])->assertSessionDoesntHaveErrors(['last_checked_at']);

            $this->assertDatabaseCount('trust_knowledge_statements', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Cantiere 39: concept_id/content_cluster_id devono riferire un
     * Concept/Percorso attivo — stesso pattern già in uso in
     * StoreArticleRequest per secondary_categories.
     */
    public function test_creating_a_statement_rejects_an_inactive_concept(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $concept = Concept::create(['name' => 'Concetto archiviato', 'status' => Concept::STATUS_INACTIVE]);

        $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [
            'domanda' => 'Domanda',
            'consenso' => 'Consenso.',
            'incertezza' => 'Incertezza.',
            'concept_id' => $concept->id,
        ])->assertSessionHasErrors(['concept_id']);

        $this->assertDatabaseCount('trust_knowledge_statements', 0);
    }

    public function test_creating_a_statement_rejects_an_inactive_cluster(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $cluster = ContentCluster::create(['name' => 'Percorso archiviato', 'slug' => 'percorso-archiviato', 'is_active' => false]);

        $this->actingAs($editor)->post(route('admin.trust-knowledge.store'), [
            'domanda' => 'Domanda',
            'consenso' => 'Consenso.',
            'incertezza' => 'Incertezza.',
            'content_cluster_id' => $cluster->id,
        ])->assertSessionHasErrors(['content_cluster_id']);

        $this->assertDatabaseCount('trust_knowledge_statements', 0);
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
            '/cosa-sappiamo-davvero/'.$statement->id.'/anteprima',
        ];

        foreach ($publicGuesses as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    /**
     * Cantiere 40 (programma "100 cantieri Kairus"): anteprima di sola
     * lettura — ANCORA dentro auth+editor, mai una route pubblica (il
     * NO-GO B-45 resta in vigore, si legga il docblock del controller).
     */
    public function test_guest_cannot_view_the_preview(): void
    {
        $statement = $this->statement();

        $this->get(route('admin.trust-knowledge.preview', $statement))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_view_the_preview(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $statement = $this->statement();

        $this->actingAs($author)->get(route('admin.trust-knowledge.preview', $statement))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_preview_with_the_real_content(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $statement = $this->statement([
            'domanda' => 'Il caffè fa male al cuore?',
            'cosa_manca' => 'Non copre gli effetti su popolazioni pediatriche.',
        ]);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement))
            ->assertOk()
            ->assertSee('Anteprima amministrativa', false)
            ->assertSee('Il caffè fa male al cuore?')
            ->assertSee($statement->consenso)
            ->assertSee($statement->incertezza)
            ->assertSee('Non copre gli effetti su popolazioni pediatriche.')
            ->assertSee('Mai controllato');
    }

    public function test_the_preview_shows_the_last_checked_date_and_linked_concept(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $concept = Concept::create(['name' => 'Vaccini anteprima', 'status' => Concept::STATUS_ACTIVE]);
        $statement = $this->statement([
            'concept_id' => $concept->id,
            'last_checked_at' => '2026-09-01',
            'last_checked_by' => 'Redazione scienza',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement))
            ->assertOk()
            ->assertDontSee('Mai controllato')
            ->assertSee('01/09/2026')
            ->assertSee('Redazione scienza')
            ->assertSee('Vaccini anteprima');

        $response->assertSee('noindex,nofollow', false);
    }

    public function test_the_preview_omits_cosa_manca_when_not_declared(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $statement = $this->statement(['cosa_manca' => null]);

        $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement))
            ->assertOk()
            ->assertDontSee('Cosa manca / limiti di questa risposta');
    }

    public function test_the_preview_preserves_line_breaks_in_prose_fields(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $statement = $this->statement([
            'consenso' => "Prima riga.\nSeconda riga.",
            'incertezza' => "Punto uno.\nPunto due.",
            'cosa_manca' => "Limite uno.\nLimite due.",
        ]);

        $response = $this->actingAs($editor)->get(route('admin.trust-knowledge.preview', $statement))
            ->assertOk();

        $content = $response->getContent();
        $this->assertSame(3, substr_count($content, 'white-space:pre-line'));
        $response->assertSee("Prima riga.\nSeconda riga.", false);
        $response->assertSee("Punto uno.\nPunto due.", false);
        $response->assertSee("Limite uno.\nLimite due.", false);
    }
}
