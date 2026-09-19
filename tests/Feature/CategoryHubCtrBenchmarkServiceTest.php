<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\CategoryHubImpression;
use App\Models\User;
use App\Services\CategoryHubCtrBenchmarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cantiere 53 (programma "100 cantieri Kairus", dipende dai Cantieri 49-50).
 *
 * Ispezione diretta prima di questo cantiere: ArticleController::category()
 * non registra nessuna impression — a differenza di
 * ArticleController::show(), che già scrive `referer` per ogni view
 * articolo (ArticleViewTrackingService::recordView()). Il lato "click" del
 * CTR è quindi dedotto da article_views già esistente, non da un nuovo
 * tracciamento dedicato — questi test verificano solo l'aggregazione
 * (impression proprie + referer già raccolto altrove), mai una regola di
 * dominio duplicata.
 */
class CategoryHubCtrBenchmarkServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CategoryHubCtrBenchmarkService
    {
        return app(CategoryHubCtrBenchmarkService::class);
    }

    private function category(string $namePrefix, array $overrides = []): Category
    {
        $slug = Str::slug($namePrefix).'-'.uniqid();

        return Category::create(array_merge([
            'name' => $namePrefix.' '.uniqid(),
            'slug' => $slug,
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ], $overrides));
    }

    private function article(string $categorySlug): Article
    {
        return Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $categorySlug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
    }

    private function articleView(Article $article, ?string $referer): ArticleView
    {
        return ArticleView::create([
            'article_id' => $article->id,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'referer' => $referer,
            'viewed_at' => now(),
        ]);
    }

    public function test_impression_is_recorded_for_a_real_public_category_page_request(): void
    {
        $category = $this->category('Salute Test');

        $this->get(route('categoria', $category->slug))->assertOk();

        $this->assertSame(1, CategoryHubImpression::where('category_slug', $category->slug)->count());
    }

    public function test_impression_is_deduplicated_within_the_same_session(): void
    {
        $category = $this->category('Salute Test');

        $this->get(route('categoria', $category->slug))->assertOk();
        $this->get(route('categoria', $category->slug))->assertOk();

        $this->assertSame(1, CategoryHubImpression::where('category_slug', $category->slug)->count());
    }

    public function test_impression_is_not_recorded_for_a_404_category(): void
    {
        $this->get(route('categoria', 'categoria-inesistente-'.uniqid()))->assertNotFound();

        $this->assertSame(0, CategoryHubImpression::count());
    }

    public function test_impression_is_not_recorded_for_a_logged_in_editor(): void
    {
        $category = $this->category('Salute Test');
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor'])->save();

        $this->actingAs($editor)->get(route('categoria', $category->slug))->assertOk();

        $this->assertSame(0, CategoryHubImpression::count());
    }

    public function test_benchmark_for_computes_ctr_from_impressions_and_existing_article_view_referers(): void
    {
        $category = $this->category('Salute Test');
        $article = $this->article($category->slug);

        CategoryHubImpression::create(['category_slug' => $category->slug]);
        CategoryHubImpression::create(['category_slug' => $category->slug]);
        CategoryHubImpression::create(['category_slug' => $category->slug]);
        CategoryHubImpression::create(['category_slug' => $category->slug]);

        $this->articleView($article, 'https://kairus.test/categoria/'.$category->slug);
        $this->articleView($article, 'https://kairus.test/categoria/'.$category->slug.'?page=2');
        $this->articleView($article, 'https://kairus.test/'); // referer non da un hub categoria

        $benchmark = $this->service()->benchmarkFor($category->slug);

        $this->assertSame(4, $benchmark['impressions']);
        $this->assertSame(2, $benchmark['click_throughs']);
        $this->assertSame(0.5, $benchmark['ctr']);
    }

    public function test_click_through_matching_does_not_false_positive_on_a_slug_prefix_collision(): void
    {
        $short = $this->category('Energia', ['slug' => 'energia-'.uniqid()]);
        $long = $this->category('Energia Rinnovabile', ['slug' => $short->slug.'-rinnovabile']);
        $article = $this->article($long->slug);

        // Referer dell'hub della categoria "lunga": un match ingenuo per
        // sottostringa su "/categoria/{$short->slug}" la conterebbe per
        // errore anche come click-through della categoria corta.
        $this->articleView($article, 'https://kairus.test/categoria/'.$long->slug);

        $shortBenchmark = $this->service()->benchmarkFor($short->slug);
        $longBenchmark = $this->service()->benchmarkFor($long->slug);

        $this->assertSame(0, $shortBenchmark['click_throughs']);
        $this->assertSame(1, $longBenchmark['click_throughs']);
    }

    public function test_hub_breakdown_includes_every_publicly_visible_category_even_with_zero_activity(): void
    {
        $category = $this->category('Salute Test');

        $row = $this->service()->hubBreakdown()->firstWhere('slug', $category->slug);

        $this->assertNotNull($row);
        $this->assertSame(0, $row['impressions']);
        $this->assertSame(0, $row['click_throughs']);
        $this->assertSame(0.0, $row['ctr']);
    }

    public function test_hub_breakdown_excludes_a_non_publicly_visible_category(): void
    {
        $draft = $this->category('Bozza Test', ['status' => Category::STATUS_DRAFT]);

        $row = $this->service()->hubBreakdown()->firstWhere('slug', $draft->slug);

        $this->assertNull($row);
    }

    public function test_site_wide_totals_count_events_even_for_a_category_no_longer_publicly_visible(): void
    {
        $category = $this->category('Salute Test');
        $article = $this->article($category->slug);

        CategoryHubImpression::create(['category_slug' => $category->slug]);
        $this->articleView($article, 'https://kairus.test/categoria/'.$category->slug);

        // Disattivata DOPO aver registrato gli eventi: non deve sparire dai
        // totali sitewide, anche se ora esce da hubBreakdown().
        $category->forceFill(['is_active' => false])->save();

        $totals = $this->service()->siteWideTotals();

        $this->assertSame(1, $totals['impressions']);
        $this->assertSame(1, $totals['click_throughs']);
    }

    public function test_the_service_never_writes_to_article_views(): void
    {
        $category = $this->category('Salute Test');

        $countBefore = ArticleView::count();

        $this->service()->benchmarkFor($category->slug);
        $this->service()->hubBreakdown();
        $this->service()->siteWideTotals();

        $this->assertSame($countBefore, ArticleView::count());
    }
}
