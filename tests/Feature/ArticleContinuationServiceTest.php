<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ArticleContinuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleContinuationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create();
    }

    // ── Percorso: priorità 1 ──────────────────────────────────────────

    public function test_next_article_of_the_active_cluster_wins_over_category_affinity(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $pathNext = $this->article('Prossima tappa', 'fisica');
        // Candidato di categoria che sarebbe scelto SE non ci fosse un
        // Percorso: più recente di pathNext, stessa categoria.
        $this->article('Più recente ma fuori percorso', 'fisica', now());

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $pathNext->id => ['position' => 20, 'is_primary' => false],
        ]);

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNotNull($result);
        $this->assertSame($pathNext->id, $result->id);
    }

    public function test_scheduled_next_in_cluster_is_never_surfaced(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $this->articleWithStatus('Programmato', 'fisica', Article::STATUS_SCHEDULED, now()->addDay());
        $fallback = $this->article('Affinità categoria', 'fisica');

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $scheduled = Article::where('title', 'Programmato')->firstOrFail();
        $cluster->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $scheduled->id => ['position' => 20, 'is_primary' => false],
        ]);

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNotNull($result);
        $this->assertSame($fallback->id, $result->id);
    }

    public function test_draft_next_in_cluster_is_never_surfaced(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $draft = $this->articleWithStatus('Bozza', 'fisica', Article::STATUS_DRAFT, null);
        $fallback = $this->article('Affinità categoria', 'fisica');

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $draft->id => ['position' => 20, 'is_primary' => false],
        ]);

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNotNull($result);
        $this->assertSame($fallback->id, $result->id);
    }

    public function test_last_article_of_cluster_does_not_wrap_and_falls_through_to_category(): void
    {
        $first = $this->article('Prima tappa', 'fisica');
        $last = $this->article('Ultima tappa', 'fisica');
        $fallback = $this->article('Affinità categoria', 'fisica');

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => false],
            $last->id => ['position' => 20, 'is_primary' => true],
        ]);

        $result = $this->service()->forArticle($last->fresh());

        $this->assertNotNull($result);
        $this->assertSame($fallback->id, $result->id);
        $this->assertNotSame($first->id, $result->id, 'must not wrap around to the first article of the path');
    }

    public function test_inactive_cluster_is_ignored_and_falls_through_to_category(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $pathNext = $this->article('In cluster inattivo', 'fisica');
        $fallback = $this->article('Affinità categoria', 'fisica');

        $cluster = ContentCluster::factory()->create(['is_active' => false]);
        $cluster->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $pathNext->id => ['position' => 20, 'is_primary' => false],
        ]);

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNotNull($result);
        $this->assertSame($fallback->id, $result->id);
    }

    public function test_multiple_clusters_defer_entirely_to_articlepathnavigation_primary_pick(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $primaryNext = $this->article('Tappa nel percorso primario', 'fisica');
        $secondaryNext = $this->article('Tappa nel percorso secondario', 'fisica');

        $secondary = ContentCluster::factory()->create(['name' => 'Secondario', 'slug' => 'secondario', 'is_active' => true, 'sort_order' => 1]);
        $primary = ContentCluster::factory()->create(['name' => 'Primario', 'slug' => 'primario', 'is_active' => true, 'sort_order' => 99]);

        $secondary->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => false],
            $secondaryNext->id => ['position' => 20, 'is_primary' => false],
        ]);
        $primary->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $primaryNext->id => ['position' => 20, 'is_primary' => false],
        ]);

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNotNull($result);
        $this->assertSame($primaryNext->id, $result->id);
    }

    public function test_previous_path_article_is_not_re_offered_by_the_category_fallback(): void
    {
        $first = $this->article('Prima tappa', 'fisica');
        $last = $this->article('Ultima tappa', 'fisica');

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => false],
            $last->id => ['position' => 20, 'is_primary' => true],
        ]);

        // Nessun altro articolo di categoria "fisica" disponibile a parte
        // "first" (il precedente del Percorso): il fallback deve restare
        // null piuttosto che riproporre "first".
        $result = $this->service()->forArticle($last->fresh());

        $this->assertNull($result);
    }

    // ── Categoria: priorità 2 ──────────────────────────────────────────

    public function test_falls_back_to_most_recent_same_primary_category_article(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $older = $this->article('Più vecchio', 'fisica', now()->subDay());
        $newer = $this->article('Più recente', 'fisica', now());

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNotNull($result);
        $this->assertSame($newer->id, $result->id);
        $this->assertNotSame($older->id, $result->id);
    }

    public function test_secondary_category_overlap_alone_does_not_produce_a_candidate(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $secondaryOnly = $this->article('Solo categoria secondaria condivisa', 'spazio');
        $secondaryOnly->secondaryCategories()->attach(
            Category::firstOrCreate(['slug' => 'fisica'], ['name' => 'Fisica'])->id
        );

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNull($result, 'secondary-category-only overlap must not drive the single continuation slot');
    }

    public function test_unrelated_category_and_no_path_produces_no_candidate(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $this->article('Argomento diverso', 'spazio');

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNull($result);
    }

    public function test_tie_break_on_identical_published_at_is_deterministic_by_id(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $sameInstant = now()->subHour();
        $lowerId = $this->articleWithStatus('Prima creata', 'fisica', Article::STATUS_PUBLISHED, $sameInstant);
        $higherId = $this->articleWithStatus('Seconda creata', 'fisica', Article::STATUS_PUBLISHED, $sameInstant);

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNotNull($result);
        $this->assertSame(max($lowerId->id, $higherId->id), $result->id);
    }

    // ── Visibilità ───────────────────────────────────────────────────

    public function test_current_article_is_never_returned_as_its_own_continuation(): void
    {
        $current = $this->article('Solo', 'fisica');

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNull($result);
    }

    public function test_no_valid_candidate_returns_null_rather_than_a_weak_fallback(): void
    {
        $current = $this->article('Unico articolo del sito', 'fisica');

        $result = $this->service()->forArticle($current->fresh());

        $this->assertNull($result);
    }

    // ── Determinismo e performance ──────────────────────────────────────

    public function test_same_database_state_produces_the_same_result_on_repeated_calls(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $this->article('Candidato', 'fisica');

        $service = $this->service();
        $first = $service->forArticle($current->fresh());
        $second = $service->forArticle($current->fresh());

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
    }

    public function test_category_fallback_path_costs_at_most_one_additional_query_beyond_navigation(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $this->article('Candidato', 'fisica');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->forArticle($current->fresh());
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // ArticlePathNavigation::forArticle() costa già fino a 2 query
        // (verificato in ArticlePathNavigationTest); il fallback di
        // categoria ne aggiunge esattamente 1.
        $this->assertLessThanOrEqual(3, $queryCount);
    }

    private function service(): ArticleContinuationService
    {
        return app(ArticleContinuationService::class);
    }

    private function article(string $title, string $category, $publishedAt = null): Article
    {
        return $this->articleWithStatus($title, $category, Article::STATUS_PUBLISHED, $publishedAt ?? now()->subMinute());
    }

    private function articleWithStatus(string $title, string $category, string $status, $publishedAt): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'excerpt' => 'Estratto.',
            'category' => $category,
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt,
        ]);
    }
}
