<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSecondaryCategoryDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, string $slug): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function article(User $author, string $title, string $primary): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'category' => $primary,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);
    }

    public function test_public_category_page_includes_articles_with_secondary_category(): void
    {
        $author = User::factory()->create();
        $this->category('Tecnologia & Società', 'societa');
        $physics = $this->category('Fisica', 'fisica');

        $article = $this->article($author, 'GPS e relatività', 'societa');
        $article->secondaryCategories()->attach($physics->id);

        $response = $this->get(route('categoria', 'fisica'));

        $response->assertOk();
        $response->assertSee('GPS e relatività');
        $this->assertSame([$article->id], $response->viewData('articles')->pluck('id')->all());
    }

    public function test_public_category_page_does_not_duplicate_an_article_matching_both_paths(): void
    {
        $author = User::factory()->create();
        $physics = $this->category('Fisica', 'fisica');

        $article = $this->article($author, 'Fisica fondamentale', 'fisica');
        $article->secondaryCategories()->attach($physics->id);

        $response = $this->get(route('categoria', 'fisica'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('articles')->count());
        $this->assertSame($article->id, $response->viewData('articles')->first()->id);
    }

    public function test_admin_category_filter_includes_secondary_category_articles_and_offers_the_category(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $this->category('Tecnologia & Società', 'societa');
        $physics = $this->category('Fisica', 'fisica');

        $article = $this->article($editor, 'GPS nel filtro admin', 'societa');
        $article->secondaryCategories()->attach($physics->id);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['category' => 'fisica']));

        $response->assertOk();
        $response->assertSee('GPS nel filtro admin');
        $this->assertSame([$article->id], $response->viewData('articles')->pluck('id')->all());
        $this->assertSame('Fisica', $response->viewData('categoryOptions')['fisica'] ?? null);
    }

    public function test_admin_category_filter_keeps_existing_primary_category_behavior(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $this->category('Spazio', 'spazio');
        $this->category('Fisica', 'fisica');

        $space = $this->article($editor, 'Solo spazio', 'spazio');
        $this->article($editor, 'Solo fisica', 'fisica');

        $response = $this->actingAs($editor)->get(route('admin.articles', ['category' => 'spazio']));

        $response->assertOk();
        $this->assertSame([$space->id], $response->viewData('articles')->pluck('id')->all());
    }
}
