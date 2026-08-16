<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusterHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentClusterPhase2ATest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_multiple_editorial_findings(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'pillar_article_id' => null]);

        $health = app(ContentClusterHealth::class)->evaluate($cluster->load(['articles', 'pillarArticle']));

        $this->assertSame('EMPTY', $health['status']);
        $this->assertContains('EMPTY', $health['findings']);
        $this->assertContains('NO_PILLAR', $health['findings']);
        $this->assertSame(0, $health['article_count_published']);
    }

    public function test_health_detects_scheduled_only_primary_gaps_and_ordering_issue(): void
    {
        $article = $this->article('Programmato', Article::STATUS_SCHEDULED);
        $cluster = ContentCluster::factory()->create(['pillar_article_id' => null]);
        DB::table('article_content_cluster')->insert([
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
            'position' => 0,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $health = app(ContentClusterHealth::class)->evaluate($cluster->load(['articles', 'pillarArticle']));

        $this->assertContains('NO_PUBLIC_ARTICLES', $health['findings']);
        $this->assertContains('PRIMARY_GAPS', $health['findings']);
        $this->assertContains('ORDERING_ISSUE', $health['findings']);
        $this->assertSame(1, $health['scheduled_count']);
    }

    public function test_non_public_pillar_is_an_explicit_unhealthy_finding(): void
    {
        $public = $this->article('Pubblico');
        $draftPillar = $this->article('Pillar bozza', Article::STATUS_DRAFT);
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'pillar_article_id' => $draftPillar->id]);
        $cluster->articles()->attach([
            $public->id => ['position' => 10, 'is_primary' => true],
            $draftPillar->id => ['position' => 20, 'is_primary' => true],
        ]);

        $health = app(ContentClusterHealth::class)->evaluate($cluster->load(['articles', 'pillarArticle']));

        $this->assertFalse($health['pillar_public']);
        $this->assertContains('PILLAR_NOT_PUBLIC', $health['findings']);
        $this->assertSame('PILLAR_NOT_PUBLIC', $health['status']);
    }

    public function test_primary_coverage_counts_a_primary_membership_in_another_cluster(): void
    {
        $article = $this->article('Primary globale');
        $secondaryCluster = ContentCluster::factory()->create(['pillar_article_id' => $article->id]);
        $primaryCluster = ContentCluster::factory()->create();
        $secondaryCluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => false]);
        $primaryCluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $health = app(ContentClusterHealth::class)->evaluate($secondaryCluster->load(['articles', 'pillarArticle']));

        $this->assertSame(100, $health['primary_coverage']);
        $this->assertNotContains('PRIMARY_GAPS', $health['findings']);
    }

    public function test_orphan_counts_return_scalar_aggregates(): void
    {
        $this->article('Senza percorso');
        $covered = $this->article('Coperto');
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($covered->id, ['position' => 10, 'is_primary' => true]);

        $counts = app(ContentClusterHealth::class)->orphanCounts();

        $this->assertSame(1, $counts['without_cluster']);
        $this->assertSame(1, $counts['published_uncovered']);
        $this->assertSame(0, $counts['without_primary']);
        $this->assertSame(0, $counts['inactive_only']);
        $this->assertSame(0, $counts['scheduled_uncovered']);
    }

    public function test_dry_run_reports_plan_and_performs_zero_writes(): void
    {
        $article = $this->article('Mapped article');
        config()->set('content-clusters-initial', [[
            'slug' => 'test-path',
            'name' => 'Test path',
            'pillar' => $article->slug,
            'articles' => [['slug' => $article->slug, 'position' => 10, 'primary' => true]],
        ]]);

        $this->artisan('content-clusters:backfill-initial')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('CREATE CLUSTER test-path')
            ->expectsOutputToContain('ARTICLE MEMBERSHIP')
            ->assertSuccessful();

        $this->assertDatabaseMissing('content_clusters', ['slug' => 'test-path']);
        $this->assertSame(0, DB::table('article_content_cluster')->count());
    }

    public function test_apply_is_idempotent_and_preserves_unrelated_data(): void
    {
        $mapped = $this->article('Mapped article');
        $unrelated = $this->article('Unrelated article');
        $unrelatedCluster = ContentCluster::factory()->create(['slug' => 'unrelated']);
        $unrelatedCluster->articles()->attach($unrelated->id, ['position' => 10, 'is_primary' => false]);
        config()->set('content-clusters-initial', [[
            'slug' => 'test-path',
            'name' => 'Test path',
            'pillar' => $mapped->slug,
            'articles' => [['slug' => $mapped->slug, 'position' => 10, 'primary' => true]],
        ]]);

        $this->artisan('content-clusters:backfill-initial --apply')->assertSuccessful();
        $this->artisan('content-clusters:backfill-initial --apply')->assertSuccessful();

        $cluster = ContentCluster::where('slug', 'test-path')->firstOrFail();
        $this->assertSame(1, ContentCluster::where('slug', 'test-path')->count());
        $this->assertDatabaseHas('article_content_cluster', ['content_cluster_id' => $cluster->id, 'article_id' => $mapped->id, 'position' => 10, 'is_primary' => true]);
        $this->assertDatabaseHas('article_content_cluster', ['content_cluster_id' => $unrelatedCluster->id, 'article_id' => $unrelated->id]);
        $this->assertSame($mapped->id, $cluster->fresh()->pillar_article_id);
    }

    public function test_primary_conflict_is_skipped_and_missing_slug_is_safe(): void
    {
        $article = $this->article('Conflict article');
        $existing = ContentCluster::factory()->create(['slug' => 'existing']);
        $existing->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        config()->set('content-clusters-initial', [[
            'slug' => 'new-path',
            'name' => 'New path',
            'pillar' => 'missing-pillar',
            'articles' => [
                ['slug' => $article->slug, 'position' => 10, 'primary' => true],
                ['slug' => 'missing-article', 'position' => 20, 'primary' => false],
            ],
        ]]);

        $this->artisan('content-clusters:backfill-initial --apply')
            ->expectsOutputToContain('CONFLICT')
            ->expectsOutputToContain('MISSING ARTICLE')
            ->assertSuccessful();

        $new = ContentCluster::where('slug', 'new-path')->firstOrFail();
        $this->assertDatabaseMissing('article_content_cluster', ['content_cluster_id' => $new->id, 'article_id' => $article->id]);
        $this->assertDatabaseHas('article_content_cluster', ['content_cluster_id' => $existing->id, 'article_id' => $article->id, 'is_primary' => true]);
        $this->assertNull($new->pillar_article_id);
    }

    private function article(string $title, string $status = Article::STATUS_PUBLISHED): Article
    {
        $user = User::factory()->create();

        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'body' => 'Corpo.',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $status === Article::STATUS_SCHEDULED ? now()->addDay() : now()->subDay(),
        ]);
    }
}
