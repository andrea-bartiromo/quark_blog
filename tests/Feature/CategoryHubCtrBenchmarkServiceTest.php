<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\CategoryHubEvent;
use App\Models\User;
use App\Services\CategoryHubCtrBenchmarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cantiere 53 (programma "100 cantieri Kairus", dipende dai Cantieri 49-50).
 *
 * Impression e click-through sono due eventi ESPLICITI e SIMMETRICI (stessa
 * granularità di deduplicazione: una volta per categoria per sessione).
 * Una prima versione deduceva il click-through dal referer già presente in
 * article_views — Codex (PR #638) ha segnalato che questo produceva un CTR
 * a cardinalità disallineata (poteva superare il 100%) e che l'audit
 * read-only RedirectAndCanonicalIntegrityAudit avrebbe gonfiato le
 * impression a ogni esecuzione. Questi test coprono la versione corretta:
 * eventi propri, stessa esclusione traffico interno/audit su entrambi i
 * lati, stessa deduplicazione di sessione.
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

    // ── Impression: registrazione ────────────────────────────────

    public function test_impression_is_recorded_for_a_real_public_category_page_request(): void
    {
        $category = $this->category('Salute Test');

        $this->get(route('categoria', $category->slug))->assertOk();

        $this->assertSame(1, CategoryHubEvent::where('category_slug', $category->slug)
            ->where('event_type', CategoryHubEvent::EVENT_IMPRESSION)->count());
    }

    public function test_impression_is_deduplicated_within_the_same_session(): void
    {
        $category = $this->category('Salute Test');

        $this->get(route('categoria', $category->slug))->assertOk();
        $this->get(route('categoria', $category->slug))->assertOk();

        $this->assertSame(1, CategoryHubEvent::where('category_slug', $category->slug)
            ->where('event_type', CategoryHubEvent::EVENT_IMPRESSION)->count());
    }

    public function test_impression_is_not_recorded_for_a_404_category(): void
    {
        $this->get(route('categoria', 'categoria-inesistente-'.uniqid()))->assertNotFound();

        $this->assertSame(0, CategoryHubEvent::count());
    }

    public function test_impression_is_not_recorded_for_a_logged_in_editor(): void
    {
        $category = $this->category('Salute Test');
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor'])->save();

        $this->actingAs($editor)->get(route('categoria', $category->slug))->assertOk();

        $this->assertSame(0, CategoryHubEvent::count());
    }

    public function test_impression_is_not_recorded_for_an_internal_audit_request(): void
    {
        // Finding Codex (P1, PR #638): RedirectAndCanonicalIntegrityAudit
        // visita /categoria/{slug} in-process con questo stesso header —
        // senza l'esclusione, ogni sua esecuzione (pensata per essere
        // ripetibile a piacere) gonfierebbe silenziosamente le impression.
        $category = $this->category('Salute Test');

        $this->withHeaders(['X-Kairus-Internal-Audit' => '1'])
            ->get(route('categoria', $category->slug))
            ->assertOk();

        $this->assertSame(0, CategoryHubEvent::count());
    }

    // ── Click-through: registrazione ─────────────────────────────

    public function test_click_through_is_recorded_when_arriving_at_an_article_from_a_category_hub(): void
    {
        $category = $this->category('Salute Test');
        $article = $this->article($category->slug);

        $this->withHeaders(['referer' => route('categoria', $category->slug)])
            ->get(route('articolo', $article->slug))
            ->assertOk();

        $this->assertSame(1, CategoryHubEvent::where('category_slug', $category->slug)
            ->where('event_type', CategoryHubEvent::EVENT_CLICK_THROUGH)->count());
    }

    public function test_click_through_is_not_recorded_when_referer_is_not_a_category_hub_page(): void
    {
        $category = $this->category('Salute Test');
        $article = $this->article($category->slug);

        $this->withHeaders(['referer' => route('home')])
            ->get(route('articolo', $article->slug))
            ->assertOk();

        $this->assertSame(0, CategoryHubEvent::where('event_type', CategoryHubEvent::EVENT_CLICK_THROUGH)->count());
    }

    public function test_click_through_is_deduplicated_within_the_same_session_even_across_different_articles(): void
    {
        // Regressione diretta del finding Codex P1 (PR #638): prima di
        // questo fix, due articoli diversi aperti dalla stessa visita alla
        // stessa categoria contavano DUE click-through contro UNA sola
        // impression (CTR anche oltre il 100%). Ora entrambi i lati usano
        // la stessa granularità di deduplicazione: una volta per categoria
        // per sessione.
        $category = $this->category('Salute Test');
        $first = $this->article($category->slug);
        $second = $this->article($category->slug);

        $this->get(route('categoria', $category->slug))->assertOk();

        $this->withHeaders(['referer' => route('categoria', $category->slug)])
            ->get(route('articolo', $first->slug))
            ->assertOk();
        $this->withHeaders(['referer' => route('categoria', $category->slug)])
            ->get(route('articolo', $second->slug))
            ->assertOk();

        $benchmark = $this->service()->benchmarkFor($category->slug);

        $this->assertSame(1, $benchmark['impressions']);
        $this->assertSame(1, $benchmark['click_throughs']);
        $this->assertSame(1.0, $benchmark['ctr']);
    }

    public function test_click_through_matching_does_not_false_positive_on_a_slug_prefix_collision(): void
    {
        $short = $this->category('Energia', ['slug' => 'energia-'.uniqid()]);
        $long = $this->category('Energia Rinnovabile', ['slug' => $short->slug.'-rinnovabile']);
        $article = $this->article($long->slug);

        $this->withHeaders(['referer' => route('categoria', $long->slug)])
            ->get(route('articolo', $article->slug))
            ->assertOk();

        $this->assertSame(0, CategoryHubEvent::where('category_slug', $short->slug)
            ->where('event_type', CategoryHubEvent::EVENT_CLICK_THROUGH)->count());
        $this->assertSame(1, CategoryHubEvent::where('category_slug', $long->slug)
            ->where('event_type', CategoryHubEvent::EVENT_CLICK_THROUGH)->count());
    }

    public function test_click_through_is_not_recorded_for_an_internal_audit_request(): void
    {
        $category = $this->category('Salute Test');
        $article = $this->article($category->slug);

        $this->withHeaders([
            'referer' => route('categoria', $category->slug),
            'X-Kairus-Internal-Audit' => '1',
        ])->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(0, CategoryHubEvent::where('event_type', CategoryHubEvent::EVENT_CLICK_THROUGH)->count());
    }

    // ── resolveCategoryHubSlugFromReferer(): parsing puro ─────────

    public function test_resolve_slug_from_referer_matches_an_exact_category_url(): void
    {
        $this->assertSame('salute', $this->service()->resolveCategoryHubSlugFromReferer('https://kairus.test/categoria/salute'));
    }

    public function test_resolve_slug_from_referer_matches_with_a_query_string(): void
    {
        $this->assertSame('salute', $this->service()->resolveCategoryHubSlugFromReferer('https://kairus.test/categoria/salute?page=2'));
    }

    public function test_resolve_slug_from_referer_matches_with_a_trailing_slash(): void
    {
        $this->assertSame('salute', $this->service()->resolveCategoryHubSlugFromReferer('https://kairus.test/categoria/salute/'));
    }

    public function test_resolve_slug_from_referer_returns_null_for_null(): void
    {
        $this->assertNull($this->service()->resolveCategoryHubSlugFromReferer(null));
    }

    public function test_resolve_slug_from_referer_returns_null_for_a_non_category_url(): void
    {
        $this->assertNull($this->service()->resolveCategoryHubSlugFromReferer('https://kairus.test/'));
        $this->assertNull($this->service()->resolveCategoryHubSlugFromReferer('https://kairus.test/articolo/qualche-slug'));
    }

    public function test_resolve_slug_from_referer_does_not_match_a_deeper_subpath(): void
    {
        $this->assertNull($this->service()->resolveCategoryHubSlugFromReferer('https://kairus.test/categoria/salute/qualcosa-altro'));
    }

    // ── Aggregazione ───────────────────────────────────────────────

    public function test_benchmark_for_computes_ctr_from_recorded_events(): void
    {
        $category = $this->category('Salute Test');

        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $category->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $category->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $category->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $category->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_CLICK_THROUGH, 'category_slug' => $category->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_CLICK_THROUGH, 'category_slug' => $category->slug]);

        $benchmark = $this->service()->benchmarkFor($category->slug);

        $this->assertSame(4, $benchmark['impressions']);
        $this->assertSame(2, $benchmark['click_throughs']);
        $this->assertSame(0.5, $benchmark['ctr']);
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

    public function test_hub_breakdown_aggregates_events_correctly_per_category(): void
    {
        $salute = $this->category('Salute Test');
        $energia = $this->category('Energia Test');

        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $salute->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $salute->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_CLICK_THROUGH, 'category_slug' => $salute->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $energia->slug]);

        $breakdown = $this->service()->hubBreakdown();

        $saluteRow = $breakdown->firstWhere('slug', $salute->slug);
        $energiaRow = $breakdown->firstWhere('slug', $energia->slug);

        $this->assertSame(2, $saluteRow['impressions']);
        $this->assertSame(1, $saluteRow['click_throughs']);
        $this->assertSame(1, $energiaRow['impressions']);
        $this->assertSame(0, $energiaRow['click_throughs']);
    }

    public function test_site_wide_totals_count_events_even_for_a_category_no_longer_publicly_visible(): void
    {
        $category = $this->category('Salute Test');

        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_IMPRESSION, 'category_slug' => $category->slug]);
        CategoryHubEvent::create(['event_type' => CategoryHubEvent::EVENT_CLICK_THROUGH, 'category_slug' => $category->slug]);

        // Disattivata DOPO aver registrato gli eventi: non deve sparire dai
        // totali sitewide, anche se ora esce da hubBreakdown().
        $category->forceFill(['is_active' => false])->save();

        $totals = $this->service()->siteWideTotals();

        $this->assertSame(1, $totals['impressions']);
        $this->assertSame(1, $totals['click_throughs']);
    }

    public function test_the_service_never_writes_to_categories_or_articles(): void
    {
        $category = $this->category('Salute Test');

        $categoriesBefore = Category::count();
        $articlesBefore = Article::count();

        $this->service()->benchmarkFor($category->slug);
        $this->service()->hubBreakdown();
        $this->service()->siteWideTotals();

        $this->assertSame($categoriesBefore, Category::count());
        $this->assertSame($articlesBefore, Article::count());
    }
}
