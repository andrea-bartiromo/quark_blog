<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentClusterSuggestion;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ContentClusterPhase2CMembershipSignalTest extends TestCase
{
    use DatabaseMigrations;

    public function test_membership_count_one_to_two_creates_category_suggestion(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'one-to-two', 'is_active' => true]);
        config()->set('content-clusters-initial', []);
        $first = $this->article('One to two first');
        $second = $this->article('One to two second');
        $candidate = $this->article('One to two candidate');
        $memberships = app(ContentClusterMembershipService::class);

        $memberships->addMembership($cluster, $first);
        $this->assertDatabaseMissing('content_cluster_suggestions', [
            'article_id' => $candidate->id,
            'content_cluster_id' => $cluster->id,
        ]);

        $memberships->addMembership($cluster, $second);

        $suggestion = ContentClusterSuggestion::query()
            ->where('article_id', $candidate->id)
            ->where('content_cluster_id', $cluster->id)
            ->firstOrFail();

        $this->assertSame(ContentClusterSuggestion::STATUS_PENDING, $suggestion->status);
        $this->assertSame(65, $suggestion->confidence);
        $this->assertContains('Categoria fisica: 2 membership editoriali confermate.', $suggestion->reasons);
    }

    public function test_membership_count_two_to_one_stales_category_only_suggestion(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'two-to-one', 'is_active' => true]);
        config()->set('content-clusters-initial', []);
        $first = $this->article('Two to one first');
        $second = $this->article('Two to one second');
        $candidate = $this->article('Two to one candidate');
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

        $this->assertSame(ContentClusterSuggestion::STATUS_STALE, $suggestion->fresh()->status);
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
