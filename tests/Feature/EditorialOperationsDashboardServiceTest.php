<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\SearchConsoleQuery;
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

        $this->assertSame('SANA', $snapshot['salute_operativa']['status']);
        $this->assertSame(0, $snapshot['salute_operativa']['open_problems_total']);
        $this->assertSame(0, $snapshot['salute_operativa']['published_articles_total']);
        $this->assertSame(0, $snapshot['salute_operativa']['active_percorsi_total']);
        $this->assertSame([], $snapshot['da_pubblicare']);
        $this->assertSame(0, $snapshot['pubblicazione_readiness']['total']);
        $this->assertSame(0, $snapshot['pubblicazione_readiness']['ready_count']);
        $this->assertSame(0, $snapshot['pubblicazione_readiness']['not_ready_count']);
        $this->assertSame([], $snapshot['da_sistemare']);
        $this->assertSame([], $snapshot['contenuti_isolati']);
        $this->assertSame([], $snapshot['contenuti_senza_concept']);
        $this->assertSame([], $snapshot['contenuti_da_aggiornare']);
        $this->assertSame([], $snapshot['programmati_non_assegnati']);
        $this->assertSame(0.0, $snapshot['content_graph']['articles']['coverage_percent']);
        $this->assertSame(0, $snapshot['content_graph']['articles']['published_total']);
        $this->assertSame(0, $snapshot['second_read']['impressions']);
        $this->assertSame(0, $snapshot['second_read']['second_reads']);
        $this->assertSame(0.0, $snapshot['second_read']['second_read_rate']);
        $this->assertFalse($snapshot['search_console']['available']);
        $this->assertFalse($snapshot['ritmo_pubblicazione']['available']);
        $this->assertSame([], $snapshot['percorsi_readiness']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['structural_error_count']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['publication_warning_count']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['editorial_advisory_count']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['published_beyond_gap_article_count']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['published_beyond_gap_cluster_count']);
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
     * Missione 28 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "upcoming publications panel" — il
     * pannello 'da_pubblicare' esiste già dalla Mission 09 (V1
     * Convergence), ma nessun test provava finora che più articoli
     * programmati appaiano in ordine cronologico (il più imminente prima),
     * proprietà su cui si basa esplicitamente il commento "Mission 37" nel
     * servizio. Verificata qui — nessuna nuova regola, solo la copertura
     * mancante sulla query già ordinata per published_at asc.
     */
    public function test_upcoming_publications_are_listed_soonest_first(): void
    {
        $furthest = $this->article('mission28-furthest', Article::STATUS_SCHEDULED, now()->addDays(5));
        $soonest = $this->article('mission28-soonest', Article::STATUS_SCHEDULED, now()->addHours(2));
        $middle = $this->article('mission28-middle', Article::STATUS_SCHEDULED, now()->addDays(2));

        $snapshot = $this->service()->snapshot();

        $this->assertSame(
            [$soonest->id, $middle->id, $furthest->id],
            collect($snapshot['da_pubblicare'])->pluck('article_id')->all()
        );
    }

    /**
     * Missione 40 (secondo batch autonomo KAIRUS, Fase E — Editorial
     * Quality & Readiness): "publication gaps" a livello di sito — riusa
     * PublicationCadenceService::summary(), mai un ricalcolo qui.
     */
    public function test_ritmo_pubblicazione_reflects_the_most_recently_published_article(): void
    {
        $this->article('mission40-oldest', Article::STATUS_PUBLISHED, now()->subDays(10));
        $this->article('mission40-newest', Article::STATUS_PUBLISHED, now()->subDays(2));

        $snapshot = $this->service()->snapshot();

        $this->assertTrue($snapshot['ritmo_pubblicazione']['available']);
        $this->assertSame(2, $snapshot['ritmo_pubblicazione']['days_since_last_publication']);
    }

    /**
     * Missione 41 (secondo batch autonomo KAIRUS, Fase E — Editorial
     * Quality & Readiness): "stale content candidates" — il dominio ha già
     * un segnale esplicito e non inventato, Article::verification_status
     * === 'needs_update' (già gestito da /admin/verifica), mai una soglia
     * di anzianità arbitraria. Il gap reale era la mancata esposizione sul
     * command center.
     */
    public function test_a_published_article_needing_an_update_is_listed_as_a_stale_content_candidate(): void
    {
        $article = $this->articleWithVerification(Article::STATUS_PUBLISHED, now()->subDay(), 'needs_update');
        $this->articleWithVerification(Article::STATUS_PUBLISHED, now()->subDays(2), 'verified');

        $snapshot = $this->service()->snapshot();

        $this->assertSame([$article->id], collect($snapshot['contenuti_da_aggiornare'])->pluck('article_id')->all());
    }

    public function test_a_draft_article_needing_an_update_is_never_listed_as_a_stale_content_candidate(): void
    {
        $this->articleWithVerification(Article::STATUS_DRAFT, null, 'needs_update');

        $snapshot = $this->service()->snapshot();

        $this->assertSame([], $snapshot['contenuti_da_aggiornare']);
    }

    private function articleWithVerification(string $status, ?\DateTimeInterface $publishedAt, string $verificationStatus): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo verifica '.uniqid(),
            'slug' => 'articolo-verifica-'.uniqid(),
            'excerpt' => '',
            'body' => '<p>Body</p>',
            'category' => 'operations-test',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 1,
            'verification_status' => $verificationStatus,
        ]));
    }

    /**
     * Missione 30 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "publication readiness summary" — un
     * articolo programmato senza alcun warning content-health/attribuzione
     * aperto (stesso insieme di article_id già usato per 'da_sistemare')
     * deve risultare 'ready' nel pannello 'da_pubblicare', mai una seconda
     * soglia di "readiness" inventata qui.
     */
    public function test_a_scheduled_article_with_no_open_issues_is_marked_ready_to_publish(): void
    {
        $cluster = ContentCluster::create(['name' => 'Percorso Readiness Ops Test', 'slug' => 'percorso-readiness-ops-test', 'is_active' => true]);
        $scheduled = Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Pronto per la pubblicazione',
            'slug' => 'pronto-per-la-pubblicazione-ops-test',
            'excerpt' => 'Sommario editoriale.',
            'body' => '<p>Corpo con <a href="/articolo/altro-articolo">collegamento interno</a>.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
            'read_minutes' => 2,
            'cover_image' => 'cover.jpg',
            'cover_alt' => 'Illustrazione.',
            'cover_credit' => 'Kairus',
            'cover_source' => 'Archivio Kairus',
            'cover_license' => 'CC BY 4.0',
            'seo_title' => 'Titolo SEO',
            'seo_description' => 'Descrizione SEO',
            'primary_sources' => 'Fonte primaria verificata',
        ]));
        $cluster->articles()->attach($scheduled->id, ['position' => 10, 'is_primary' => true]);

        $snapshot = $this->service()->snapshot();

        $row = collect($snapshot['da_pubblicare'])->firstWhere('article_id', $scheduled->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['ready']);
        $this->assertSame(1, $snapshot['pubblicazione_readiness']['total']);
        $this->assertSame(1, $snapshot['pubblicazione_readiness']['ready_count']);
        $this->assertSame(0, $snapshot['pubblicazione_readiness']['not_ready_count']);
    }

    public function test_a_scheduled_article_with_an_open_issue_is_marked_not_ready_to_publish(): void
    {
        $scheduled = $this->article('non-pronto-programmato-test', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();

        $row = collect($snapshot['da_pubblicare'])->firstWhere('article_id', $scheduled->id);
        $this->assertNotNull($row);
        $this->assertFalse($row['ready']);
        $this->assertSame(0, $snapshot['pubblicazione_readiness']['ready_count']);
        $this->assertSame(1, $snapshot['pubblicazione_readiness']['not_ready_count']);
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
     * Missione 27 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "actionable problems queue" —
     * "pubblicato senza Concept" riusa ContentGraphOrphanAuditService::
     * orphanArticles() (Missione 23, batch precedente), mai una seconda
     * implementazione della stessa regola.
     */
    public function test_a_published_article_with_no_concept_link_is_reported_without_concept(): void
    {
        $published = $this->article('senza-concept-operations-test', Article::STATUS_PUBLISHED, now()->subDay());

        $snapshot = $this->service()->snapshot();

        $this->assertSame([$published->id], collect($snapshot['contenuti_senza_concept'])->pluck('id')->all());
        $this->assertSame('DA_RIVEDERE', $snapshot['salute_operativa']['status']);
    }

    public function test_a_published_article_with_a_concept_link_is_not_reported_without_concept(): void
    {
        $published = $this->article('con-concept-operations-test', Article::STATUS_PUBLISHED, now()->subDay());
        $concept = Concept::create(['name' => 'Entropia operations test', 'slug' => 'entropia-operations-test', 'status' => 'active']);
        $published->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $snapshot = $this->service()->snapshot();

        $this->assertFalse(collect($snapshot['contenuti_senza_concept'])->pluck('id')->contains($published->id));
    }

    /**
     * Missione 32 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "Content Graph operational health" —
     * riusa ContentGraphCoverageService::summary() (Missione 19, primo
     * batch), mai un ricalcolo della percentuale di copertura qui.
     */
    public function test_content_graph_coverage_reflects_real_article_concept_links(): void
    {
        $withConcept = $this->article('con-concept-coverage-test', Article::STATUS_PUBLISHED, now()->subDay());
        $concept = Concept::create(['name' => 'Coverage operations test', 'slug' => 'coverage-operations-test', 'status' => 'active']);
        $withConcept->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'supporting', 'weight' => 50]);
        $this->article('senza-concept-coverage-test', Article::STATUS_PUBLISHED, now()->subDay());

        $snapshot = $this->service()->snapshot();

        $this->assertSame(2, $snapshot['content_graph']['articles']['published_total']);
        $this->assertSame(1, $snapshot['content_graph']['articles']['published_with_concept_link']);
        $this->assertSame(50.0, $snapshot['content_graph']['articles']['coverage_percent']);
    }

    /**
     * Missione 33 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "second-read operational health" —
     * riusa ContinuationAnalyticsService::siteWideTotals() (Missione 33),
     * mai un ricalcolo qui.
     */
    public function test_second_read_totals_reflect_real_continuation_events(): void
    {
        $source = $this->article('sorgente-second-read-ops-test', Article::STATUS_PUBLISHED, now()->subDay());
        $target = $this->article('destinazione-second-read-ops-test', Article::STATUS_PUBLISHED, now()->subDay());
        ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
        ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);

        $snapshot = $this->service()->snapshot();

        $this->assertSame(1, $snapshot['second_read']['impressions']);
        $this->assertSame(1, $snapshot['second_read']['second_reads']);
        $this->assertSame(1.0, $snapshot['second_read']['second_read_rate']);
    }

    /**
     * Missione 34 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "Search Opportunities operational
     * health" — riusa SearchConsoleFreshnessService::summary() (Missione
     * 34), mai un ricalcolo qui.
     */
    public function test_search_console_freshness_reflects_the_real_last_import(): void
    {
        SearchConsoleQuery::create([
            'query' => 'query freschezza operations test',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 10,
            'ctr' => 0.1,
            'position' => 5,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'operations-test-batch',
            'imported_at' => now()->subDays(3),
        ]);

        $snapshot = $this->service()->snapshot();

        $this->assertTrue($snapshot['search_console']['available']);
        $this->assertSame(3, $snapshot['search_console']['days_since_last_import']);
    }

    /**
     * Missione 29 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "unassigned scheduled articles" — riusa
     * PercorsoCoverageAuditService::audit()['scheduled_without_path']
     * (stessa regola già usata per published_without_path/contenuti_isolati,
     * mai una seconda implementazione qui).
     */
    public function test_a_scheduled_article_with_no_percorso_is_reported_unassigned(): void
    {
        $scheduled = $this->article('senza-percorso-programmato-test', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();

        $this->assertSame([$scheduled->id], collect($snapshot['programmati_non_assegnati'])->pluck('id')->all());
        $this->assertSame('DA_RIVEDERE', $snapshot['salute_operativa']['status']);
    }

    public function test_a_scheduled_article_belonging_to_a_percorso_is_not_reported_unassigned(): void
    {
        $scheduled = $this->article('con-percorso-programmato-test', Article::STATUS_SCHEDULED, now()->addDay());
        $cluster = ContentCluster::create(['name' => 'Percorso Programmato Ops Test', 'slug' => 'percorso-programmato-ops-test', 'is_active' => true]);
        $cluster->articles()->attach($scheduled->id, ['position' => 10, 'is_primary' => true]);

        $snapshot = $this->service()->snapshot();

        $this->assertFalse(collect($snapshot['programmati_non_assegnati'])->pluck('id')->contains($scheduled->id));
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

    // ── Mission 26 (secondo batch autonomo KAIRUS, Fase D — Editorial
    // Operations Command Center): "salute_operativa" — sintesi in un
    // colpo d'occhio, mai una nuova regola di dominio. ───────────────────

    public function test_a_content_health_problem_and_isolated_article_count_toward_open_problems(): void
    {
        // article() (helper qui sotto) crea un pubblicato senza excerpt
        // (content health WARNING -> da_sistemare) e senza alcuna
        // membership Percorso (-> contenuti_isolati): un solo articolo,
        // due problemi distinti, entrambi già calcolati altrove.
        $this->article('salute-operativa-doppio-problema', Article::STATUS_PUBLISHED, now()->subDay());

        $snapshot = $this->service()->snapshot();

        $this->assertSame('DA_RIVEDERE', $snapshot['salute_operativa']['status']);
        $this->assertGreaterThanOrEqual(2, $snapshot['salute_operativa']['open_problems_total']);
        $this->assertSame(1, $snapshot['salute_operativa']['published_articles_total']);
    }

    /**
     * Un editorial_advisory (mai bloccante per contratto, vedi il test
     * sopra) non deve mai contribuire al conteggio di "problemi aperti" —
     * altrimenti la sintesi di alto livello contraddirebbe la sezione di
     * dettaglio che lo elenca esplicitamente come "informativo, non
     * bloccante". Ricalcola l'attesa dagli stessi conteggi già esposti
     * (mai structural_error_count/publication_warning_count sommati con
     * editorial_advisory_count) invece di pretendere un fixture "pulito"
     * su ogni altro controllo di content health, che qui non è lo scopo
     * del test.
     */
    public function test_an_editorial_advisory_only_percorso_never_counts_as_an_open_problem(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Solo Advisory Salute',
            'slug' => 'percorso-solo-advisory-salute',
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
        $first = $this->article('percorso-advisory-salute-primo', Article::STATUS_PUBLISHED, now()->subDays(2));
        $last = $this->article('percorso-advisory-salute-ultimo', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Passo successivo.']);
        $cluster->articles()->attach($last->id, ['position' => 20, 'is_primary' => true, 'transition_text' => 'Testo rimasto appeso.']);
        $cluster->update(['pillar_article_id' => $first->id]);

        $snapshot = $this->service()->snapshot();
        $orderHealth = $snapshot['percorsi_order_health'];

        $this->assertGreaterThan(0, $orderHealth['editorial_advisory_count']);
        $this->assertSame(0, $orderHealth['structural_error_count']);
        $this->assertSame(0, $orderHealth['publication_warning_count']);

        $expectedOpenProblems = count($snapshot['da_sistemare'])
            + count($snapshot['contenuti_isolati'])
            + count($snapshot['contenuti_senza_concept'])
            + count($snapshot['programmati_non_assegnati'])
            + count($snapshot['percorsi_readiness'])
            + $orderHealth['structural_error_count']
            + $orderHealth['publication_warning_count']
            + count($snapshot['seo']['violations'])
            + collect($snapshot['da_pubblicare'])->where('overdue', true)->count()
            + collect($snapshot['da_pubblicare'])->where('collision', true)->count()
            + count($snapshot['contenuti_da_aggiornare']);

        $this->assertSame($expectedOpenProblems, $snapshot['salute_operativa']['open_problems_total']);
        $this->assertSame($expectedOpenProblems === 0 ? 'SANA' : 'DA_RIVEDERE', $snapshot['salute_operativa']['status']);
    }

    /**
     * Solo il flag 'overdue' (già provato da
     * test_a_scheduled_article_with_a_past_published_at_is_flagged_overdue)
     * deve contribuire il termine "in ritardo" alla somma — un articolo
     * programmato nel futuro non aggiunge quel termine, anche se genera
     * comunque un proprio warning content health (già conteggiato altrove
     * nella formula, non lo scopo di questo test).
     */
    public function test_only_the_overdue_scheduled_article_contributes_the_overdue_term(): void
    {
        $this->article('salute-operativa-in-ritardo', Article::STATUS_SCHEDULED, now()->subHour());
        $this->article('salute-operativa-futuro', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();
        $orderHealth = $snapshot['percorsi_order_health'];
        $overdueCount = collect($snapshot['da_pubblicare'])->where('overdue', true)->count();

        $this->assertSame(1, $overdueCount);
        $this->assertSame('DA_RIVEDERE', $snapshot['salute_operativa']['status']);

        $expectedOpenProblems = count($snapshot['da_sistemare'])
            + count($snapshot['contenuti_isolati'])
            + count($snapshot['contenuti_senza_concept'])
            + count($snapshot['programmati_non_assegnati'])
            + count($snapshot['percorsi_readiness'])
            + $orderHealth['structural_error_count']
            + $orderHealth['publication_warning_count']
            + count($snapshot['seo']['violations'])
            + $overdueCount
            + collect($snapshot['da_pubblicare'])->where('collision', true)->count()
            + count($snapshot['contenuti_da_aggiornare']);

        $this->assertSame($expectedOpenProblems, $snapshot['salute_operativa']['open_problems_total']);
    }

    /**
     * Missione 39 (secondo batch autonomo KAIRUS, Fase E — Editorial
     * Quality & Readiness): "scheduled publication collision detection" —
     * il form di pianificazione non impedisce a due articoli di ricevere
     * lo stesso istante esatto; PublishScheduledArticles li pubblicherebbe
     * entrambi nella stessa run in un ordine deciso solo dall'id, non da
     * una scelta editoriale.
     */
    public function test_two_scheduled_articles_sharing_the_same_instant_are_both_flagged_as_collisions(): void
    {
        $instant = now()->addDay()->setTime(9, 0, 0);
        $first = $this->article('mission39-collision-first', Article::STATUS_SCHEDULED, $instant);
        $second = $this->article('mission39-collision-second', Article::STATUS_SCHEDULED, $instant);
        $unrelated = $this->article('mission39-no-collision', Article::STATUS_SCHEDULED, now()->addDays(2));

        $snapshot = $this->service()->snapshot();
        $rows = collect($snapshot['da_pubblicare'])->keyBy('article_id');

        $this->assertTrue($rows->get($first->id)['collision']);
        $this->assertTrue($rows->get($second->id)['collision']);
        $this->assertFalse($rows->get($unrelated->id)['collision']);
        $this->assertSame(2, $snapshot['pubblicazione_readiness']['collision_count']);
    }

    public function test_a_lone_scheduled_article_at_a_unique_instant_is_never_flagged_as_a_collision(): void
    {
        $this->article('mission39-lone-scheduled', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();

        $this->assertSame(0, $snapshot['pubblicazione_readiness']['collision_count']);
        $this->assertFalse(collect($snapshot['da_pubblicare'])->first()['collision']);
    }

    // ── Mission 37 — Dashboard Priority Model V2 ─────────────────────────

    /**
     * Un articolo programmato con published_at nel passato significa che lo
     * scheduler non lo ha ancora pubblicato — un problema operativo reale,
     * distinto da "in coda per il futuro". 'overdue' rende visibile questo
     * fatto senza inventare una nuova regola di scheduling.
     */
    public function test_a_scheduled_article_with_a_past_published_at_is_flagged_overdue(): void
    {
        $overdue = $this->article('overdue-priority-test', Article::STATUS_SCHEDULED, now()->subHour());
        $future = $this->article('future-priority-test', Article::STATUS_SCHEDULED, now()->addDay());

        $snapshot = $this->service()->snapshot();

        $rows = collect($snapshot['da_pubblicare'])->keyBy('article_id');
        $this->assertTrue($rows->get($overdue->id)['overdue']);
        $this->assertFalse($rows->get($future->id)['overdue']);
    }

    /**
     * da_sistemare: un articolo con un finding "critico" (cover/summary/
     * sources per content health, external_body_images per attribuzione —
     * lo stesso identico vocabolario già usato da Radar in
     * EditorialRadarService::opportunities(), mai una seconda definizione)
     * deve comparire PRIMA di uno con soli finding non critici,
     * indipendentemente dall'ordine di creazione.
     */
    public function test_da_sistemare_sorts_high_priority_findings_before_medium(): void
    {
        // MEDIUM-only: excerpt/cover/fonti compilati (niente HIGH), ma
        // seo_title/description e cover_alt mancanti (WARNING non critici).
        $mediumOnly = Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo Medium Priority',
            'slug' => 'medium-priority-test',
            'excerpt' => 'Estratto presente.',
            'body' => '<p>Corpo.</p>',
            'category' => 'operations-test',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 1,
            'primary_sources' => 'Fonte primaria.',
            'cover_image' => 'cover.jpg',
        ]));
        // HIGH: excerpt vuoto (helper article() di default) -> 'summary' è
        // uno degli id critici.
        $high = $this->article('high-priority-test', Article::STATUS_PUBLISHED, now()->subDays(2));

        $snapshot = $this->service()->snapshot();
        $ids = collect($snapshot['da_sistemare'])->pluck('article_id')->all();

        $this->assertContains($high->id, $ids);
        $this->assertContains($mediumOnly->id, $ids);
        $this->assertLessThan(array_search($mediumOnly->id, $ids), array_search($high->id, $ids));

        $byId = collect($snapshot['da_sistemare'])->keyBy('article_id');
        $this->assertSame('HIGH', $byId->get($high->id)['priority']);
        $this->assertSame('MEDIUM', $byId->get($mediumOnly->id)['priority']);
    }

    /**
     * contenuti_isolati: più a lungo un articolo pubblicato resta senza
     * Percorso, più è urgente — quindi l'articolo pubblicato prima deve
     * comparire per primo, indipendentemente dall'ordine di creazione o
     * dall'id.
     */
    public function test_contenuti_isolati_sorts_the_oldest_published_article_first(): void
    {
        $newer = $this->article('isolato-recente', Article::STATUS_PUBLISHED, now()->subDay());
        $older = $this->article('isolato-vecchio', Article::STATUS_PUBLISHED, now()->subDays(30));

        $snapshot = $this->service()->snapshot();
        $ids = collect($snapshot['contenuti_isolati'])->pluck('id')->all();

        $this->assertSame([$older->id, $newer->id], $ids);
        $byId = collect($snapshot['contenuti_isolati'])->keyBy('id');
        $this->assertNotNull($byId->get($older->id)['published_at']);
    }

    /**
     * percorsi_readiness: NOT READY (blocca la pubblicazione) deve comparire
     * prima di READY WITH WARNINGS, indipendentemente dall'ordine curatoriale
     * di ContentCluster::ordered().
     */
    public function test_percorsi_readiness_sorts_not_ready_before_ready_with_warnings(): void
    {
        // READY WITH WARNINGS: tutti i campi ERROR-required compilati e un
        // pillar impostato (per evitare NO_PILLAR, ERROR), ma seo_title
        // mancante (WARNING).
        $withWarningsOnly = ContentCluster::create([
            'name' => 'Percorso Solo Warning',
            'slug' => 'percorso-solo-warning-priority',
            'is_active' => true,
            'short_description' => 'Breve.',
            'description' => 'Descrizione completa.',
            'seo_description' => 'SEO description.',
            'cover_image' => 'cover.jpg',
            'takeaways' => 'Takeaway.',
            'guiding_questions' => 'Domanda?',
            'closing_text' => 'Chiusura.',
            'curator_note' => 'Nota.',
        ]);
        $member = $this->article('percorso-solo-warning-membro', Article::STATUS_PUBLISHED, now()->subDay());
        $withWarningsOnly->articles()->attach($member->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);
        $withWarningsOnly->update(['pillar_article_id' => $member->id]);

        // NOT READY: short_description/description mancanti -> ERROR.
        $notReady = ContentCluster::create([
            'name' => 'Percorso Not Ready Priority',
            'slug' => 'percorso-not-ready-priority',
            'is_active' => true,
        ]);

        $snapshot = $this->service()->snapshot();
        $ids = collect($snapshot['percorsi_readiness'])->pluck('cluster_id')->all();

        $this->assertContains($notReady->id, $ids);
        $this->assertContains($withWarningsOnly->id, $ids);
        $this->assertLessThan(
            array_search($withWarningsOnly->id, $ids),
            array_search($notReady->id, $ids)
        );

        $byId = collect($snapshot['percorsi_readiness'])->keyBy('cluster_id');
        $this->assertSame('NOT READY', $byId->get($notReady->id)['status']);
        $this->assertSame('READY WITH WARNINGS', $byId->get($withWarningsOnly->id)['status']);
    }

    /**
     * SEO: la dashboard deve restare scoped a pubblicato+programmato come
     * ogni altra sezione (stesso confine di
     * test_draft_articles_are_not_exposed_in_operational_sections) —
     * SeoMetadataQualityAuditService analizza TUTTI gli articoli (bozze
     * incluse), ma la lista 'violations' non deve mai far trapelare una
     * bozza in questa card.
     */
    public function test_seo_violations_are_scoped_to_published_and_scheduled_only(): void
    {
        $draft = Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Bozza con canonical rotto',
            'slug' => 'bozza-canonical-rotto',
            'excerpt' => 'Estratto.',
            'body' => '<p>Corpo.</p>',
            'category' => 'operations-test',
            'status' => Article::STATUS_DRAFT,
            'canonical_url' => 'not a valid url',
            'read_minutes' => 1,
        ]));
        $published = Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Pubblicato con canonical rotto',
            'slug' => 'pubblicato-canonical-rotto',
            'excerpt' => 'Estratto.',
            'body' => '<p>Corpo.</p>',
            'category' => 'operations-test',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'canonical_url' => 'not a valid url',
            'read_minutes' => 1,
        ]));

        $snapshot = $this->service()->snapshot();
        $ids = collect($snapshot['seo']['violations'])->pluck('article_id')->all();

        $this->assertContains($published->id, $ids);
        $this->assertNotContains($draft->id, $ids);

        $row = collect($snapshot['seo']['violations'])->firstWhere('article_id', $published->id);
        $this->assertSame('HIGH', $row['priority']);
        $this->assertNotEmpty($row['reasons']);
    }

    // ── Mission 38 — Dashboard "Why?" Explanations ───────────────────────

    /**
     * da_sistemare non deve mai mostrare un badge HIGH/MEDIUM senza un
     * motivo accanto — reason_summary riusa le label/reason già scritte
     * dai servizi sottostanti, mai una frase inventata qui.
     */
    public function test_da_sistemare_exposes_a_concrete_reason_summary_next_to_the_priority_badge(): void
    {
        $article = $this->article('why-priority-test', Article::STATUS_PUBLISHED, now()->subDay());

        $snapshot = $this->service()->snapshot();

        $row = collect($snapshot['da_sistemare'])->firstWhere('article_id', $article->id);
        $this->assertNotNull($row);
        $this->assertNotEmpty($row['reason_summary']);
        // Nessun placeholder anonimo: ogni riga deve corrispondere a una
        // label reale già usata altrove (Sommario è tra i finding di
        // ArticleContentHealthService, l'articolo helper ha excerpt vuoto).
        $this->assertContains('Sommario', $row['reason_summary']);
    }

    /**
     * Sequenza Percorsi: ogni cluster in clusters_with_issues deve esporre
     * quale/i codice/i strutturale/i lo hanno fatto comparire — stesso
     * pattern già usato da percorsi_readiness['codes'], mai un nome di
     * Percorso senza spiegazione.
     */
    public function test_clusters_with_issues_expose_which_structural_codes_triggered_them(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Why Test',
            'slug' => 'percorso-why-test',
            'is_active' => true,
        ]);
        $article = $this->article('percorso-why-membro', Article::STATUS_PUBLISHED, now()->subDay());
        // position=0 non positiva -> NON_POSITIVE_POSITION (structural_error).
        DB::table('article_content_cluster')->insert([
            'content_cluster_id' => $cluster->id,
            'article_id' => $article->id,
            'position' => 0,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = $this->service()->snapshot();

        $row = collect($snapshot['percorsi_order_health']['clusters_with_issues'])->firstWhere('cluster_id', $cluster->id);
        $this->assertNotNull($row);
        $this->assertContains('NON_POSITIVE_POSITION', $row['codes']);
    }

    /**
     * Missione 21 (secondo batch autonomo KAIRUS, Fase C — Percorsi
     * Advanced Operations): "publication gap dashboard". PUBLISHED_BEYOND_GAP
     * era già calcolato e già uno dei codici possibili in clusters_with_issues
     * (vedi PercorsoCoverageAuditService::orderHealthRow()), ma non era mai
     * stato provato end-to-end sulla dashboard reale, e non esisteva un
     * conteggio dedicato di QUANTI articoli restano invisibili per questo
     * motivo — solo un codice tra tanti in un elenco misto. Riproduce
     * l'incidente concettuale: una tappa non pubblicata in posizione 1
     * blocca il prefisso pubblico, lasciando una tappa già PUBLISHED in
     * posizione 2 irraggiungibile.
     */
    public function test_a_published_article_stuck_behind_a_gap_is_counted_in_the_publication_gap_summary(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Con Gap',
            'slug' => 'percorso-con-gap',
            'is_active' => true,
        ]);
        $draftGate = $this->article('percorso-gap-cancello', Article::STATUS_DRAFT, null);
        $publishedBehindGap = $this->article('percorso-gap-bloccato', Article::STATUS_PUBLISHED, now()->subDay());
        DB::table('article_content_cluster')->insert([
            ['content_cluster_id' => $cluster->id, 'article_id' => $draftGate->id, 'position' => 10, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
            ['content_cluster_id' => $cluster->id, 'article_id' => $publishedBehindGap->id, 'position' => 20, 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $snapshot = $this->service()->snapshot();

        $this->assertSame(1, $snapshot['percorsi_order_health']['published_beyond_gap_article_count']);
        $this->assertSame(1, $snapshot['percorsi_order_health']['published_beyond_gap_cluster_count']);
        $row = collect($snapshot['percorsi_order_health']['clusters_with_issues'])->firstWhere('cluster_id', $cluster->id);
        $this->assertNotNull($row);
        $this->assertContains('PUBLISHED_BEYOND_GAP', $row['codes']);
    }

    public function test_a_percorso_with_no_gap_does_not_count_toward_the_publication_gap_summary(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Percorso Senza Gap',
            'slug' => 'percorso-senza-gap',
            'is_active' => true,
        ]);
        $published = $this->article('percorso-senza-gap-membro', Article::STATUS_PUBLISHED, now()->subDay());
        DB::table('article_content_cluster')->insert([
            'content_cluster_id' => $cluster->id,
            'article_id' => $published->id,
            'position' => 10,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = $this->service()->snapshot();

        $this->assertSame(0, $snapshot['percorsi_order_health']['published_beyond_gap_article_count']);
        $this->assertSame(0, $snapshot['percorsi_order_health']['published_beyond_gap_cluster_count']);
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

    /**
     * Mission 39 — Dashboard Query/Performance Audit. Profilato manualmente
     * a 5/25/50 articoli (nessun Percorso): il conteggio query resta
     * PIATTO in ogni caso — nessun N+1 legato agli Articoli esiste in
     * questa dashboard, a differenza dei Percorsi (vedi il test sopra, che
     * già documenta e accetta quella crescita lineare separatamente). Ogni
     * chiamata per-articolo qui dentro (content health, attribuzione)
     * opera su dati già in memoria — ArticleContentHealthService e
     * SourceImageAttributionHealthService non emettono query proprie per
     * articolo, solo letture di attributi già caricati dalla singola query
     * Article::query() eseguita una volta in snapshot(). Questo test
     * cristallizza quella prova: un numero di query identico a 5 e 50
     * articoli, non solo "sotto un tetto" come per i Percorsi.
     */
    public function test_query_count_does_not_grow_with_article_count(): void
    {
        $countQueriesFor = function (int $n): int {
            Article::query()->delete();
            for ($i = 0; $i < $n; $i++) {
                $this->article('query-scale-'.$i, Article::STATUS_PUBLISHED, now()->subDays($i + 1));
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->service()->snapshot();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = $countQueriesFor(5);
        $large = $countQueriesFor(50);

        $this->assertSame(
            $small,
            $large,
            'Il conteggio query della dashboard non deve dipendere dal numero di articoli (nessun N+1).'
        );
    }
}
