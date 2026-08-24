<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\EditorialOperations\EditorialOperationsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mission 09 — Editorial Operations Dashboard V1 Convergence.
 *
 * Recuperato da PR #294 (foundation isolata, mai su main) e convergente sui
 * due servizi Percorsi più recenti — Mission 02 (PercorsoPublicationReadinessService)
 * e Mission 04 (PercorsoCoverageAuditService::editorialOrderHealth()) —
 * entrambi assenti alla base di #294. Ogni sezione qui testata verifica solo
 * l'AGGREGAZIONE, mai una regola di dominio già coperta dai test dei
 * servizi sottostanti (PercorsoPublicationReadinessServiceTest,
 * PercorsoEditorialOrderHealthTest, ecc.).
 */
class EditorialOperationsDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EditorialOperationsDashboardService
    {
        return app(EditorialOperationsDashboardService::class);
    }

    private function author(): User
    {
        return User::factory()->create();
    }

    private function article(string $slug, string $status, ?\DateTimeInterface $publishedAt = null): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->author()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => '',
            'body' => '<p>Body</p>',
            'category' => 'operations-test',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 1,
        ]));
    }

    public function test_empty_state_reports_zero_everywhere_without_error(): void
    {
        $snapshot = $this->service()->snapshot();

        $this->assertSame([], $snapshot['da_pubblicare']);
        $this->assertSame([], $snapshot['da_sistemare']);
        $this->assertSame([], $snapshot['contenuti_isolati']);
        $this->assertSame([], $snapshot['percorsi_readiness']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['structural_error_count']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['publication_warning_count']);
        $this->assertFalse($snapshot['opportunita']['available']);
        $this->assertFalse($snapshot['distribuzione']['available']);
    }

    public function test_snapshot_aggregates_real_sources_and_marks_unavailable_sections_explicitly(): void
    {
        $this->article('scheduled-operations-test', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();

        $this->assertCount(1, $snapshot['da_pubblicare']);
        $this->assertArrayHasKey('summary', $snapshot['seo']);
        $this->assertFalse($snapshot['opportunita']['available']);
        $this->assertFalse($snapshot['distribuzione']['available']);
    }

    public function test_draft_articles_are_not_exposed_in_operational_sections(): void
    {
        $draft = $this->article('draft-operations-test', Article::STATUS_DRAFT);

        $snapshot = $this->service()->snapshot();
        $ids = collect($snapshot['da_pubblicare'])->pluck('article_id')
            ->merge(collect($snapshot['da_sistemare'])->pluck('article_id'));

        $this->assertFalse($ids->contains($draft->id));
    }

    public function test_a_published_article_with_no_percorso_membership_is_reported_isolated(): void
    {
        $published = $this->article('isolated-operations-test', Article::STATUS_PUBLISHED, now()->subDay());

        $snapshot = $this->service()->snapshot();

        $this->assertSame([$published->id], collect($snapshot['contenuti_isolati'])->pluck('id')->all());
    }

    public function test_a_published_article_belonging_to_a_percorso_is_not_reported_isolated(): void
    {
        $published = $this->article('member-operations-test', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = ContentCluster::create(['name' => 'Percorso Ops Test', 'slug' => 'percorso-ops-test', 'is_active' => true]);
        $cluster->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);

        $snapshot = $this->service()->snapshot();

        $this->assertFalse(collect($snapshot['contenuti_isolati'])->pluck('id')->contains($published->id));
    }

    /**
     * Mixed-warnings: un Percorso con readiness reale (Mission 02) E con
     * un'anomalia di sequenza reale (Mission 04) deve apparire in
     * ENTRAMBE le sezioni — non sono la stessa cosa, e la dashboard non
     * deve appiattirle in un'unica lista.
     */
    public function test_a_percorso_with_both_readiness_findings_and_order_health_issues_appears_in_both_sections(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Misto Test',
            'slug' => 'percorso-misto-test',
            'is_active' => true,
            // Nome/slug presenti ma short_description/description mancanti:
            // basta a generare almeno un finding WARNING in
            // PercorsoPublicationReadinessService::evaluate() (campi
            // editoriali mancanti), senza dover replicare qui l'elenco
            // completo dei suoi controlli.
        ]);
        $article = $this->article('percorso-misto-membro', Article::STATUS_PUBLISHED, now()->subDay());
        // position=0 (non positiva) fa scattare structural_error/non_positive_position
        // in editorialOrderHealth() — vedi PercorsoEditorialOrderHealthTest per
        // la prova diretta di questa regola, qui riusata solo per popolare il caso misto.
        DB::table('article_content_cluster')->insert([
            'content_cluster_id' => $cluster->id,
            'article_id' => $article->id,
            'position' => 0,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = $this->service()->snapshot();

        $this->assertTrue(collect($snapshot['percorsi_readiness'])->pluck('cluster_id')->contains($cluster->id));
        $this->assertTrue(collect($snapshot['percorsi_order_health']['clusters_with_issues'])->pluck('cluster_id')->contains($cluster->id));
    }

    public function test_a_fully_ready_percorso_with_no_order_issues_appears_in_neither_section(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Completo Test',
            'slug' => 'percorso-completo-test',
            'is_active' => true,
            'short_description' => 'Breve.',
            'description' => 'Descrizione completa.',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description.',
            'cover_image' => 'cover.jpg',
            'takeaways' => 'Takeaway.',
            'guiding_questions' => 'Domanda?',
            'closing_text' => 'Chiusura.',
            'curator_note' => 'Nota.',
        ]);
        $first = $this->article('percorso-completo-primo', Article::STATUS_PUBLISHED, now()->subDays(2));
        $second = $this->article('percorso-completo-secondo', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Passo successivo.']);
        $cluster->articles()->attach($second->id, ['position' => 20, 'is_primary' => true]);
        // Un pillar mancante genera NO_PILLAR (ContentClusterHealth), un
        // finding ERROR per PercorsoPublicationReadinessService: qui
        // impostato esplicitamente per ottenere davvero uno stato READY,
        // stesso fixture-pattern già usato in
        // PercorsoPublicationReadinessServiceTest.
        $cluster->update(['pillar_article_id' => $first->id]);

        $snapshot = $this->service()->snapshot();

        $this->assertFalse(collect($snapshot['percorsi_readiness'])->pluck('cluster_id')->contains($cluster->id));
        $this->assertFalse(collect($snapshot['percorsi_order_health']['clusters_with_issues'])->pluck('cluster_id')->contains($cluster->id));
    }

    /**
     * Query budget: il numero totale di query non deve esplodere in modo
     * incontrollato al crescere del numero di Percorsi. La dashboard
     * accetta (e documenta, vedi EditorialOperationsDashboardService)
     * una crescita lineare sui Percorsi — un catalogo editoriale curato a
     * mano, mai nell'ordine di migliaia come gli Articoli — quindi qui si
     * verifica un tetto assoluto ragionevole a un N piccolo ma realistico,
     * non una falsa uguaglianza O(1) che non rispecchierebbe il reale
     * comportamento di PercorsoPublicationReadinessService::evaluate().
     */
    public function test_query_count_stays_within_a_reasonable_ceiling_for_a_realistic_number_of_percorsi(): void
    {
        $article = $this->article('query-budget-operations', Article::STATUS_PUBLISHED, now()->subDay());

        foreach (range(1, 6) as $i) {
            $cluster = ContentCluster::create(['name' => 'Query Percorso Ops '.$i, 'slug' => 'query-percorso-ops-'.$i, 'is_active' => true]);
            $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->snapshot();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(120, $queryCount, 'La dashboard con 6 Percorsi non deve superare un tetto ragionevole di query totali.');
    }
}
