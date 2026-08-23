<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\Search\TrovaEntitySearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrovaEntitySearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_categories_backed_by_published_articles(): void
    {
        $publishedCategory = Category::create(['name' => 'Astrofisica Test', 'slug' => 'astrofisica-test', 'description' => 'Spazio e stelle']);
        $draftCategory = Category::create(['name' => 'Biologia Test', 'slug' => 'biologia-test', 'description' => 'Vita e cellule']);

        $this->article('stelle-test', 'astrofisica-test', Article::STATUS_PUBLISHED);
        $this->article('cellule-test', 'biologia-test', Article::STATUS_DRAFT);

        $results = app(TrovaEntitySearchService::class)->search('Astrofisica Test');

        $this->assertSame([$publishedCategory->id], $results['categories']->pluck('id')->all());
        $this->assertFalse($results['categories']->contains('id', $draftCategory->id));
        $this->assertSame('EXACT', $results['categories']->first()['match_class']);
    }

    public function test_published_secondary_category_membership_also_makes_category_eligible(): void
    {
        $primary = Category::create(['name' => 'Primary Test', 'slug' => 'primary-test']);
        $secondary = Category::create(['name' => 'Secondaria Pubblica Test', 'slug' => 'secondaria-pubblica-test']);
        $article = $this->article('secondary-membership-test', $primary->slug, Article::STATUS_PUBLISHED);
        $article->secondaryCategories()->attach($secondary->id);

        $results = app(TrovaEntitySearchService::class)->search('Secondaria Pubblica Test');

        $this->assertSame([$secondary->id], $results['categories']->pluck('id')->all());
    }

    public function test_it_returns_only_active_percorsi_with_published_members(): void
    {
        $published = $this->article('relativita-test', 'fisica-test', Article::STATUS_PUBLISHED);
        $draft = $this->article('quantistica-test', 'fisica-test', Article::STATUS_DRAFT);

        $safe = ContentCluster::create(['name' => 'Fisica moderna test', 'slug' => 'fisica-moderna-test', 'is_active' => true]);
        $draftOnly = ContentCluster::create(['name' => 'Fisica teorica test', 'slug' => 'fisica-teorica-test', 'is_active' => true]);
        $inactive = ContentCluster::create(['name' => 'Fisica classica test', 'slug' => 'fisica-classica-test', 'is_active' => false]);

        $safe->articles()->attach($published->id, ['position' => 1, 'is_primary' => true]);
        $draftOnly->articles()->attach($draft->id, ['position' => 1, 'is_primary' => true]);
        $inactive->articles()->attach($published->id, ['position' => 1, 'is_primary' => true]);

        $results = app(TrovaEntitySearchService::class)->search('fisica test');

        $this->assertSame([$safe->id], $results['percorsi']->pluck('id')->all());
    }

    public function test_match_classes_are_deterministic_without_numeric_relevance_scores(): void
    {
        Category::create(['name' => 'Cosmo Test', 'slug' => 'cosmo-test', 'description' => 'Missioni e universo']);
        Category::create(['name' => 'Scienza Vita Test', 'slug' => 'scienza-vita-test', 'description' => 'Scienza nella vita quotidiana']);
        $this->article('cosmo-test-article', 'cosmo-test', Article::STATUS_PUBLISHED);
        $this->article('vita-test-article', 'scienza-vita-test', Article::STATUS_PUBLISHED);

        $exact = app(TrovaEntitySearchService::class)->search('Cosmo Test')['categories']->first();
        $partial = app(TrovaEntitySearchService::class)->search('vita scienza')['categories']->first();

        $this->assertSame('EXACT', $exact['match_class']);
        $this->assertSame('ALL_TOKENS', $partial['match_class']);
        $this->assertArrayNotHasKey('score', $exact);
    }

    public function test_entity_search_uses_a_bounded_two_query_catalog_read(): void
    {
        Category::create(['name' => 'Cosmo Query Test', 'slug' => 'cosmo-query-test']);
        $article = $this->article('cosmo-query-article', 'cosmo-query-test', Article::STATUS_PUBLISHED);
        $cluster = ContentCluster::create(['name' => 'Viaggio cosmo query', 'slug' => 'viaggio-cosmo-query-test', 'is_active' => true]);
        $cluster->articles()->attach($article->id, ['position' => 1, 'is_primary' => true]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(TrovaEntitySearchService::class)->search('cosmo query');

        $this->assertCount(2, DB::getQueryLog());
    }

    private function article(string $slug, string $category, string $status): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Test excerpt',
            'body' => '<p>Test body</p>',
            'category' => $category,
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subMinute() : null,
        ]));
    }
}
