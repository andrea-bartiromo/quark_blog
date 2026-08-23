<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentHealth\ArticleContentHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleContentHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
    }

    public function test_complete_article_produces_explainable_checks_without_score(): void
    {
        $article = $this->article([
            'cover_image' => 'cover.jpg',
            'cover_alt' => 'Illustrazione scientifica.',
            'cover_credit' => 'Kairus',
            'cover_source' => 'Archivio Kairus',
            'seo_title' => 'Titolo SEO',
            'seo_description' => 'Descrizione SEO',
            'primary_sources' => 'Fonte primaria verificata',
            'body' => '<p>Testo con <a href="/articolo/relativita-speciale">collegamento interno</a>.</p>',
        ]);
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($article->id, [
            'position' => 10,
            'is_primary' => true,
        ]);

        $checks = $this->service()->evaluate($article);

        $this->assertCount(10, $checks);
        $this->assertSame(10, $checks->pluck('id')->unique()->count());
        $this->assertTrue($checks->every(fn (array $check) => array_keys($check) === [
            'id', 'label', 'status', 'reason', 'action_url',
        ]));
        $this->assertFalse($checks->pluck('id')->contains('score'));
        $this->assertSame(
            ArticleContentHealthService::STATUS_NOT_APPLICABLE,
            $checks->firstWhere('id', 'freshness')['status']
        );
        $this->assertTrue(
            $checks->reject(fn (array $check) => $check['id'] === 'freshness')
                ->every(fn (array $check) => $check['status'] === ArticleContentHealthService::STATUS_OK)
        );
    }

    public function test_missing_supported_fields_are_warnings_not_blockers(): void
    {
        $article = $this->article([
            'cover_image' => null,
            'excerpt' => null,
            'seo_title' => null,
            'seo_description' => null,
            'primary_sources' => null,
            'body' => '<p>Nessun link interno.</p>',
        ]);

        $before = $article->fresh()->getAttributes();
        $checks = $this->service()->evaluate($article);

        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $checks->firstWhere('id', 'cover')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_NOT_APPLICABLE, $checks->firstWhere('id', 'cover_alt')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_NOT_APPLICABLE, $checks->firstWhere('id', 'cover_attribution')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $checks->firstWhere('id', 'summary')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $checks->firstWhere('id', 'seo_metadata')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $checks->firstWhere('id', 'internal_links')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $checks->firstWhere('id', 'percorso')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $checks->firstWhere('id', 'sources')['status']);
        $this->assertSame($before, $article->fresh()->getAttributes(), 'Content Health must be read-only.');
    }

    public function test_cover_specific_checks_only_apply_when_cover_exists(): void
    {
        $withoutCover = $this->article(['cover_image' => null]);
        $withUnattributedCover = $this->article([
            'title' => 'Articolo con cover',
            'cover_image' => 'cover.jpg',
            'cover_alt' => null,
            'cover_credit' => null,
            'cover_source' => null,
            'cover_source_url' => null,
        ]);

        $noCoverChecks = $this->service()->evaluate($withoutCover);
        $coverChecks = $this->service()->evaluate($withUnattributedCover);

        $this->assertSame(ArticleContentHealthService::STATUS_NOT_APPLICABLE, $noCoverChecks->firstWhere('id', 'cover_alt')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_NOT_APPLICABLE, $noCoverChecks->firstWhere('id', 'cover_attribution')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $coverChecks->firstWhere('id', 'cover_alt')['status']);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $coverChecks->firstWhere('id', 'cover_attribution')['status']);
    }

    public function test_seo_warning_explains_missing_fields_without_ignoring_existing_fallbacks(): void
    {
        $article = $this->article([
            'seo_title' => 'Titolo SEO presente',
            'seo_description' => null,
        ]);

        $check = $this->service()->evaluate($article)->firstWhere('id', 'seo_metadata');

        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $check['status']);
        $this->assertStringContainsString('SEO description', $check['reason']);
        $this->assertStringContainsString('fallback', strtolower($check['reason']));
    }

    public function test_percorso_check_costs_at_most_one_query_and_zero_when_preloaded(): void
    {
        $article = $this->article();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->evaluate($article);
        $lazyQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $preloaded = $article->fresh()->load('contentClusters:id');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->evaluate($preloaded);
        $preloadedQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(1, $lazyQueryCount);
        $this->assertSame(0, $preloadedQueryCount);
    }

    public function test_unsaved_article_can_be_evaluated_without_side_effects_or_database_queries(): void
    {
        $article = new Article([
            'title' => 'Bozza non salvata',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo</p>',
            'category' => null,
        ]);
        $article->setRelation('contentClusters', collect());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $checks = $this->service()->evaluate($article);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queryCount);
        $this->assertSame(ArticleContentHealthService::STATUS_WARNING, $checks->firstWhere('id', 'category')['status']);
        $this->assertFalse($article->exists);
    }

    private function service(): ArticleContentHealthService
    {
        return app(ArticleContentHealthService::class);
    }

    private function article(array $overrides = []): Article
    {
        $title = $overrides['title'] ?? 'Articolo Content Health';

        return Article::create(array_merge([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario editoriale.',
            'body' => '<p>Corpo articolo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 2,
        ], $overrides));
    }
}
