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
        $this->assertSame(0, $snapshot['percorsi_order_health']['editorial_advisory_count']);
        $this->assertSame([], $snapshot['percorsi_order_health']['clusters_with_advisories_only']);
        $this->assertTrue($snapshot['opportunita']['available']);
        $this->assertSame(0, $snapshot['opportunita']['total']);
        $this->assertSame([], $snapshot['opportunita']['items']);
        $this->assertFalse($snapshot['distribuzione']['available']);
    }

    /**
     * Mission 36 — Dashboard Social Distribution Integration.
     * UtmLinkGenerator (Growth S3) is already on main, but it is
     * deliberately stateless (docs/SOCIAL_DISTRIBUTION.md): no campaign or
     * click is ever persisted, so there is no real aggregate data to
     * summarize. The correct outcome is to keep 'available' false rather
     * than fabricate a metric — but the reason must describe the actual
     * state (tool exists, stateless by design) rather than the stale
     * "not yet on main" message, and a direct link to the real tool must
     * still reach the editor.
     */
    public function test_distribuzione_stays_unavailable_but_links_to_the_real_stateless_tool(): void
    {
        $snapshot = $this->service()->snapshot();

        $this->assertFalse($snapshot['distribuzione']['available']);
        $this->assertStringNotContainsString('non è ancora su main', $snapshot['distribuzione']['reason']);
        $this->assertSame(route('admin.social-distribution'), $snapshot['distribuzione']['tool_url']);
    }

    public function test_snapshot_aggregates_real_sources_and_marks_unavailable_sections_explicitly(): void
    {
        $this->article('scheduled-operations-test', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();

        $this->assertCount(1, $snapshot['da_pubblicare']);
        $this->assertArrayHasKey('summary', $snapshot['seo']);
        // Mission 35: Radar is real on main. A merely-scheduled article (not
        // yet published) must never surface as an opportunity — Radar only
        // evaluates Article::published(), same public-safety boundary as
        // every other section here.
        $this->assertTrue($snapshot['opportunita']['available']);
        $this->assertSame(0, $snapshot['opportunita']['total']);
        $this->assertFalse($snapshot['distribuzione']['available']);
    }

    /**
     * Mission 35 — Dashboard Radar Opportunities Integration. Recovers the
     * tested-but-never-merged foundation from PR #292
     * (EditorialRadarProviderGraphService) and wires it into the
     * previously-stubbed 'opportunita' slot. Only the AGGREGATION is tested
     * here — every domain rule (which findings become which opportunity
     * type/priority) is already covered by EditorialRadarServiceTest and
     * SearchConsoleRadarProviderTest.
     */
    public function test_a_published_article_content_health_warning_surfaces_as_an_opportunity(): void
    {
        $published = $this->article('opportunita-content-health-test', Article::STATUS_PUBLISHED, now()->subDay());
        // No excerpt was set (see article() helper), which is exactly the
        // 'summary' WARNING finding ArticleContentHealthService already
        // covers — reused here, never reimplemented.

        $snapshot = $this->service()->snapshot();

        $this->assertTrue($snapshot['opportunita']['available']);
        $this->assertGreaterThan(0, $snapshot['opportunita']['total']);
        $this->assertTrue(collect($snapshot['opportunita']['items'])->pluck('article_id')->contains($published->id));
    }

    /**
     * Public-safety boundary: a scheduled (not-yet-public) article's content
     * gaps must never leak into the Opportunità card, even though the same
     * article already appears in da_pubblicare. EditorialRadarService only
     * ever queries Article::published(), so this proves the dashboard
     * inherits that boundary rather than accidentally widening it.
     */
    public function test_a_scheduled_articles_content_gaps_never_surface_as_an_opportunity(): void
    {
        $scheduled = $this->article('opportunita-scheduled-safety-test', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();

        $this->assertFalse(collect($snapshot['opportunita']['items'])->pluck('article_id')->contains($scheduled->id));
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

        // Mission 15: the readiness row must also expose WHICH codes are
        // driving it — never just an anonymous count — and INFO-only codes
        // (SCHEDULING_NOT_AVAILABLE, always present since no publicationAt
        // is ever passed here) must never appear in that visible list.
        $readinessRow = collect($snapshot['percorsi_readiness'])->firstWhere('cluster_id', $cluster->id);
        $this->assertNotEmpty($readinessRow['codes']);
        $this->assertNotContains('SCHEDULING_NOT_AVAILABLE', $readinessRow['codes']);
    }

    /**
     * Mission 15 — Dashboard Integration. Mission 14 wired the SAME
     * underlying signal (complete_with_hidden_remainder) into both
     * readiness (ORDER_HEALTH_COMPLETE_WITH_HIDDEN_REMAINDER) and
     * order-health (clusters_with_issues) — unlike the mixed-cause test
     * above, this is genuinely one root cause appearing in two sections,
     * and the dashboard must say so via also_in_order_health rather than
     * presenting it as two disconnected problems.
     */
    public function test_shared_hidden_remainder_cause_is_flagged_as_also_in_order_health(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Concluso Con Resto Nascosto',
            'slug' => 'percorso-concluso-resto-nascosto',
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
            'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE,
        ]);
        $first = $this->article('resto-nascosto-primo', Article::STATUS_PUBLISHED, now()->subDays(2));
        $gap = $this->article('resto-nascosto-gap', Article::STATUS_SCHEDULED, now()->addDays(3));
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Continua.']);
        $cluster->articles()->attach($gap->id, ['position' => 20, 'is_primary' => true]);
        $cluster->update(['pillar_article_id' => $first->id]);

        $snapshot = $this->service()->snapshot();

        $readinessRow = collect($snapshot['percorsi_readiness'])->firstWhere('cluster_id', $cluster->id);
        $this->assertNotNull($readinessRow);
        $this->assertContains('ORDER_HEALTH_COMPLETE_WITH_HIDDEN_REMAINDER', $readinessRow['codes']);
        $this->assertTrue($readinessRow['also_in_order_health']);
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
     * Mission 11 — Editorial Order Health V2. editorial_advisory findings
     * (e.g. dangling_transition) are never blocking by design
     * (PercorsoCoverageAuditService::editorialOrderHealth() docblock), so
     * the dashboard must surface them separately from clusters_with_issues
     * — never merged in, never silently dropped either (the gap this
     * mission closes: Mission 09's orderHealthSummary() only ever summed
     * structural_error/publication_warning, discarding editorial_advisory
     * entirely).
     */
    public function test_a_percorso_with_only_an_editorial_advisory_is_listed_separately_from_blocking_issues(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Solo Advisory',
            'slug' => 'percorso-solo-advisory',
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
        $first = $this->article('percorso-advisory-primo', Article::STATUS_PUBLISHED, now()->subDays(2));
        $last = $this->article('percorso-advisory-ultimo', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Passo successivo.']);
        // transition_text non nullo sull'ultima posizione: nessuna tappa
        // successiva a cui introdurre, quindi dangling_transition
        // (editorial_advisory, mai bloccante).
        $cluster->articles()->attach($last->id, ['position' => 20, 'is_primary' => true, 'transition_text' => 'Testo rimasto appeso.']);
        $cluster->update(['pillar_article_id' => $first->id]);

        $snapshot = $this->service()->snapshot();

        $this->assertFalse(collect($snapshot['percorsi_readiness'])->pluck('cluster_id')->contains($cluster->id));
        $this->assertFalse(collect($snapshot['percorsi_order_health']['clusters_with_issues'])->pluck('cluster_id')->contains($cluster->id));
        $this->assertTrue(collect($snapshot['percorsi_order_health']['clusters_with_advisories_only'])->pluck('cluster_id')->contains($cluster->id));
        $this->assertGreaterThan(0, $snapshot['percorsi_order_health']['editorial_advisory_count']);
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
