<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 05 — Content Graph Admin × Article Editor Integration.
 *
 * Riusa ContentGraphService::linkArticle()/conceptsForArticle() (mai una
 * seconda implementazione della regola di dominio) tramite due endpoint
 * gemelli di Admin\ConceptController::linkArticle()/unlinkArticle(), qui
 * dal lato articolo invece che dal lato concetto. Integrazione volutamente
 * limitata alla superficie Admin (ruolo editor/admin, stessa middleware
 * 'editor' già usata da routes/content-graph-admin.php): la superficie
 * Redazione (ruolo author, meno fidato) resta invariata, nessun potere di
 * editing del Content Graph concesso lì.
 */
class ArticleConceptIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function author(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'author'])->save();

        return $user;
    }

    private function article(string $title = 'Articolo di test'): Article
    {
        return Article::create([
            'user_id' => $this->editor()->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subDay(),
        ]);
    }

    private function concept(string $name = 'Entropia', string $status = Concept::STATUS_ACTIVE): Concept
    {
        return Concept::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'status' => $status,
        ]);
    }

    public function test_article_edit_page_shows_linked_concepts(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept('Entropia');

        ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => ArticleConcept::RELATION_PRIMARY,
            'weight' => 90,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Entropia');
        $response->assertSee('Primario');
    }

    public function test_zero_concepts_linked_shows_empty_state_not_an_error(): void
    {
        $editor = $this->editor();
        $article = $this->article();

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Nessun concetto collegato a questo articolo.');
    }

    public function test_editor_can_link_a_concept_to_an_article(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        $response = $this->actingAs($editor)->post(
            route('admin.articles.concepts.link', [$article, $concept]),
            ['relation_type' => 'primary', 'weight' => 80]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => 'primary',
            'weight' => 80,
        ]);
    }

    public function test_linking_defaults_to_supporting_with_weight_fifty_when_omitted(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        $this->actingAs($editor)->post(route('admin.articles.concepts.link', [$article, $concept]));

        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => 'supporting',
            'weight' => 50,
        ]);
    }

    public function test_relinking_the_same_concept_updates_instead_of_duplicating(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        $this->actingAs($editor)->post(
            route('admin.articles.concepts.link', [$article, $concept]),
            ['relation_type' => 'supporting', 'weight' => 40]
        );
        $this->actingAs($editor)->post(
            route('admin.articles.concepts.link', [$article, $concept]),
            ['relation_type' => 'primary', 'weight' => 95]
        );

        $this->assertSame(1, ArticleConcept::where('article_id', $article->id)->where('concept_id', $concept->id)->count());
        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => 'primary',
            'weight' => 95,
        ]);
    }

    public function test_invalid_relation_type_is_rejected(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        $response = $this->actingAs($editor)->post(
            route('admin.articles.concepts.link', [$article, $concept]),
            ['relation_type' => 'not-a-real-relation']
        );

        $response->assertSessionHasErrors('relation_type');
        $this->assertDatabaseMissing('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);
    }

    public function test_weight_out_of_range_is_rejected(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        $response = $this->actingAs($editor)->post(
            route('admin.articles.concepts.link', [$article, $concept]),
            ['weight' => 256]
        );

        $response->assertSessionHasErrors('weight');
    }

    public function test_linking_a_nonexistent_concept_id_returns_not_found(): void
    {
        $editor = $this->editor();
        $article = $this->article();

        $response = $this->actingAs($editor)->post(
            route('admin.articles.concepts.link', [$article, 999999])
        );

        $response->assertNotFound();
    }

    public function test_editor_can_unlink_a_concept(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => ArticleConcept::RELATION_SUPPORTING,
            'weight' => 50,
        ]);

        $response = $this->actingAs($editor)->delete(route('admin.articles.concepts.unlink', [$article, $concept]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);
    }

    public function test_unlinking_a_link_that_does_not_exist_is_a_harmless_no_op(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        $response = $this->actingAs($editor)->delete(route('admin.articles.concepts.unlink', [$article, $concept]));

        $response->assertRedirect();
    }

    public function test_guest_cannot_link_a_concept(): void
    {
        $article = $this->article();
        $concept = $this->concept();

        $response = $this->post(route('admin.articles.concepts.link', [$article, $concept]));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);
    }

    public function test_author_role_cannot_reach_the_admin_concept_endpoints(): void
    {
        $author = $this->author();
        $article = $this->article();
        $concept = $this->concept();

        $response = $this->actingAs($author)->post(route('admin.articles.concepts.link', [$article, $concept]));

        $response->assertRedirect(route('redazione.dashboard'));
        $this->assertDatabaseMissing('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);
    }

    public function test_redazione_article_form_does_not_expose_any_concept_editing(): void
    {
        $author = $this->author();
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Bozza autore',
            'slug' => 'bozza-autore-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => 'review',
            'read_minutes' => 2,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($author)->get(route('redazione.articles.edit', $article));

        $response->assertOk();
        $response->assertDontSee('Concetti collegati');
        $response->assertDontSee('Content Graph');
    }

    public function test_updating_the_article_does_not_alter_its_concept_links(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $concept = $this->concept();

        ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => ArticleConcept::RELATION_PRIMARY,
            'weight' => 77,
        ]);

        $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => 'Titolo aggiornato',
            'body' => '<p>Corpo aggiornato.</p>',
            'excerpt' => 'Estratto aggiornato.',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
        ]);

        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => 'primary',
            'weight' => 77,
        ]);
    }

    public function test_available_concepts_list_excludes_already_linked_concepts(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $linked = $this->concept('Gia collegato');
        $unlinked = $this->concept('Non ancora collegato');

        ArticleConcept::create([
            'article_id' => $article->id,
            'concept_id' => $linked->id,
            'relation_type' => ArticleConcept::RELATION_SUPPORTING,
            'weight' => 50,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Non ancora collegato');
        // "Gia collegato" appare comunque una volta nel blocco "Concetti
        // collegati" in alto: qui verifichiamo solo che non compaia una
        // seconda volta nel catalogo "Collega un nuovo concetto".
        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'Gia collegato'));
    }
}
