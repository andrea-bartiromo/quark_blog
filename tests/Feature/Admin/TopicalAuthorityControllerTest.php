<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 8 (programma "Kairus Organic Discovery"): pagina di sola
 * lettura, stessa autorizzazione (auth + editor) di tutte le altre pagine
 * del programma.
 */
class TopicalAuthorityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_page(): void
    {
        $this->get(route('admin.topical-authority'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_view_the_page(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('admin.topical-authority'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_page_with_zero_state_and_no_errors(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get(route('admin.topical-authority'))
            ->assertOk()
            ->assertSee('Autorevolezza tematica')
            ->assertSee('Nessun Percorso pubblico da valutare.')
            ->assertSee('Nessun Concetto attivo da valutare.');
    }

    public function test_editor_sees_a_real_cluster_row(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pagina autorevolezza',
            'slug' => 'articolo-pagina-autorevolezza',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $cluster = ContentCluster::create([
            'name' => 'Percorso pagina autorevolezza',
            'slug' => 'percorso-pagina-autorevolezza',
            'is_active' => true,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10]);

        $this->actingAs($editor)->get(route('admin.topical-authority'))
            ->assertOk()
            ->assertSee('Percorso pagina autorevolezza')
            ->assertSee('Domanda non osservata');
    }

    public function test_a_draft_article_never_appears_in_the_page(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $author = User::factory()->create(['role' => 'author']);
        $draft = Article::create([
            'user_id' => $author->id,
            'title' => 'Bozza mai esposta autorevolezza',
            'slug' => 'bozza-mai-esposta-autorevolezza',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
        ]);
        $cluster = ContentCluster::create([
            'name' => 'Percorso con sola bozza',
            'slug' => 'percorso-con-sola-bozza',
            'is_active' => true,
        ]);
        $cluster->articles()->attach($draft->id, ['position' => 10]);

        $this->actingAs($editor)->get(route('admin.topical-authority'))
            ->assertOk()
            ->assertDontSee('Bozza mai esposta autorevolezza')
            ->assertSee('Nessun contenuto pubblico');
    }
}
