<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentClusterSuggestion;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ContentClusterPhase2CPruningRegressionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_sync_detach_uses_composite_pivot_and_stales_category_evidence(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'pruning-regression', 'is_active' => true]);
        config()->set('content-clusters-initial', []);
        $first = $this->article('Pruning first');
        $second = $this->article('Pruning second');
        $candidate = $this->article('Pruning candidate');
        $memberships = app(ContentClusterMembershipService::class);

        $memberships->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        $suggestion = ContentClusterSuggestion::query()
            ->where('article_id', $candidate->id)
            ->where('content_cluster_id', $cluster->id)
            ->firstOrFail();

        $memberships->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
        ], null);

        $this->assertDatabaseMissing('article_content_cluster', [
            'article_id' => $second->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $this->assertSame(ContentClusterSuggestion::STATUS_STALE, $suggestion->fresh()->status);
    }

    public function test_primary_and_position_only_changes_do_not_refresh_category_suggestion(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'no-churn', 'is_active' => true]);
        config()->set('content-clusters-initial', []);
        $first = $this->article('No churn first');
        $second = $this->article('No churn second');
        $candidate = $this->article('No churn candidate');
        $memberships = app(ContentClusterMembershipService::class);

        $memberships->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10, 'is_primary' => false],
            ['article_id' => $second->id, 'position' => 20, 'is_primary' => false],
        ], null);

        $suggestion = ContentClusterSuggestion::query()
            ->where('article_id', $candidate->id)
            ->where('content_cluster_id', $cluster->id)
            ->firstOrFail();
        $updatedAt = $suggestion->updated_at;

        $memberships->sync($cluster, [
            ['article_id' => $second->id, 'position' => 10, 'is_primary' => true],
            ['article_id' => $first->id, 'position' => 20, 'is_primary' => false],
        ], null);

        $this->assertSame($updatedAt->toISOString(), $suggestion->fresh()->updated_at->toISOString());
        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $suggestion->fresh()->status);
    }

    private function article(string $title): Article
    {
        return Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'body' => 'Corpo.',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subDay(),
        ]);
    }
}
