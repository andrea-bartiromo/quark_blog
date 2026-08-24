<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Percorsi Editorial Order Health: PercorsoCoverageAuditService::editorialOrderHealth().
 * Read-only — nessun test qui deve mai osservare una mutazione di
 * lifecycle_status, is_active, publish_at o dell'ordine dei pivot.
 */
class PercorsoEditorialOrderHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'editor']);
    }

    public function test_it_is_strictly_read_only(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $article = $this->article('Tappa', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        $before = $cluster->fresh()->getAttributes();

        app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertSame($before, $cluster->fresh()->getAttributes());
    }

    public function test_duplicate_position_is_a_structural_error(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $first = $this->article('Prima', Article::STATUS_PUBLISHED, now()->subDays(2));
        $second = $this->article('Seconda', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $second->id => ['position' => 10, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['structural_error']['duplicate_position']);
        $this->assertSame([10], $report['structural_error']['duplicate_position'][0]['duplicate_position']);
    }

    public function test_missing_position_is_a_structural_error(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $article = $this->article('Senza posizione', Article::STATUS_PUBLISHED, now()->subDay());
        // insertOrIgnore per aggirare eventuali default applicativi e
        // provare davvero il caso position=NULL a livello di riga.
        DB::table('article_content_cluster')->insert([
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
            'position' => null,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['structural_error']['missing_position']);
        $this->assertSame($article->id, $report['structural_error']['missing_position'][0]['missing_position'][0]['id']);
    }

    public function test_non_positive_position_is_a_structural_error(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $article = $this->article('Posizione zero', Article::STATUS_PUBLISHED, now()->subDay());
        DB::table('article_content_cluster')->insert([
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
            'position' => 0,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['structural_error']['non_positive_position']);
    }

    public function test_published_beyond_gap_is_a_publication_warning(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $first = $this->article('Prima pubblica', Article::STATUS_PUBLISHED, now()->subDays(2));
        $gap = $this->article('Bozza bloccante', Article::STATUS_DRAFT, null);
        $trapped = $this->article('Pubblicato ma intrappolato', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $gap->id => ['position' => 20, 'is_primary' => false],
            $trapped->id => ['position' => 30, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['publication_warning']['published_beyond_gap']);
        $this->assertSame(
            $trapped->id,
            $report['publication_warning']['published_beyond_gap'][0]['published_beyond_gap'][0]['id']
        );
    }

    public function test_pillar_outside_reachable_prefix_is_a_publication_warning(): void
    {
        $first = $this->article('Prima pubblica', Article::STATUS_PUBLISHED, now()->subDays(2));
        $gap = $this->article('Bozza bloccante', Article::STATUS_DRAFT, null);
        $pillarBeyondGap = $this->article('Pillar oltre il gap', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'pillar_article_id' => $pillarBeyondGap->id]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $gap->id => ['position' => 20, 'is_primary' => false],
            $pillarBeyondGap->id => ['position' => 30, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['publication_warning']['pillar_outside_reachable_prefix']);
    }

    public function test_complete_lifecycle_with_hidden_remainder_is_a_publication_warning(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);
        $first = $this->article('Prima', Article::STATUS_PUBLISHED, now()->subDay());
        $hidden = $this->article('Nascosta', Article::STATUS_SCHEDULED, now()->addDay());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $hidden->id => ['position' => 20, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['publication_warning']['complete_with_hidden_remainder']);
    }

    public function test_updating_lifecycle_with_hidden_remainder_is_not_flagged(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $first = $this->article('Prima', Article::STATUS_PUBLISHED, now()->subDay());
        $hidden = $this->article('Nascosta', Article::STATUS_SCHEDULED, now()->addDay());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $hidden->id => ['position' => 20, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertEmpty($report['publication_warning']['complete_with_hidden_remainder']);
    }

    /**
     * Un'inversione cronologica è SOLO un avviso: la narrazione
     * editoriale può legittimamente mettere un articolo più vecchio dopo
     * uno più recente. Non deve mai comparire tra gli errori strutturali.
     */
    public function test_chronological_inversion_is_an_editorial_advisory_not_an_error(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $newer = $this->article('Più recente ma prima nel percorso', Article::STATUS_PUBLISHED, now()->subDay());
        $older = $this->article('Più vecchio ma dopo nel percorso', Article::STATUS_PUBLISHED, now()->subWeek());
        $cluster->articles()->attach([
            $newer->id => ['position' => 10, 'is_primary' => true],
            $older->id => ['position' => 20, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['editorial_advisory']['chronological_inversions']);
        $this->assertSame([], $report['structural_error']['duplicate_position']);
        $this->assertSame([], $report['structural_error']['missing_position']);
    }

    public function test_chronologically_ordered_path_has_no_inversion(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $first = $this->article('Prima', Article::STATUS_PUBLISHED, now()->subWeek());
        $second = $this->article('Seconda', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $second->id => ['position' => 20, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertEmpty($report['editorial_advisory']['chronological_inversions']);
    }

    public function test_two_scheduled_members_out_of_date_order_is_an_editorial_advisory(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $earlierPositionLaterDate = $this->article('Prima nel percorso, dopo nel tempo', Article::STATUS_SCHEDULED, now()->addWeek());
        $laterPositionEarlierDate = $this->article('Dopo nel percorso, prima nel tempo', Article::STATUS_SCHEDULED, now()->addDay());
        $cluster->articles()->attach([
            $earlierPositionLaterDate->id => ['position' => 10, 'is_primary' => true],
            $laterPositionEarlierDate->id => ['position' => 20, 'is_primary' => false],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['editorial_advisory']['scheduled_out_of_order']);
    }

    /**
     * transition_text narra la tappa SUCCESSIVA: su una posizione
     * terminale non ha più nulla da introdurre — segnale editoriale, non
     * un blocco, perché potrebbe semplicemente non essere stato pulito
     * dopo un riordino.
     */
    public function test_transition_text_on_the_terminal_member_is_a_dangling_transition_advisory(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $first = $this->article('Prima', Article::STATUS_PUBLISHED, now()->subDay());
        $last = $this->article('Ultima', Article::STATUS_PUBLISHED, now());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true, 'transition_text' => 'Verso la prossima tappa.'],
            $last->id => ['position' => 20, 'is_primary' => false, 'transition_text' => 'Testo rimasto appeso dopo un riordino.'],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertNotEmpty($report['editorial_advisory']['dangling_transition']);
        $this->assertSame($last->id, $report['editorial_advisory']['dangling_transition'][0]['dangling_transition']['id']);
    }

    public function test_null_terminal_transition_text_is_not_flagged(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $first = $this->article('Prima', Article::STATUS_PUBLISHED, now()->subDay());
        $last = $this->article('Ultima', Article::STATUS_PUBLISHED, now());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true, 'transition_text' => 'Verso la prossima tappa.'],
            $last->id => ['position' => 20, 'is_primary' => false, 'transition_text' => null],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();

        $this->assertEmpty($report['editorial_advisory']['dangling_transition']);
    }

    public function test_a_fully_healthy_path_produces_no_findings_at_all(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $first = $this->article('Prima', Article::STATUS_PUBLISHED, now()->subDays(2));
        $second = $this->article('Seconda', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true, 'transition_text' => 'Continua.'],
            $second->id => ['position' => 20, 'is_primary' => false, 'transition_text' => null],
        ]);

        $report = app(PercorsoCoverageAuditService::class)->editorialOrderHealth();
        $row = collect($report['clusters'])->firstWhere('id', $cluster->id);

        $this->assertSame([], $row['missing_position']);
        $this->assertSame([], $row['non_positive_position']);
        $this->assertSame([], $row['duplicate_position']);
        $this->assertSame([], $row['published_beyond_gap']);
        $this->assertFalse($row['pillar_outside_reachable_prefix']);
        $this->assertFalse($row['complete_with_hidden_remainder']);
        $this->assertSame([], $row['chronological_inversions']);
        $this->assertSame([], $row['scheduled_out_of_order']);
        $this->assertNull($row['dangling_transition']);
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
