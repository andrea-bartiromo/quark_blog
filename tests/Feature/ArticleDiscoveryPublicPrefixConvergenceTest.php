<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\Discovery\ArticleDiscoveryAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleDiscoveryPublicPrefixConvergenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_percorso_counts_only_members_in_its_public_contiguous_prefix(): void
    {
        $first = $this->article('discovery-prefix-first', Article::STATUS_PUBLISHED);
        $gap = $this->article('discovery-prefix-gap', Article::STATUS_SCHEDULED);
        $later = $this->article('discovery-prefix-later', Article::STATUS_PUBLISHED);
        $cluster = ContentCluster::create([
            'name' => 'Discovery Prefix',
            'slug' => 'discovery-prefix',
            'is_active' => true,
        ]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $gap->id => ['position' => 20, 'is_primary' => false],
            $later->id => ['position' => 30, 'is_primary' => false],
        ]);

        $rows = app(ArticleDiscoveryAuditService::class)->audit()->keyBy('article_id');

        $this->assertSame(1, $rows[$first->id]['active_path_count']);
        $this->assertSame(0, $rows[$later->id]['active_path_count']);
        $this->assertNotContains('NO_ACTIVE_PATH', $rows[$first->id]['risks']);
        $this->assertContains('NO_ACTIVE_PATH', $rows[$later->id]['risks']);
        $this->assertFalse($rows->has($gap->id));
    }

    public function test_public_members_after_gap_become_discoverable_when_blocking_step_becomes_published(): void
    {
        $first = $this->article('discovery-open-first', Article::STATUS_PUBLISHED);
        $gap = $this->article('discovery-open-gap', Article::STATUS_SCHEDULED);
        $later = $this->article('discovery-open-later', Article::STATUS_PUBLISHED);
        $cluster = ContentCluster::create([
            'name' => 'Discovery Opens',
            'slug' => 'discovery-opens',
            'is_active' => true,
        ]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $gap->id => ['position' => 20, 'is_primary' => false],
            $later->id => ['position' => 30, 'is_primary' => false],
        ]);

        $before = app(ArticleDiscoveryAuditService::class)->audit()->firstWhere('article_id', $later->id);
        $this->assertSame(0, $before['active_path_count']);

        $gap->update([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $after = app(ArticleDiscoveryAuditService::class)->audit()->firstWhere('article_id', $later->id);
        $this->assertSame(1, $after['active_path_count']);
    }

    public function test_inactive_percorso_never_counts_as_discovery_even_with_public_members(): void
    {
        $article = $this->article('discovery-inactive-path', Article::STATUS_PUBLISHED);
        $cluster = ContentCluster::create([
            'name' => 'Inactive Discovery',
            'slug' => 'inactive-discovery',
            'is_active' => false,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        $row = app(ArticleDiscoveryAuditService::class)->audit()->firstWhere('article_id', $article->id);

        $this->assertSame(0, $row['active_path_count']);
        $this->assertContains('NO_ACTIVE_PATH', $row['risks']);
    }

    public function test_percorso_corpus_query_count_does_not_scale_with_number_of_paths(): void
    {
        $article = $this->article('discovery-query-budget', Article::STATUS_PUBLISHED);
        $this->clusterWith($article, 'query-one');

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ArticleDiscoveryAuditService::class)->audit();
        $singlePathQueryCount = count(DB::getQueryLog());

        foreach (range(2, 8) as $index) {
            $this->clusterWith($article, 'query-'.$index);
        }

        DB::flushQueryLog();
        app(ArticleDiscoveryAuditService::class)->audit();
        $eightPathQueryCount = count(DB::getQueryLog());

        $this->assertSame(
            $singlePathQueryCount,
            $eightPathQueryCount,
            'Adding Percorsi must not add one query per path.',
        );
    }

    private function clusterWith(Article $article, string $slug): ContentCluster
    {
        $cluster = ContentCluster::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);

        return $cluster;
    }

    private function article(string $slug, string $status): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Excerpt',
            'body' => '<p>Body</p>',
            'category' => 'discovery-prefix-test',
            'status' => $status,
            'published_at' => match ($status) {
                Article::STATUS_PUBLISHED => now()->subMinute(),
                Article::STATUS_SCHEDULED => now()->addDay(),
                default => null,
            },
            'read_minutes' => 1,
        ]));
    }
}
