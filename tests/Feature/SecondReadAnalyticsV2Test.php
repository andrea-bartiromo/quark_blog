<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use App\Models\User;
use App\Services\ContinuationAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Growth S2 — Second Read Analytics V2: evoluzione del conteggio grezzo
 * (#267/#268) in segnale editoriale — riepilogo per articolo, intervalli
 * di date reali (since + until), stati vuoti e superficie admin di sola
 * lettura. Nessun nuovo tracking introdotto: solo aggregazione di ciò che
 * article_continuation_events registra già.
 */
class SecondReadAnalyticsV2Test extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'author']);
        $this->editor = User::factory()->create(['role' => 'editor']);
    }

    // ── articleBreakdown() ───────────────────────────────────────────

    public function test_article_breakdown_is_empty_when_no_events_exist(): void
    {
        $breakdown = app(ContinuationAnalyticsService::class)->articleBreakdown();

        $this->assertTrue($breakdown->isEmpty());
    }

    public function test_article_breakdown_aggregates_correctly_per_source_article(): void
    {
        $a = $this->article('Articolo A');
        $b = $this->article('Articolo B');
        $target = $this->article('Destinazione');

        // A: 3 impression, 1 second read.
        $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $a, $target, 3);
        $this->recordEvent(ArticleContinuationEvent::EVENT_SECOND_READ_START, $a, $target, 1);

        // B: 2 impression, 0 second read.
        $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $b, $target, 2);

        $breakdown = app(ContinuationAnalyticsService::class)->articleBreakdown();

        $this->assertCount(2, $breakdown);

        $rowA = $breakdown->firstWhere('source_article_id', $a->id);
        $this->assertSame(3, $rowA['impressions']);
        $this->assertSame(1, $rowA['second_reads']);
        $this->assertSame(0.3333, $rowA['second_read_rate']);
        $this->assertSame($a->title, $rowA['title']);
        $this->assertSame($a->slug, $rowA['slug']);

        $rowB = $breakdown->firstWhere('source_article_id', $b->id);
        $this->assertSame(2, $rowB['impressions']);
        $this->assertSame(0, $rowB['second_reads']);
        $this->assertSame(0.0, $rowB['second_read_rate']);

        // Ordinato per second_reads decrescenti: A (1) prima di B (0).
        $this->assertSame($a->id, $breakdown->first()['source_article_id']);
    }

    public function test_article_breakdown_respects_since_and_until_bounds(): void
    {
        $source = $this->article('Sorgente');
        $target = $this->article('Destinazione');

        // create() sovrascrive sempre created_at con il timestamp reale
        // (comportamento standard di Eloquent per i nuovi record): il
        // valore storico va impostato con un update esplicito dopo la
        // creazione, non passato all'array di create().
        $old = ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
        ArticleContinuationEvent::whereKey($old->id)->update(['created_at' => now()->subDays(10)]);

        $recent = ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
        ArticleContinuationEvent::whereKey($recent->id)->update(['created_at' => now()->subDays(2)]);

        $recentOnly = app(ContinuationAnalyticsService::class)->articleBreakdown(now()->subDays(5));
        $this->assertSame(1, $recentOnly->first()['impressions']);

        $oldOnly = app(ContinuationAnalyticsService::class)->articleBreakdown(null, now()->subDays(5));
        $this->assertSame(1, $oldOnly->first()['impressions']);

        $both = app(ContinuationAnalyticsService::class)->articleBreakdown(now()->subDays(15), now());
        $this->assertSame(2, $both->first()['impressions']);
    }

    public function test_article_breakdown_query_count_is_bounded_regardless_of_source_count(): void
    {
        $target = $this->article('Destinazione condivisa');

        for ($i = 0; $i < 8; $i++) {
            $source = $this->article("Sorgente {$i}");
            $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $source, $target, 1);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ContinuationAnalyticsService::class)->articleBreakdown();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 query di aggregazione + 1 query whereIn per i titoli: mai una
        // query per articolo sorgente, indipendentemente da quanti ce ne
        // siano.
        $this->assertLessThanOrEqual(2, $queryCount);
    }

    // ── siteWideTotals() ─────────────────────────────────────────────

    /**
     * Missione 33 (secondo batch autonomo KAIRUS, Fase D — Editorial
     * Operations Command Center): "second-read operational health".
     * SecondReadAnalyticsController::index() sommava articleBreakdown()
     * per calcolare i totali — quella lista è troncata a `limit` (50 per
     * default) per la visualizzazione, quindi con più di `limit` articoli
     * sorgente distinti i totali sarebbero stati sottostimati. Qui una
     * finestra "limit=1" simula esattamente quel tetto e prova che
     * siteWideTotals() resta corretto indipendentemente da esso — mai più
     * una somma della lista troncata.
     */
    public function test_site_wide_totals_are_never_capped_by_the_breakdown_display_limit(): void
    {
        $target = $this->article('Destinazione condivisa totali');
        foreach (range(1, 3) as $i) {
            $source = $this->article("Sorgente totali {$i}");
            $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $source, $target, 2);
            $this->recordEvent(ArticleContinuationEvent::EVENT_SECOND_READ_START, $source, $target, 1);
        }

        $service = app(ContinuationAnalyticsService::class);
        $limitedBreakdown = $service->articleBreakdown(limit: 1);
        $totals = $service->siteWideTotals();

        $this->assertCount(1, $limitedBreakdown);
        $this->assertSame(6, $totals['impressions']);
        $this->assertSame(3, $totals['second_reads']);
        $this->assertSame(0.5, $totals['second_read_rate']);
        $this->assertSame(3, $totals['source_articles_engaged']);
    }

    public function test_site_wide_totals_respect_since_and_until_bounds(): void
    {
        $source = $this->article('Sorgente totali intervallo');
        $target = $this->article('Destinazione totali intervallo');

        $old = ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
        ArticleContinuationEvent::whereKey($old->id)->update(['created_at' => now()->subDays(10)]);

        ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);

        $recentOnly = app(ContinuationAnalyticsService::class)->siteWideTotals(now()->subDays(5));

        $this->assertSame(1, $recentOnly['impressions']);
    }

    // ── Admin controller ─────────────────────────────────────────────

    public function test_guests_cannot_view_the_second_read_admin_page(): void
    {
        $this->get(route('admin.second-read'))->assertRedirect(route('login'));
    }

    public function test_the_admin_page_shows_an_honest_empty_state_with_no_data(): void
    {
        $response = $this->actingAs($this->editor)->get(route('admin.second-read'));

        $response->assertOk();
        $response->assertSee('Nessun dato registrato ancora in questo periodo.');
    }

    public function test_the_admin_page_shows_the_breakdown_and_totals_with_data(): void
    {
        $source = $this->article('Articolo con second read');
        $target = $this->article('Destinazione');

        $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $source, $target, 4);
        $this->recordEvent(ArticleContinuationEvent::EVENT_SECOND_READ_START, $source, $target, 2);

        $response = $this->actingAs($this->editor)->get(route('admin.second-read'));

        $response->assertOk();
        $response->assertSee($source->title);
        $response->assertSee('50.0%');
    }

    public function test_the_admin_page_totals_sum_across_multiple_source_articles(): void
    {
        $target = $this->article('Destinazione multipla');
        $a = $this->article('Sorgente multipla A');
        $b = $this->article('Sorgente multipla B');

        $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $a, $target, 3);
        $this->recordEvent(ArticleContinuationEvent::EVENT_SECOND_READ_START, $a, $target, 1);
        $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $b, $target, 2);
        $this->recordEvent(ArticleContinuationEvent::EVENT_SECOND_READ_START, $b, $target, 1);

        $response = $this->actingAs($this->editor)->get(route('admin.second-read'));

        $response->assertOk();
        // 2 second read su 5 impression totali (3+2, non solo quelle del
        // primo articolo in classifica) = 40.0%, mai il tasso di un solo
        // articolo isolato.
        $response->assertSee('40.0%');
    }

    /**
     * Missione 52 (secondo batch autonomo KAIRUS, Fase F — Search
     * Intelligence): siteWideTotals()['source_articles_engaged'] era già
     * calcolato e già coperto da
     * test_site_wide_totals_are_never_capped_by_the_breakdown_display_limit
     * a livello di servizio, ma né questa pagina né la card "Continua da
     * qui" del dashboard editoriale lo mostravano mai — un segnale
     * distinto dal tasso/conteggio già visibili (ampiezza del
     * coinvolgimento, non solo la sua intensità).
     */
    public function test_the_admin_page_shows_how_many_distinct_source_articles_are_engaged(): void
    {
        $target = $this->article('Destinazione coinvolgimento');
        $a = $this->article('Sorgente coinvolgimento A');
        $b = $this->article('Sorgente coinvolgimento B');

        $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $a, $target, 1);
        $this->recordEvent(ArticleContinuationEvent::EVENT_IMPRESSION, $b, $target, 1);

        $response = $this->actingAs($this->editor)->get(route('admin.second-read'));

        $response->assertOk();
        $response->assertSee('Articoli sorgente coinvolti');
        $response->assertSee('2');
    }

    public function test_the_admin_page_date_range_filter_excludes_older_events(): void
    {
        $source = $this->article('Articolo vecchio');
        $target = $this->article('Destinazione');

        $event = ArticleContinuationEvent::create([
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
        ArticleContinuationEvent::whereKey($event->id)->update(['created_at' => now()->subDays(60)]);

        $response = $this->actingAs($this->editor)->get(route('admin.second-read', ['periodo' => '7']));

        $response->assertOk();
        $response->assertSee('Nessun dato registrato ancora in questo periodo.');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function article(string $title): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 2,
            'published_at' => now()->subMinute(),
        ]);
    }

    private function recordEvent(string $eventType, Article $source, Article $target, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            ArticleContinuationEvent::create([
                'event_type' => $eventType,
                'source_article_id' => $source->id,
                'target_article_id' => $target->id,
            ]);
        }
    }
}
