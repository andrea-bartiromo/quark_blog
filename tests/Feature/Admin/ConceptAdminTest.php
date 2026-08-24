<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Concept;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Content Graph Admin V1 (docs/CONTENT_GRAPH_V1.md, PR #302): CRUD minimo
 * per Concept/Alias/ArticleConcept, mai una seconda source of truth per le
 * regole di pubblicazione (ContentGraphService resta l'unico contratto).
 */
class ConceptAdminTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function article(string $title): Article
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

    public function test_guest_cannot_access_concept_admin(): void
    {
        $this->get(route('admin.concepts.index'))->assertRedirect(route('login'));
    }

    public function test_editor_can_create_a_concept(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.concepts.store'), [
            'name' => 'Entropia',
            'slug' => '',
            'short_definition' => 'Misura del disordine di un sistema.',
            'status' => 'draft',
        ]);

        $concept = Concept::where('slug', 'entropia')->firstOrFail();
        $response->assertRedirect(route('admin.concepts.edit', $concept));
        $this->assertSame('draft', $concept->status);
    }

    public function test_slug_must_be_unique(): void
    {
        $editor = $this->editor();
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia']);

        $response = $this->actingAs($editor)->post(route('admin.concepts.store'), [
            'name' => 'Entropia',
            'slug' => '',
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_editor_can_update_concept_metadata_and_sync_aliases(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'draft']);
        $concept->aliases()->create(['alias' => 'disordine termodinamico']);

        $response = $this->actingAs($editor)->put(route('admin.concepts.update', $concept), [
            'name' => 'Entropia',
            'slug' => 'entropia',
            'status' => 'active',
            'aliases' => ['Entropia informazionale', 'entropia informazionale', '  ', 'Shannon entropy'],
        ]);

        $response->assertRedirect(route('admin.concepts.edit', $concept));
        $concept->refresh();
        $this->assertSame('active', $concept->status);

        // "disordine termodinamico" non era nella nuova lista: rimosso.
        // "Entropia informazionale" duplicata (case-insensitive) nella
        // stessa submission: una sola riga persistita, non un 500 per
        // violazione del vincolo unique.
        $aliases = $concept->aliases()->pluck('alias')->all();
        $this->assertCount(2, $aliases);
        $this->assertContains('Entropia informazionale', $aliases);
        $this->assertContains('Shannon entropy', $aliases);
        $this->assertNotContains('disordine termodinamico', $aliases);
    }

    public function test_editor_can_link_and_unlink_an_article(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article = $this->article('Termodinamica base');

        $link = $this->actingAs($editor)->post(route('admin.concepts.articles.link', [$concept, $article]), [
            'relation_type' => 'primary',
            'weight' => 80,
        ]);
        $link->assertRedirect();
        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => 'primary',
            'weight' => 80,
        ]);

        $unlink = $this->actingAs($editor)->delete(route('admin.concepts.articles.unlink', [$concept, $article]));
        $unlink->assertRedirect();
        $this->assertDatabaseMissing('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
        ]);
    }

    public function test_linking_the_same_article_twice_updates_rather_than_duplicates(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article = $this->article('Termodinamica base');

        $this->actingAs($editor)->post(route('admin.concepts.articles.link', [$concept, $article]), ['relation_type' => 'supporting', 'weight' => 40]);
        $this->actingAs($editor)->post(route('admin.concepts.articles.link', [$concept, $article]), ['relation_type' => 'primary', 'weight' => 90]);

        $this->assertDatabaseCount('article_concepts', 1);
        $this->assertDatabaseHas('article_concepts', [
            'article_id' => $article->id,
            'concept_id' => $concept->id,
            'relation_type' => 'primary',
            'weight' => 90,
        ]);
    }

    public function test_index_shows_no_duplicate_panel_when_no_concepts_collide(): void
    {
        $editor = $this->editor();
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);

        $response = $this->actingAs($editor)->get(route('admin.concepts.index'));

        $response->assertOk();
        $response->assertDontSee('Possibili concetti duplicati', false);
    }

    public function test_index_flags_two_concepts_sharing_the_same_normalized_name(): void
    {
        $editor = $this->editor();
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia-1', 'status' => 'active']);
        Concept::create(['name' => 'entropia', 'slug' => 'entropia-2', 'status' => 'draft']);

        $response = $this->actingAs($editor)->get(route('admin.concepts.index'));

        $response->assertOk();
        $response->assertSee('Possibili concetti duplicati (1)', false);
    }

    public function test_edit_form_excludes_already_linked_articles_from_the_catalog(): void
    {
        $editor = $this->editor();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $linked = $this->article('Già collegato');
        $unlinked = $this->article('Non collegato');
        $concept->articleLinks()->create(['article_id' => $linked->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $response = $this->actingAs($editor)->get(route('admin.concepts.edit', $concept));

        $response->assertOk();
        // "Già collegato" appare comunque nella tabella "Articoli
        // collegati" sopra — qui verifichiamo solo che il CATALOGO (la
        // seconda tabella, quella per collegare NUOVI articoli) non lo
        // riproponga.
        $mainHtml = $response->getContent();
        $this->assertSame(1, substr_count($mainHtml, 'Già collegato'));
        $this->assertStringContainsString('Non collegato', $mainHtml);
    }
}
