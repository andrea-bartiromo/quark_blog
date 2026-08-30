<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\EditorialOperations\EditorialOperationsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EditorialOperationsScheduledWaitClassificationTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $this->editor = User::factory()->create(['role' => 'editor']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_future_scheduled_gap_is_informative_and_keeps_both_canonical_codes(): void
    {
        $first = $this->article('prima-pubblica', Article::STATUS_PUBLISHED, now()->subDays(3));
        $blocker = $this->article('attesa-futura', Article::STATUS_SCHEDULED, now()->addDays(2));
        $beyond = $this->article('pubblicata-oltre-gap', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = $this->cluster('Gap futuro', $first, [$first, $blocker, $beyond]);

        $snapshot = $this->snapshot();
        $wait = $this->row($snapshot['percorsi_operativi']['scheduled_waits'], $cluster);

        $this->assertContains('PUBLIC_SEQUENCE_BLOCKED', $wait['informative_codes']);
        $this->assertContains('PUBLISHED_BEYOND_GAP', $wait['informative_codes']);
        $this->assertSame($blocker->id, $wait['blocking_article']['id']);
        $this->assertSame($blocker->published_at->toISOString(), $wait['expected_at']);
        $this->assertSame([], $snapshot['percorsi_operativi']['actionable']);
        $this->assertNotEmpty($snapshot['percorsi_order_health']['clusters_with_issues']);
    }

    public function test_future_scheduled_pillar_is_an_informative_wait(): void
    {
        $first = $this->article('introduzione-pubblica', Article::STATUS_PUBLISHED, now()->subDay());
        $pillar = $this->article('pillar-futuro', Article::STATUS_SCHEDULED, now()->addDay());
        $cluster = $this->cluster('Pillar futuro', $pillar, [$first, $pillar]);

        $snapshot = $this->snapshot();
        $wait = $this->row($snapshot['percorsi_operativi']['scheduled_waits'], $cluster);

        $this->assertContains('HEALTH_PILLAR_NOT_PUBLIC', $wait['informative_codes']);
        $this->assertContains('PILLAR_OUTSIDE_PUBLIC_PREFIX', $wait['informative_codes']);
        $this->assertSame($pillar->id, $wait['blocking_article']['id']);
        $this->assertSame([], $snapshot['percorsi_operativi']['actionable']);
        $this->assertSame(0, $snapshot['percorsi_operativi']['actionable_pillar_count']);
        $this->assertNotEmpty($snapshot['percorsi_pillar_issues']);
    }

    public function test_valid_narrative_inversion_is_informative_without_reordering_members(): void
    {
        $narrativeFirst = $this->article('apertura-narrativa', Article::STATUS_PUBLISHED, now()->subDay());
        $olderSecond = $this->article('antefatto', Article::STATUS_PUBLISHED, now()->subDays(10));
        $cluster = $this->cluster('Ordine narrativo', $narrativeFirst, [$narrativeFirst, $olderSecond]);

        $before = $cluster->articles()->pluck('article_content_cluster.position', 'articles.id')->all();
        $snapshot = $this->snapshot();
        $wait = $this->row($snapshot['percorsi_operativi']['scheduled_waits'], $cluster);

        $this->assertContains('CHRONOLOGICAL_INVERSIONS', $wait['informative_codes']);
        $this->assertSame([], $snapshot['percorsi_operativi']['actionable']);
        $this->assertSame($before, $cluster->fresh()->articles()->pluck('article_content_cluster.position', 'articles.id')->all());
    }

    public function test_expired_scheduled_date_remains_actionable(): void
    {
        $first = $this->article('prima-scaduta', Article::STATUS_PUBLISHED, now()->subDays(4));
        $overdue = $this->article('programmato-scaduto', Article::STATUS_SCHEDULED, now()->subDay());
        $beyond = $this->article('oltre-scaduto', Article::STATUS_PUBLISHED, now()->subHours(2));
        $cluster = $this->cluster('Programmazione scaduta', $first, [$first, $overdue, $beyond]);

        $snapshot = $this->snapshot();
        $actionable = $this->row($snapshot['percorsi_operativi']['actionable'], $cluster);

        $this->assertContains('PUBLIC_SEQUENCE_BLOCKED', $actionable['actionable_codes']);
        $this->assertContains('PUBLISHED_BEYOND_GAP', $actionable['actionable_codes']);
        $this->assertNotContains('PUBLIC_SEQUENCE_BLOCKED', $actionable['informative_codes']);
    }

    public function test_real_structural_gap_remains_actionable(): void
    {
        $first = $this->article('prima-gap-reale', Article::STATUS_PUBLISHED, now()->subDays(3));
        $draft = $this->article('bozza-gap-reale', Article::STATUS_DRAFT);
        $beyond = $this->article('oltre-gap-reale', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = $this->cluster('Gap strutturale', $first, [$first, $draft, $beyond]);

        $snapshot = $this->snapshot();
        $actionable = $this->row($snapshot['percorsi_operativi']['actionable'], $cluster);

        $this->assertContains('NON_PUBLISHABLE_MEMBERS', $actionable['actionable_codes']);
        $this->assertContains('PUBLISHED_BEYOND_GAP', $actionable['actionable_codes']);
        $this->assertNotContains('PUBLISHED_BEYOND_GAP', $actionable['informative_codes']);
    }

    public function test_headline_counts_only_actionable_sections(): void
    {
        $first = $this->article('headline-pubblico', Article::STATUS_PUBLISHED, now()->subDay());
        $future = $this->article('headline-futuro', Article::STATUS_SCHEDULED, now()->addDay());
        $this->cluster('Headline attesa', $first, [$first, $future]);

        $snapshot = $this->snapshot();

        $this->assertNotEmpty($snapshot['percorsi_operativi']['scheduled_waits']);
        $this->assertSame([], $snapshot['percorsi_operativi']['actionable']);
        $this->assertSame(0, $snapshot['percorsi_operativi']['actionable_readiness_count']);
        $this->assertSame(0, $snapshot['percorsi_operativi']['actionable_order_count']);
        $this->assertSame(0, $snapshot['percorsi_operativi']['actionable_pillar_count']);

        $expectedHeadline = count($snapshot['da_sistemare'])
            + count($snapshot['contenuti_isolati'])
            + count($snapshot['contenuti_senza_concept'])
            + count($snapshot['programmati_non_assegnati'])
            + count($snapshot['seo']['violations'])
            + collect($snapshot['da_pubblicare'])->where('overdue', true)->count()
            + $snapshot['pubblicazione_readiness']['collision_count']
            + count($snapshot['contenuti_da_aggiornare'])
            + count($snapshot['percorsi_non_publishable_members'])
            + $snapshot['content_graph_actionable']['total'];

        $this->assertSame($expectedHeadline, $snapshot['salute_operativa']['open_problems_total']);
    }

    public function test_dashboard_renders_actionable_and_informative_sections_separately(): void
    {
        $first = $this->article('render-pubblico', Article::STATUS_PUBLISHED, now()->subDay());
        $future = $this->article('Render articolo futuro', Article::STATUS_SCHEDULED, now()->addDay());
        $this->cluster('Render attesa programmata', $first, [$first, $future]);

        $response = $this->actingAs($this->editor)->get(route('admin.editorial-operations'));

        $response->assertOk()
            ->assertSee('Problemi Percorsi actionable')
            ->assertSee('Nessun Percorso richiede una correzione immediata.')
            ->assertSee('Attese programmate dei Percorsi')
            ->assertSee('PUBLIC_SEQUENCE_BLOCKED')
            ->assertSee('Render articolo futuro')
            ->assertSee('previsto il');
    }

    private function snapshot(): array
    {
        return app(EditorialOperationsDashboardService::class)->snapshot();
    }

    private function article(string $title, string $status, ?\DateTimeInterface $publishedAt = null): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->editor->id,
            'title' => ucfirst(str_replace('-', ' ', $title)),
            'slug' => str($title)->slug()->append('-', uniqid())->toString(),
            'excerpt' => 'Estratto completo per il test.',
            'body' => '<p>Corpo completo per il test editoriale.</p>',
            'category' => 'scienza',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 3,
        ]));
    }

    /** @param list<Article> $articles */
    private function cluster(string $name, Article $pillar, array $articles): ContentCluster
    {
        $cluster = ContentCluster::create([
            'name' => $name,
            'slug' => str($name)->slug()->append('-', uniqid())->toString(),
            'short_description' => 'Descrizione breve completa.',
            'description' => 'Descrizione completa del Percorso.',
            'cover_image' => 'percorsi/test.jpg',
            'seo_title' => 'Titolo SEO del Percorso',
            'seo_description' => 'Descrizione SEO completa del Percorso.',
            'pillar_article_id' => $pillar->id,
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
            'takeaways' => ['Un takeaway'],
            'guiding_questions' => ['Una domanda guida?'],
            'closing_text' => 'Chiusura completa.',
            'curator_note' => 'Nota del curatore.',
        ]);

        foreach ($articles as $index => $article) {
            $cluster->articles()->attach($article->id, [
                'position' => ($index + 1) * 10,
                'is_primary' => true,
                'transition_text' => $index < count($articles) - 1 ? 'Prosegui con la tappa successiva.' : null,
            ]);
        }

        return $cluster;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function row(array $rows, ContentCluster $cluster): array
    {
        $row = collect($rows)->firstWhere('cluster_id', $cluster->id);

        $this->assertNotNull($row, "Nessuna classificazione trovata per il Percorso {$cluster->name}.");

        return $row;
    }
}
