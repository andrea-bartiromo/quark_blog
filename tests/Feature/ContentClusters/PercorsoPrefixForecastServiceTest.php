<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use App\Services\ContentClusters\PercorsoPrefixForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 20 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): "Given scheduled dates, calculate how a path's public prefix
 * is expected to grow over time."
 */
class PercorsoPrefixForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'editor']);
    }

    private function article(string $title, string $status, $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid('', true),
            'excerpt' => 'Sommario editoriale sufficientemente completo per il test.',
            'body' => '<p>Corpo articolo di test con contenuto editoriale sufficiente.</p>',
            'category' => 'spazio',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }

    public function test_an_empty_cluster_returns_an_inert_forecast(): void
    {
        $cluster = ContentCluster::factory()->create();

        $result = app(PercorsoPrefixForecastService::class)->forecast($cluster);

        $this->assertSame(0, $result['current_prefix_length']);
        $this->assertSame([], $result['forecast_steps']);
        $this->assertNull($result['blocked_by']);
    }

    public function test_a_fully_published_cluster_with_no_trailing_members_has_no_forecast_steps(): void
    {
        $cluster = ContentCluster::factory()->create();
        $first = $this->article('Prima tappa', Article::STATUS_PUBLISHED, now()->subDay());
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
        ], null);

        $result = app(PercorsoPrefixForecastService::class)->forecast($cluster);

        $this->assertSame(1, $result['current_prefix_length']);
        $this->assertSame([], $result['forecast_steps']);
        $this->assertNull($result['blocked_by']);
    }

    public function test_a_scheduled_member_immediately_after_the_current_prefix_is_forecast(): void
    {
        $cluster = ContentCluster::factory()->create();
        $published = $this->article('Pubblicato', Article::STATUS_PUBLISHED, now()->subDay());
        $scheduled = $this->article('Programmato', Article::STATUS_SCHEDULED, now()->addWeek());
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $published->id, 'position' => 10],
            ['article_id' => $scheduled->id, 'position' => 20],
        ], null);

        $result = app(PercorsoPrefixForecastService::class)->forecast($cluster);

        $this->assertSame(1, $result['current_prefix_length']);
        $this->assertCount(1, $result['forecast_steps']);
        $this->assertSame($scheduled->id, $result['forecast_steps'][0]['article_id']);
        $this->assertSame(2, $result['forecast_steps'][0]['position']);
        $this->assertTrue($result['forecast_steps'][0]['expected_at']->equalTo($scheduled->publishedAtForEditors()));
        $this->assertNull($result['blocked_by']);
    }

    public function test_a_chain_of_scheduled_members_in_increasing_date_order_forecasts_every_step(): void
    {
        $cluster = ContentCluster::factory()->create();
        $first = $this->article('Programmato prima', Article::STATUS_SCHEDULED, now()->addDays(2));
        $second = $this->article('Programmato dopo', Article::STATUS_SCHEDULED, now()->addDays(5));
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        $result = app(PercorsoPrefixForecastService::class)->forecast($cluster);

        $this->assertSame(0, $result['current_prefix_length']);
        $this->assertCount(2, $result['forecast_steps']);
        $this->assertSame([$first->id, $second->id], array_column($result['forecast_steps'], 'article_id'));
        $this->assertNull($result['blocked_by']);
    }

    public function test_a_draft_member_right_after_the_prefix_blocks_the_forecast_with_no_date(): void
    {
        $cluster = ContentCluster::factory()->create();
        $published = $this->article('Pubblicato', Article::STATUS_PUBLISHED, now()->subDay());
        $draft = $this->article('Bozza', Article::STATUS_DRAFT, null);
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $published->id, 'position' => 10],
            ['article_id' => $draft->id, 'position' => 20],
        ], null);

        $result = app(PercorsoPrefixForecastService::class)->forecast($cluster);

        $this->assertSame([], $result['forecast_steps']);
        $this->assertNotNull($result['blocked_by']);
        $this->assertSame($draft->id, $result['blocked_by']['article_id']);
        $this->assertSame(Article::STATUS_DRAFT, $result['blocked_by']['status']);
    }

    public function test_a_scheduled_member_whose_date_precedes_the_previous_forecast_step_stops_the_chain(): void
    {
        $cluster = ContentCluster::factory()->create();
        $first = $this->article('Programmato più tardi', Article::STATUS_SCHEDULED, now()->addWeek());
        $second = $this->article('Programmato prima, fuori ordine', Article::STATUS_SCHEDULED, now()->addDay());
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        $result = app(PercorsoPrefixForecastService::class)->forecast($cluster);

        $this->assertCount(1, $result['forecast_steps']);
        $this->assertSame($first->id, $result['forecast_steps'][0]['article_id']);
        $this->assertNotNull($result['blocked_by']);
        $this->assertSame($second->id, $result['blocked_by']['article_id']);
        $this->assertSame('chronological_inversion', $result['blocked_by']['status']);
    }

    public function test_forecast_never_reaches_beyond_a_gap_that_already_exists_in_the_current_prefix(): void
    {
        $cluster = ContentCluster::factory()->create();
        $draft = $this->article('Bozza iniziale', Article::STATUS_DRAFT, null);
        $scheduledAfterGap = $this->article('Programmato dopo il blocco', Article::STATUS_SCHEDULED, now()->addWeek());
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $draft->id, 'position' => 10],
            ['article_id' => $scheduledAfterGap->id, 'position' => 20],
        ], null);

        $result = app(PercorsoPrefixForecastService::class)->forecast($cluster);

        $this->assertSame(0, $result['current_prefix_length']);
        $this->assertSame([], $result['forecast_steps']);
        $this->assertSame($draft->id, $result['blocked_by']['article_id']);
    }
}
