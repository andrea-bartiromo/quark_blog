<?php

namespace Tests\Feature\Redazione;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRevisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(User $author, array $overrides = []): Article
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

    public function test_a_real_save_that_changes_content_creates_a_revision(): void
    {
        $author = $this->author();
        $article = $this->article($author);

        $this->actingAs($author)->put(route('redazione.articles.update', $article), [
            'title' => 'Titolo aggiornato',
            'excerpt' => 'Sommario aggiornato',
            'body' => '<p>Corpo aggiornato.</p>',
            'category' => 'energia',
        ]);

        $this->assertDatabaseCount('article_revisions', 1);
        $this->assertDatabaseHas('article_revisions', [
            'article_id' => $article->id,
            'title' => 'Bozza esistente',
        ]);
    }

    public function test_a_collaborator_cannot_view_or_restore_revisions_of_another_users_article(): void
    {
        $owner = $this->author();
        $intruder = $this->author();
        $article = $this->article($owner);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $owner->id,
            'title' => 'X', 'excerpt' => 'X', 'body' => 'X',
            'category' => 'energia', 'status' => 'draft', 'created_at' => now(),
        ]);

        $this->actingAs($intruder)->get(route('redazione.articles.revisions.index', $article))->assertForbidden();
        $this->actingAs($intruder)->get(route('redazione.articles.revisions.show', [$article, $revision]))->assertForbidden();
        $this->actingAs($intruder)->post(route('redazione.articles.revisions.restore', [$article, $revision]))->assertForbidden();
    }

    public function test_restore_applies_the_revision_for_the_articles_own_author(): void
    {
        $author = $this->author();
        $article = $this->article($author, ['title' => 'Titolo attuale']);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $author->id,
            'title' => 'Titolo da ripristinare', 'excerpt' => $article->excerpt, 'body' => $article->body,
            'category' => $article->category, 'status' => $article->status, 'created_at' => now(),
        ]);

        $response = $this->actingAs($author)->post(route('redazione.articles.revisions.restore', [$article, $revision]));

        $response->assertRedirect(route('redazione.articles.edit', $article));
        $this->assertSame('Titolo da ripristinare', $article->refresh()->title);
    }

    public function test_a_published_articles_revision_cannot_be_restored_matching_the_edit_restriction(): void
    {
        // Stesso limite già applicato da ArticleController::edit()/update()
        // ("Gli articoli pubblicati non possono essere modificati") — il
        // ripristino non deve aprire una scorciatoia verso un privilegio
        // che l'editing normale non concede a Redazione.
        $author = $this->author();
        $article = $this->article($author, ['status' => 'published', 'published_at' => now()]);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $author->id,
            'title' => 'Titolo storico', 'excerpt' => $article->excerpt, 'body' => $article->body,
            'category' => $article->category, 'status' => 'draft', 'created_at' => now(),
        ]);

        $response = $this->actingAs($author)->post(route('redazione.articles.revisions.restore', [$article, $revision]));

        $response->assertRedirect(route('redazione.articles'));
        $this->assertSame('Bozza esistente', $article->refresh()->title);
    }

    public function test_collaborator_cannot_restore_a_revision_with_an_editor_only_status(): void
    {
        $author = $this->author();
        $article = $this->article($author, ['title' => 'Titolo attuale']);

        foreach ([Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED] as $status) {
            $revision = ArticleRevision::create([
                'article_id' => $article->id,
                'user_id' => $author->id,
                'title' => 'Titolo privilegiato '.$status,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'category' => $article->category,
                'status' => $status,
                'published_at' => now()->addDay(),
                'created_at' => now(),
            ]);

            $response = $this->actingAs($author)->post(
                route('redazione.articles.revisions.restore', [$article, $revision])
            );

            $response->assertRedirect(route('redazione.articles.edit', $article));
            $this->assertSame('Titolo attuale', $article->refresh()->title);
            $this->assertSame(Article::STATUS_DRAFT, $article->status);
        }
    }

    public function test_the_show_view_hides_the_restore_button_for_a_published_article(): void
    {
        $author = $this->author();
        $article = $this->article($author, ['status' => 'published', 'published_at' => now()]);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $author->id,
            'title' => 'X', 'excerpt' => 'X', 'body' => 'X',
            'category' => 'energia', 'status' => 'draft', 'created_at' => now(),
        ]);

        $response = $this->actingAs($author)->get(route('redazione.articles.revisions.show', [$article, $revision]));

        $response->assertOk();
        $response->assertDontSee('Ripristina questa versione');
    }

    public function test_the_show_view_hides_the_restore_button_for_an_editor_only_revision_status(): void
    {
        $author = $this->author();
        $article = $this->article($author);
        $revision = ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => $author->id,
            'title' => 'Versione pubblicata',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($author)->get(
            route('redazione.articles.revisions.show', [$article, $revision])
        );

        $response->assertOk();
        $response->assertDontSee('Ripristina questa versione');
    }
}
