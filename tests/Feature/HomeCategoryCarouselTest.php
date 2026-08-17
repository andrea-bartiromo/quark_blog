<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeCategoryCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_without_published_articles_are_not_rendered(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory('solo-bozze', 1);

        $this->createArticle($user, $category, 'draft');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Solo Bozze')
            ->assertDontSee(route('categoria', $category), false);
    }

    #[DataProvider('categoryCounts')]
    public function test_all_published_categories_are_rendered_in_editorial_order(int $count): void
    {
        $user = User::factory()->create();
        $labels = [];

        for ($index = 1; $index <= $count; $index++) {
            $category = $this->createCategory('categoria-'.$index, $index);
            $this->createArticle($user, $category, 'published');
            $labels[] = $category->name;
        }

        $draftOnly = $this->createCategory('categoria-senza-pubblicati', $count + 1);
        $this->createArticle($user, $draftOnly, 'draft');

        $response = $this->get('/')->assertOk();

        $response->assertSeeInOrder($labels);
        $response->assertDontSee($draftOnly->name);

        foreach (Category::ordered()->where('slug', '!=', $draftOnly->slug)->get() as $category) {
            $response->assertSee(route('categoria', $category), false);
        }
    }

    public static function categoryCounts(): array
    {
        return [
            'one category' => [1],
            'six categories' => [6],
            'seven categories' => [7],
            'ten categories' => [10],
        ];
    }

    private function createCategory(string $slug, int $sortOrder): Category
    {
        return Category::create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'description' => null,
            'image' => null,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    private function createArticle(User $user, Category $category, string $status): Article
    {
        return Article::create([
            'user_id' => $user->id,
            'title' => 'Articolo '.$category->name,
            'slug' => 'articolo-'.$category->slug,
            'excerpt' => 'Excerpt',
            'body' => 'Body',
            'category' => $category->slug,
            'status' => $status,
            'featured' => false,
            'read_minutes' => 5,
            'views' => 0,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }
}
