<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentClusterSuggestion;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ContentClusterPhase2CArticleCohortTest extends TestCase
{
    use DatabaseMigrations;

    public function test_member_category_change_refreshes_old_and_new_category_cohorts(): void
    {
        $oldCluster = ContentCluster::factory()->create(['slug' => 'category-old', 'is_active' => true]);
        $newCluster = ContentCluster::factory()->create(['slug' => 'category-new', 'is_active' => true]);
        config()->set('content-clusters-initial', []);

        $changing = $this->article('Changing member');
        $oldMember = $this->article('Old category member');
        $oldCandidate = $this->article('Old category candidate');
        $newMember = $this->article('New category member', 'chimica');
        $newCandidate = $this->article('New category candidate', 'chimica');
        $memberships = app(ContentClusterMembershipService::class);

        $memberships->addMembership($oldCluster, $oldMember);
        $memberships->addMembership($oldCluster, $changing);
        $memberships->addMembership($newCluster, $newMember);
        $memberships->addMembership($newCluster, $changing);

        $oldSuggestion = ContentClusterSuggestion::query()
            ->where('article_id', $oldCandidate->id)
            ->where('content_cluster_id', $oldCluster->id)
            ->firstOrFail();

        $this->assertDatabaseMissing('content_cluster_suggestions', [
            'article_id' => $newCandidate->id,
            'content_cluster_id' => $newCluster->id,
        ]);

        $changing->update(['category' => 'chimica']);

        $this->assertSame(ContentClusterSuggestion::STATUS_STALE, $oldSuggestion->fresh()->status);
        $this->assertDatabaseHas('content_cluster_suggestions', [
            'article_id' => $newCandidate->id,
            'content_cluster_id' => $newCluster->id,
            'status' => ContentClusterSuggestion::STATUS_PENDING,
        ]);
    }

    public function test_member_deletion_refreshes_category_cohort_after_pivot_cascade(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'delete-member', 'is_active' => true]);
        config()->set('content-clusters-initial', []);
        $first = $this->article('Delete cohort first');
        $deleting = $this->article('Delete cohort second');
        $candidate = $this->article('Delete cohort candidate');
        $memberships = app(ContentClusterMembershipService::class);

        $memberships->addMembership($cluster, $first);
        $memberships->addMembership($cluster, $deleting);

        $suggestion = ContentClusterSuggestion::query()
            ->where('article_id', $candidate->id)
            ->where('content_cluster_id', $cluster->id)
            ->firstOrFail();

        $deleting->delete();

        $this->assertSame(ContentClusterSuggestion::STATUS_STALE, $suggestion->fresh()->status);
    }

    private function article(string $title, string $category = 'fisica'): Article
    {
        return Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'body' => 'Corpo.',
            'excerpt' => 'Estratto.',
            'category' => $category,
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subDay(),
        ]);
    }
}
