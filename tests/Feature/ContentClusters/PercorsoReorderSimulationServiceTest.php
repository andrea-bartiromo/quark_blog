<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use App\Services\ContentClusters\PercorsoReorderSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 18 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): "Create a reusable simulation service that can evaluate a
 * proposed sequence without DB mutation."
 */
class PercorsoReorderSimulationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'editor']);
    }

    private function article(string $title, string $status = Article::STATUS_PUBLISHED, $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid('', true),
            'excerpt' => 'Sommario editoriale sufficientemente completo per il test.',
            'body' => '<p>Corpo articolo di test con contenuto editoriale sufficiente.</p>',
            'category' => 'spazio',
            'status' => $status,
            'published_at' => $status === Article::STATUS_PUBLISHED ? ($publishedAt ?? now()->subDay()) : $publishedAt,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }

    public function test_reordering_two_published_members_never_writes_to_the_database(): void
    {
        $cluster = ContentCluster::factory()->create();
        $first = $this->article('Prima tappa reale');
        $second = $this->article('Seconda tappa reale');
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10, 'transition_text' => 'Verso la seconda tappa.'],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        app(PercorsoReorderSimulationService::class)->simulate($cluster, [
            $first->id => 30,
            $second->id => 10,
        ]);

        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $first->id, 'position' => 10]);
        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $second->id, 'position' => 20]);
    }

    public function test_simulated_prefix_follows_the_proposed_order_not_the_stored_one(): void
    {
        $cluster = ContentCluster::factory()->create();
        $first = $this->article('Attualmente prima');
        $second = $this->article('Attualmente seconda');
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        $result = app(PercorsoReorderSimulationService::class)->simulate($cluster, [
            $first->id => 20,
            $second->id => 10,
        ]);

        $this->assertSame([$second->id, $first->id], $result['simulated_prefix']->pluck('id')->all());
        $this->assertNull($result['first_blocker']);
    }

    public function test_first_blocker_is_the_first_non_public_member_in_the_proposed_order(): void
    {
        $cluster = ContentCluster::factory()->create();
        $published = $this->article('Pubblico', Article::STATUS_PUBLISHED);
        $draft = $this->article('Bozza', Article::STATUS_DRAFT, null);
        $trailingPublished = $this->article('Pubblico dopo il blocco', Article::STATUS_PUBLISHED);
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $published->id, 'position' => 10],
            ['article_id' => $draft->id, 'position' => 20],
            ['article_id' => $trailingPublished->id, 'position' => 30],
        ], null);

        $result = app(PercorsoReorderSimulationService::class)->simulate($cluster, []);

        $this->assertSame([$published->id], $result['simulated_prefix']->pluck('id')->all());
        $this->assertNotNull($result['first_blocker']);
        $this->assertSame($draft->id, $result['first_blocker']->id);
    }

    public function test_pillar_reachability_is_null_without_a_pillar_and_true_or_false_with_one(): void
    {
        $cluster = ContentCluster::factory()->create();
        $pillar = $this->article('Pillar', Article::STATUS_PUBLISHED);
        $hidden = $this->article('Nascosto', Article::STATUS_DRAFT, null);
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $hidden->id, 'position' => 10],
            ['article_id' => $pillar->id, 'position' => 20],
        ], null);

        $withoutPillar = app(PercorsoReorderSimulationService::class)->simulate($cluster, []);
        $this->assertNull($withoutPillar['pillar_reachable']);

        // Il pillar è in seconda posizione ma la prima tappa è nascosta:
        // il prefisso pubblico si ferma prima di raggiungerlo.
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $hidden->id, 'position' => 10],
            ['article_id' => $pillar->id, 'position' => 20],
        ], $pillar->id);
        $cluster->refresh();

        $unreachable = app(PercorsoReorderSimulationService::class)->simulate($cluster, []);
        $this->assertFalse($unreachable['pillar_reachable']);

        // Proponendo il pillar come prima tappa, torna raggiungibile.
        $reachable = app(PercorsoReorderSimulationService::class)->simulate($cluster, [
            $pillar->id => 5,
        ]);
        $this->assertTrue($reachable['pillar_reachable']);
    }

    public function test_chronology_warning_flags_a_proposed_order_that_would_invert_publish_dates(): void
    {
        $cluster = ContentCluster::factory()->create();
        $earlier = $this->article('Pubblicato prima', Article::STATUS_PUBLISHED, now()->subWeek());
        $later = $this->article('Pubblicato dopo', Article::STATUS_PUBLISHED, now());
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $earlier->id, 'position' => 10],
            ['article_id' => $later->id, 'position' => 20],
        ], null);

        $result = app(PercorsoReorderSimulationService::class)->simulate($cluster, [
            $later->id => 5,
            $earlier->id => 20,
        ]);

        $this->assertCount(1, $result['chronology_warnings']);
        $this->assertSame($later->id, $result['chronology_warnings'][0]['earlier']->id);
        $this->assertSame($earlier->id, $result['chronology_warnings'][0]['later']->id);
    }

    public function test_transition_impact_follows_the_new_terminal_step_not_the_stored_one(): void
    {
        $cluster = ContentCluster::factory()->create();
        $first = $this->article('Prima tappa con raccordo');
        $second = $this->article('Seconda tappa senza raccordo, oggi terminale');
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10, 'transition_text' => 'Prosegue verso la seconda.'],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        // Nell'ordine reale $second è terminale: nessun impatto per lei.
        $currentOrder = app(PercorsoReorderSimulationService::class)->simulate($cluster, []);
        $this->assertSame([], $currentOrder['transition_impacts']);

        // Proponendo di anteporla, $second smette di essere terminale e le
        // manca un raccordo: l'esenzione deve seguire l'ordine proposto.
        $reordered = app(PercorsoReorderSimulationService::class)->simulate($cluster, [
            $second->id => 5,
            $first->id => 20,
        ]);

        $this->assertCount(1, $reordered['transition_impacts']);
        $this->assertSame($second->id, $reordered['transition_impacts'][0]['article_id']);
    }

    public function test_an_empty_cluster_returns_an_inert_result(): void
    {
        $cluster = ContentCluster::factory()->create();

        $result = app(PercorsoReorderSimulationService::class)->simulate($cluster, []);

        $this->assertTrue($result['simulated_prefix']->isEmpty());
        $this->assertNull($result['first_blocker']);
        $this->assertNull($result['pillar_reachable']);
        $this->assertSame([], $result['chronology_warnings']);
        $this->assertSame([], $result['transition_impacts']);
    }
}
