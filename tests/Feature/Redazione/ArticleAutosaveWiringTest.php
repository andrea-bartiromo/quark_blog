<?php

namespace Tests\Feature\Redazione;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL RESILIENCE — stessa copertura di
 * tests/Feature/Admin/ArticleAutosaveWiringTest.php, lato Redazione. Il
 * form Redazione condivide lo stesso script/banner partial dell'Admin ma
 * non ha status/scheduled/featured/secondary_categories: lo script li
 * salta con querySelector nullable (verificato qui solo lato markup, il
 * comportamento JS è coperto da tests/browser/editor-autosave.spec.js).
 */
class ArticleAutosaveWiringTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function draftArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Bozza esistente',
            'slug' => 'bozza-esistente-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_create_form_exposes_a_new_article_draft_identity_scoped_to_redazione(): void
    {
        $author = $this->author();

        $response = $this->actingAs($author)->get(route('redazione.articles.create'));

        $response->assertOk();
        $response->assertSee('data-editor-autosave-form', false);
        $response->assertSee('data-editor-surface="redazione"', false);
        $response->assertSee('data-editor-context="new"', false);
        $response->assertSee('meta name="kairus-user-id" content="'.$author->id.'"', false);
    }

    public function test_edit_form_exposes_the_article_id(): void
    {
        $author = $this->author();
        $article = $this->draftArticle($author);

        $response = $this->actingAs($author)->get(route('redazione.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('data-editor-context="'.$article->id.'"', false);
    }

    public function test_a_successful_update_flashes_the_article_id_for_client_side_draft_cleanup(): void
    {
        $author = $this->author();
        $article = $this->draftArticle($author);

        $response = $this->actingAs($author)->put(route('redazione.articles.update', $article), [
            'title' => 'Titolo aggiornato',
            'excerpt' => 'Sommario aggiornato',
            'body' => '<p>Corpo aggiornato.</p>',
            'category' => 'energia',
        ]);

        $response->assertRedirect(route('redazione.articles'));
        $response->assertSessionHas('kairus_draft_cleanup_context', (string) $article->id);

        $followUp = $this->get(route('redazione.articles'));
        $followUp->assertSee('id="kairus-draft-cleanup-marker"', false);
        $followUp->assertSee('data-editor-surface="redazione"', false);
        $followUp->assertSee('data-draft-cleanup-context="'.$article->id.'"', false);
    }

    public function test_an_unrelated_successful_redazione_action_never_triggers_the_draft_cleanup_script(): void
    {
        // FASE 1 audit finding: stesso motivo del test gemello in
        // tests/Feature/Admin/ArticleAutosaveWiringTest.php — qui il caso
        // reale e' un collaboratore che aggiorna il proprio profilo
        // (ProfileController flasha anch'esso un 'success' generico)
        // mentre ha una bozza di un nuovo articolo ancora in corso altrove.
        $author = $this->author();

        $response = $this->actingAs($author)
            ->withSession(['success' => 'Profilo aggiornato.'])
            ->get(route('redazione.articles'));

        $response->assertOk();
        $response->assertSee('id="kairus-flash-success"', false);
        $response->assertDontSee('id="kairus-draft-cleanup-marker"', false);
    }

    public function test_the_redazione_form_uses_the_same_shared_autosave_partials_as_admin(): void
    {
        $author = $this->author();

        $response = $this->actingAs($author)->get(route('redazione.articles.create'));

        $response->assertOk();
        $response->assertSee('data-editor-autosave-banner', false);
        $response->assertSee('data-editor-autosave-status', false);
    }
}
