<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRevisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo esistente',
            'slug' => 'articolo-esistente-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_a_real_save_that_changes_content_creates_a_revision(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => 'Titolo aggiornato',
            'excerpt' => 'Sommario aggiornato',
            'body' => '<p>Corpo aggiornato.</p>',
            'category' => 'energia',
            'status' => 'published',
        ]);

        $this->assertDatabaseCount('article_revisions', 1);
        $this->assertDatabaseHas('article_revisions', [
            'article_id' => $article->id,
            'title' => 'Articolo esistente',
        ]);
    }

    public function test_index_lists_revisions_for_the_article(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);
        ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $editor->id,
            'title' => 'Versione precedente', 'excerpt' => 'X', 'body' => 'X',
            'category' => 'energia', 'status' => 'published', 'created_at' => now(),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.revisions.index', $article));

        $response->assertOk();
        $response->assertSee('Versione precedente');
    }

    public function test_show_compares_revision_against_current_article(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $editor->id,
            'title' => 'Titolo storico', 'excerpt' => 'X', 'body' => 'X',
            'category' => 'energia', 'status' => 'published', 'created_at' => now(),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.revisions.show', [$article, $revision]));

        $response->assertOk();
        $response->assertSee('Titolo storico');
        $response->assertSee($article->title);
    }

    public function test_a_revision_belonging_to_a_different_article_returns_404(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);
        $otherArticle = $this->article($editor, ['slug' => 'altro-articolo-'.uniqid()]);
        $foreignRevision = ArticleRevision::create([
            'article_id' => $otherArticle->id, 'user_id' => $editor->id,
            'title' => 'Non correlata', 'excerpt' => 'X', 'body' => 'X',
            'category' => 'energia', 'status' => 'published', 'created_at' => now(),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.revisions.show', [$article, $foreignRevision]));

        $response->assertNotFound();
    }

    public function test_restore_applies_the_revision_and_redirects_to_the_edit_form(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, ['title' => 'Titolo attuale']);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $editor->id,
            'title' => 'Titolo da ripristinare', 'excerpt' => $article->excerpt, 'body' => $article->body,
            'category' => $article->category, 'status' => $article->status, 'created_at' => now(),
        ]);

        $response = $this->actingAs($editor)->post(route('admin.articles.revisions.restore', [$article, $revision]));

        $response->assertRedirect(route('admin.articles.edit', $article));
        $this->assertSame('Titolo da ripristinare', $article->refresh()->title);
    }

    public function test_a_logged_out_user_cannot_view_or_restore_revisions(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);
        $revision = ArticleRevision::create([
            'article_id' => $article->id, 'user_id' => $editor->id,
            'title' => 'X', 'excerpt' => 'X', 'body' => 'X',
            'category' => 'energia', 'status' => 'published', 'created_at' => now(),
        ]);

        $this->get(route('admin.articles.revisions.index', $article))->assertRedirect();
        $this->get(route('admin.articles.revisions.show', [$article, $revision]))->assertRedirect();
        $this->post(route('admin.articles.revisions.restore', [$article, $revision]))->assertRedirect();
    }
}
