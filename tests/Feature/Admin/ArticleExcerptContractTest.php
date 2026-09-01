<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArticleExcerptContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_and_revision_excerpt_columns_use_text_storage(): void
    {
        $this->assertSame('text', Schema::getColumnType('articles', 'excerpt'));
        $this->assertSame('text', Schema::getColumnType('article_revisions', 'excerpt'));
    }

    public function test_the_300_character_limit_is_unicode_safe_and_rejects_only_character_301(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->article($editor);
        $validExcerpt = str_repeat('🚀', 300);

        $valid = $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => 'Titolo valido',
            'excerpt' => $validExcerpt,
            'body' => '<p>Corpo valido.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
        ]);

        $valid->assertSessionDoesntHaveErrors('excerpt');
        $this->assertSame($validExcerpt, $article->fresh()->excerpt);

        $invalid = $this->actingAs($editor)
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), [
                'title' => 'Titolo non valido',
                'excerpt' => $validExcerpt.'è',
                'body' => '<p>Corpo valido.</p>',
                'category' => 'energia',
                'status' => Article::STATUS_DRAFT,
            ]);

        $invalid->assertRedirect(route('admin.articles.edit', $article));
        $invalid->assertSessionHasErrors('excerpt');
        $this->assertSame($validExcerpt, $article->fresh()->excerpt);
    }

    public function test_both_forms_render_the_unicode_counter_contract(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($editor)
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('data-excerpt-limit="300"', false)
            ->assertSee('data-excerpt-counter', false);

        $this->actingAs($author)
            ->get(route('redazione.articles.create'))
            ->assertOk()
            ->assertSee('data-excerpt-limit="300"', false)
            ->assertSee('data-excerpt-counter', false);
    }

    public function test_rollback_preserves_valid_excerpts_longer_than_255_characters(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->article($editor);
        $excerpt = str_repeat('è', 300);

        $revision = ArticleRevision::query()->create([
            'article_id' => $article->id,
            'user_id' => $editor->id,
            'title' => $article->title,
            'excerpt' => $excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'created_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_01_100000_align_article_revision_excerpt_contract.php');
        $migration->down();

        $this->assertSame('text', Schema::getColumnType('article_revisions', 'excerpt'));
        $this->assertSame($excerpt, $revision->fresh()->excerpt);
    }

    private function article(User $author): Article
    {
        return Article::query()->create([
            'user_id' => $author->id,
            'title' => 'Articolo esistente',
            'slug' => 'articolo-esistente-'.uniqid(),
            'excerpt' => 'Sommario originale',
            'body' => '<p>Corpo originale.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }
}
