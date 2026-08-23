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

    public function test_percorso_requires_a_non_empty_continuous_public_prefix(): void
    {
        $published = $this->article('pubblico-test', 'fisica-test', Article::STATUS_PUBLISHED);
        $scheduled = $this->article('programmato-test', 'fisica-test', Article::STATUS_SCHEDULED, now()->addDay());

        $safe = ContentCluster::create(['name' => 'Percorso Sicuro Test', 'slug' => 'percorso-sicuro-test', 'is_active' => true]);
        $blocked = ContentCluster::create(['name' => 'Percorso Bloccato Test', 'slug' => 'percorso-bloccato-test', 'is_active' => true]);
        $inactive = ContentCluster::create(['name' => 'Percorso Inattivo Test', 'slug' => 'percorso-inattivo-test', 'is_active' => false]);

        $safe->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);
        $blocked->articles()->attach($scheduled->id, ['position' => 10, 'is_primary' => true]);
        $blocked->articles()->attach($published->id, ['position' => 20, 'is_primary' => false]);
        $inactive->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);

        $results = app(TrovaEntitySearchService::class)->search('Percorso Test');

        $this->assertTrue($results['percorsi']->contains('id', $safe->id));
        $this->assertFalse($results['percorsi']->contains('id', $blocked->id));
        $this->assertFalse($results['percorsi']->contains('id', $inactive->id));
    }

    public function test_percorso_becomes_eligible_when_first_gap_opens(): void
    {
        $first = $this->article('prima-tappa-test', 'fisica-test', Article::STATUS_SCHEDULED, now()->addDay());
        $second = $this->article('seconda-tappa-test', 'fisica-test', Article::STATUS_PUBLISHED);
        $cluster = ContentCluster::create(['name' => 'Percorso Apertura Test', 'slug' => 'percorso-apertura-test', 'is_active' => true]);
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($second->id, ['position' => 20, 'is_primary' => false]);

        $this->assertFalse(app(TrovaEntitySearchService::class)->search('Percorso Apertura Test')['percorsi']->contains('id', $cluster->id));

        $first->update(['status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subMinute()]);

        $this->assertTrue(app(TrovaEntitySearchService::class)->search('Percorso Apertura Test')['percorsi']->contains('id', $cluster->id));
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

    public function test_query_count_does_not_grow_linearly_with_number_of_percorsi(): void
    {
        $one = $this->measureQueryCountForPercorsi(1);
        $eight = $this->measureQueryCountForPercorsi(8);

        $this->assertSame($one, $eight);
    }

    private function measureQueryCountForPercorsi(int $count): int
    {
        ContentCluster::query()->delete();
        $article = $this->article('query-article-'.$count, 'query-test', Article::STATUS_PUBLISHED);

        for ($i = 1; $i <= $count; $i++) {
            $cluster = ContentCluster::create(['name' => 'Query Percorso '.$i, 'slug' => 'query-percorso-'.$count.'-'.$i, 'is_active' => true]);
            $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TrovaEntitySearchService::class)->search('Query Percorso');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }

    private function article(string $slug, string $category, string $status, $publishedAt = null): Article
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
            'published_at' => $publishedAt ?? ($status === Article::STATUS_PUBLISHED ? now()->subMinute() : null),
        ]));
    }
}
