<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PercorsoCoverageAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'editor']);
    }

    public function test_it_flags_published_and_scheduled_articles_without_a_path(): void
    {
        $published = $this->article('Pubblicato isolato', Article::STATUS_PUBLISHED, now()->subDay());
        $scheduled = $this->article('Programmato isolato', Article::STATUS_SCHEDULED, now()->addDay());

        $report = app(PercorsoCoverageAuditService::class)->audit();

        $this->assertSame([$published->id], array_column($report['published_without_path'], 'id'));
        $this->assertSame([$scheduled->id], array_column($report['scheduled_without_path'], 'id'));
    }

    public function test_it_reports_singletons_duplicate_positions_and_non_publishable_members(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $published = $this->article('Tappa pubblica', Article::STATUS_PUBLISHED, now()->subDay());
        $draft = $this->article('Tappa bozza', Article::STATUS_DRAFT, null);

        $cluster->articles()->attach($published->id, ['position' => 1, 'is_primary' => true]);
        $cluster->articles()->attach($draft->id, ['position' => 1, 'is_primary' => false]);

        $report = app(PercorsoCoverageAuditService::class)->audit();

        $this->assertSame([1], $report['paths_with_duplicate_positions'][0]['duplicate_positions']);
        $this->assertSame([$draft->id], array_column($report['paths_with_non_publishable_members'][0]['non_publishable_members'], 'id'));
    }

    public function test_missing_pillar_is_not_treated_as_an_error_because_the_domain_allows_it(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'pillar_article_id' => null]);
        $article = $this->article('Una tappa', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach($article->id, ['position' => 1, 'is_primary' => true]);

        $report = app(PercorsoCoverageAuditService::class)->audit();

        $this->assertSame([], $report['paths_with_incoherent_pillar']);
        $this->assertTrue($report['policy_notes']['missing_pillar_is_not_an_error']);
    }

    public function test_it_reports_a_pillar_that_is_not_a_member_of_its_path(): void
    {
        $member = $this->article('Tappa', Article::STATUS_PUBLISHED, now()->subDay());
        $pillar = $this->article('Pillar esterno', Article::STATUS_PUBLISHED, now()->subDays(2));
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'pillar_article_id' => $pillar->id]);
        $cluster->articles()->attach($member->id, ['position' => 1, 'is_primary' => true]);

        $report = app(PercorsoCoverageAuditService::class)->audit();

        $this->assertSame('pillar_not_in_path', $report['paths_with_incoherent_pillar'][0]['pillar_issue']);
    }

    public function test_multiple_path_membership_is_reported_without_an_arbitrary_failure_threshold(): void
    {
        $article = $this->article('Tappa condivisa', Article::STATUS_PUBLISHED, now()->subDay());
        $first = ContentCluster::factory()->create(['is_active' => true]);
        $second = ContentCluster::factory()->create(['is_active' => true]);
        $first->articles()->attach($article->id, ['position' => 1, 'is_primary' => true]);
        $second->articles()->attach($article->id, ['position' => 1, 'is_primary' => false]);

        $report = app(PercorsoCoverageAuditService::class)->audit();

        $this->assertSame(2, $report['articles_in_multiple_paths'][0]['path_count']);
        $this->assertTrue($report['policy_notes']['multiple_paths_are_reported_not_failed']);
    }

    private function article(string $title, string $status, $publishedAt): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario editoriale sufficientemente completo per il test.',
            'body' => '<p>Corpo articolo di test con contenuto editoriale sufficiente.</p>',
            'category' => 'spazio',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }
}
