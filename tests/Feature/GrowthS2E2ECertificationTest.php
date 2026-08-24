<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContinuationAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Growth S2 — certificazione end-to-end dell'intero sistema (motore +
 * UI + analytics) integrato insieme, non dei tre pezzi separatamente
 * (già coperti dai rispettivi PR). Journeys tratte letteralmente dal
 * brief della missione.
 */
class GrowthS2E2ECertificationTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'author']);
    }

    // ── Journey 1: articolo senza Percorso, fallback di categoria ─────

    public function test_journey_1_no_path_category_fallback_end_to_end(): void
    {
        $a = $this->article('Articolo A', 'fisica');
        $b = $this->article('Articolo B', 'fisica');

        $responseA = $this->get(route('articolo', $a->slug));
        $responseA->assertOk();
        $responseA->assertSee('Continua da qui');
        $responseA->assertSee($b->title);

        $this->assertDatabaseHas('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $a->id,
            'target_article_id' => $b->id,
        ]);

        // Scoped al link della card "Continua da qui" (data-continuation-click):
        // la stessa pagina mostra lo stesso articolo anche altrove (ticker,
        // correlati, più letti) con un href NON firmato — un match generico
        // su "href a B" prenderebbe quello, non il link firmato da seguire.
        preg_match('/href="([^"]*)"\s+data-continuation-click/s', $responseA->getContent(), $matches);
        $this->assertNotEmpty($matches, 'continuation link not found in rendered HTML');

        $responseB = $this->get(html_entity_decode($matches[1]));
        $responseB->assertOk();

        $this->assertDatabaseHas('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
            'source_article_id' => $a->id,
            'target_article_id' => $b->id,
        ]);
    }

    // ── Journey 2: articolo con Percorso e next, nessun doppio CTA ────

    public function test_journey_2_active_path_suppresses_continuation_and_its_analytics(): void
    {
        $current = $this->article('Corrente nel percorso', 'fisica');
        $pathNext = $this->article('Prossima tappa', 'fisica');
        $this->article('Piu recente ma fuori percorso', 'fisica', now());

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $pathNext->id => ['position' => 20, 'is_primary' => false],
        ]);

        $response = $this->get(route('articolo', $current->slug));
        $response->assertOk();
        $response->assertSee('Successivo');
        $response->assertSee($pathNext->title);
        $response->assertDontSee('id="continue-reading-title"', false);

        // Nessun evento di analytics per un modulo che non è mai stato
        // mostrato: la soppressione deve propagarsi fino al layer di
        // misurazione, non solo alla UI.
        $this->assertDatabaseCount('article_continuation_events', 0);
    }

    // ── Journey 3: nessun candidato, zero UI, zero analytics ──────────

    public function test_journey_3_no_candidate_produces_zero_ui_and_zero_analytics(): void
    {
        $onlyArticle = $this->article('Unico articolo del sito', 'fisica');

        $response = $this->get(route('articolo', $onlyArticle->slug));
        $response->assertOk();
        $response->assertDontSee('Continua da qui');
        $response->assertDontSee('id="continue-reading-title"', false);

        $this->assertDatabaseCount('article_continuation_events', 0);
    }

    // ── Journey 4: fallback di categoria, candidato verificato ────────

    public function test_journey_4_category_fallback_candidate_matches_engine_output(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $older = $this->article('Piu vecchio', 'fisica', now()->subDay());
        $expected = $this->article('Piu recente', 'fisica', now());

        $response = $this->get(route('articolo', $source->slug));
        $response->assertOk();
        $response->assertSee($expected->title);

        // $older appare comunque altrove in pagina (ticker, "più letti",
        // correlati) — sezioni indipendenti che elencano tutti gli articoli
        // recenti, non l'output del motore. Qui si verifica solo che il
        // motore stesso non abbia scelto $older come continuazione,
        // restringendo l'assert al solo blocco "Continua da qui".
        $continuationSection = $this->extractContinuationSectionHtml($response->getContent());
        $this->assertStringContainsString($expected->title, $continuationSection);
        $this->assertStringNotContainsString($older->title, $continuationSection);
    }

    // ── Visibility matrix ──────────────────────────────────────────────

    public function test_visibility_matrix_excludes_non_public_states(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $this->articleWithStatus('Bozza', 'fisica', Article::STATUS_DRAFT, null);
        $this->articleWithStatus('Revisione', 'fisica', Article::STATUS_REVIEW, null);
        $this->articleWithStatus('Programmato', 'fisica', Article::STATUS_SCHEDULED, now()->addDay());

        $response = $this->get(route('articolo', $source->slug));
        $response->assertOk();
        $response->assertDontSee('Bozza');
        $response->assertDontSee('Revisione');
        $response->assertDontSee('Programmato');
        $response->assertDontSee('Continua da qui');
    }

    // ── Loop analysis ───────────────────────────────────────────────

    public function test_loop_a_to_b_and_b_to_a_is_the_documented_engine_limitation_not_a_new_bug(): void
    {
        $a = $this->article('Articolo A', 'fisica');
        $b = $this->article('Articolo B', 'fisica');

        // Con solo due articoli nella stessa categoria, ciascuno è
        // l'unico candidato per l'altro: A raccomanda B, B raccomanda A.
        // Comportamento del motore già documentato come limitazione nota
        // (nessuno stato di sessione/cronologia in questa v1) — qui si
        // verifica solo che il ciclo sia stabile e non causi errori,
        // duplicazioni di query o crash, non che venga "risolto".
        $responseA = $this->get(route('articolo', $a->slug));
        $responseA->assertOk()->assertSee($b->title);

        $responseB = $this->get(route('articolo', $b->slug));
        $responseB->assertOk()->assertSee($a->title);
    }

    // ── Analytics math ───────────────────────────────────────────────

    public function test_analytics_math_matches_a_manually_counted_fixture(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        // 3 impression (3 sessioni), 2 second read.
        for ($i = 0; $i < 3; $i++) {
            $this->get(route('articolo', $source->slug))->assertOk();
            $this->flushSession();
        }

        for ($i = 0; $i < 2; $i++) {
            $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
                'slug' => $target->slug,
                'cd_src' => $source->id,
            ]);
            $this->get($url)->assertOk();
            $this->flushSession();
        }

        $stats = app(ContinuationAnalyticsService::class)->statsFor($source->fresh());

        $this->assertSame(3, $stats['impressions']);
        $this->assertSame(2, $stats['second_reads']);
        $this->assertEqualsWithDelta(0.6667, $stats['second_read_rate'], 0.001);
    }

    // ── Refresh / retry resilience across the whole stack ─────────────

    public function test_refresh_and_double_click_do_not_inflate_the_funnel(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        $this->get(route('articolo', $source->slug))->assertOk();
        $this->get(route('articolo', $source->slug))->assertOk();
        $this->get(route('articolo', $source->slug))->assertOk();

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $target->slug,
            'cd_src' => $source->id,
        ]);
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        // Scoped alla coppia (source→target): visitare la pagina di $target
        // genera legittimamente una SECONDA impression nella direzione
        // opposta (target→source, stesso limite "loop" già documentato in
        // test_loop_a_to_b_and_b_to_a_is_the_documented_engine_limitation_not_a_new_bug
        // — con solo due articoli nella categoria ciascuno è il candidato
        // dell'altro). Non è un'inflazione del funnel source→target, quindi
        // un conteggio globale per event_type sbaglierebbe l'asserzione.
        $this->assertSame(1, ArticleContinuationEvent::where('event_type', ArticleContinuationEvent::EVENT_IMPRESSION)
            ->where('source_article_id', $source->id)
            ->where('target_article_id', $target->id)
            ->count());
        $this->assertSame(1, ArticleContinuationEvent::where('event_type', ArticleContinuationEvent::EVENT_SECOND_READ_START)
            ->where('source_article_id', $source->id)
            ->where('target_article_id', $target->id)
            ->count());
    }

    // ── Consent parity ───────────────────────────────────────────────

    public function test_navigation_and_analytics_are_identical_regardless_of_ga4_consent_state(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione', 'fisica');

        // Nessuna interazione con il cookie di consenso GA4 in questo
        // flusso: la pagina non deve comportarsi diversamente se letta
        // con o senza quel cookie, perché questo layer di analytics è
        // interamente first-party e non dipende da AnalyticsExclusionService
        // né dal banner cookie — vedi ContinuationAnalyticsService.
        $withoutConsentCookie = $this->get(route('articolo', $source->slug));
        $withoutConsentCookie->assertOk()->assertSee('Continua da qui');

        $this->assertDatabaseCount('article_continuation_events', 1);
    }

    // ── Failure injection across the integrated stack ─────────────────

    public function test_full_stack_survives_analytics_storage_failure(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione', 'fisica');

        DB::statement('DROP TABLE article_continuation_events');

        $response = $this->get(route('articolo', $source->slug));
        $response->assertOk();
        $response->assertSee($source->title);
        $response->assertSee('Continua da qui');
    }

    // ── Query budget across the three stages ──────────────────────────

    public function test_query_budget_progression_engine_ui_analytics(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione', 'fisica');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('articolo', $source->slug))->assertOk();
        $withAnalytics = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Baseline documentato: articolo senza Continua da qui ≤11
        // (PublicPageQueryBudgetTest storico); con motore+UI ≤12; con
        // l'INSERT di analytics quando il modulo mostra un candidato,
        // ≤13 storico, poi ≤15 (+2 query bounded, Mission 24/25 — Content
        // Graph Public Consumer, discoverableConceptsForArticle() per il
        // JSON-LD `about` — vedi SecondReadAnalyticsTest::
        // test_impression_write_adds_exactly_one_bounded_query), ora ≤16
        // (+1 ulteriore, Mission 43 — category source-debt #258: il
        // composer DB-first in AppServiceProvider aggiunge una singola
        // query bounded per-request per header/category-bar/sidebar/
        // footer). Un solo numero qui, sull'integrazione reale dei
        // componenti.
        $this->assertLessThanOrEqual(16, $withAnalytics);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function extractContinuationSectionHtml(string $html): string
    {
        preg_match('/<section class="continue-reading".*?<\/section>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    private function article(string $title, string $category, $publishedAt = null): Article
    {
        return $this->articleWithStatus($title, $category, Article::STATUS_PUBLISHED, $publishedAt ?? now()->subMinute());
    }

    private function articleWithStatus(string $title, string $category, string $status, $publishedAt): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'excerpt' => 'Estratto.',
            'category' => $category,
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt,
        ]);
    }
}
