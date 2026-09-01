<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\ArticleRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ArticleSaveAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_update_rolls_back_the_complete_article_and_relations_when_revision_creation_fails(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->article($editor, Article::STATUS_PUBLISHED);
        $secondary = Category::query()->create([
            'name' => 'Secondaria',
            'slug' => 'secondaria',
            'is_active' => true,
        ]);
        $article->belongsToMany(Category::class, 'article_category')->attach($secondary);

        $before = $article->fresh()->getRawOriginal();
        $beforeCategories = $article->belongsToMany(Category::class, 'article_category')->pluck('categories.id')->all();

        $this->mock(ArticleRevisionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('recordIfChanged')->once()->andThrow(new RuntimeException('sensitive database detail'));
        });

        $response = $this->actingAs($editor)
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), [
                'title' => 'Titolo che non deve restare',
                'excerpt' => 'Sommario che non deve restare',
                'body' => '<p>Corpo che non deve restare.</p>',
                'category' => 'energia',
                'secondary_categories' => [],
                'status' => Article::STATUS_PUBLISHED,
            ]);

        $response->assertRedirect(route('admin.articles.edit', $article));
        $response->assertSessionHasErrors('save');
        $this->assertSame($before, $article->fresh()->getRawOriginal());
        $this->assertSame(
            $beforeCategories,
            $article->belongsToMany(Category::class, 'article_category')->pluck('categories.id')->all(),
        );
        $this->assertDatabaseCount('article_revisions', 0);
    }

    public function test_redazione_update_rolls_back_the_complete_article_when_revision_creation_fails(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->article($author, Article::STATUS_DRAFT);
        $before = $article->fresh()->getRawOriginal();

        $this->mock(ArticleRevisionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('recordIfChanged')->once()->andThrow(new RuntimeException('sensitive database detail'));
        });

        $response = $this->actingAs($author)
            ->from(route('redazione.articles.edit', $article))
            ->put(route('redazione.articles.update', $article), [
                'title' => 'Titolo che non deve restare',
                'excerpt' => 'Sommario che non deve restare',
                'body' => '<p>Corpo che non deve restare.</p>',
                'category' => 'energia',
            ]);

        $response->assertRedirect(route('redazione.articles.edit', $article));
        $response->assertSessionHasErrors('save');
        $this->assertSame($before, $article->fresh()->getRawOriginal());
        $this->assertDatabaseCount('article_revisions', 0);
    }

    private function article(User $author, string $status): Article
    {
        return Article::query()->create([
            'user_id' => $author->id,
            'title' => 'Stato originale',
            'slug' => 'stato-originale-'.uniqid(),
            'excerpt' => 'Sommario originale',
            'body' => '<p>Corpo originale.</p>',
            'category' => 'energia',
            'status' => $status,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subDay() : null,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }
}
