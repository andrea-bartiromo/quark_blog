<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use App\Models\User;
use App\Services\ContinuationAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecondReadAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'author']);
    }

    // ── Impression ───────────────────────────────────────────────────

    public function test_impression_is_recorded_when_the_continuation_module_renders(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        $this->get(route('articolo', $source->slug))->assertOk();

        $this->assertDatabaseHas('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
    }

    public function test_no_impression_when_there_is_no_candidate(): void
    {
        $source = $this->article('Unico articolo', 'fisica');

        $this->get(route('articolo', $source->slug))->assertOk();

        $this->assertDatabaseCount('article_continuation_events', 0);
    }

    public function test_impression_is_not_duplicated_on_page_refresh(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione', 'fisica');

        // Stesso client di test => stessa sessione, replica un refresh.
        $this->get(route('articolo', $source->slug))->assertOk();
        $this->get(route('articolo', $source->slug))->assertOk();

        $this->assertSame(
            1,
            ArticleContinuationEvent::where('event_type', ArticleContinuationEvent::EVENT_IMPRESSION)->count()
        );
    }

    public function test_internal_traffic_never_generates_an_impression(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $source = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione', 'fisica');

        $this->actingAs($editor)->get(route('articolo', $source->slug))->assertOk();

        $this->assertDatabaseCount('article_continuation_events', 0);
    }

    // ── Second read start ────────────────────────────────────────────

    public function test_valid_signed_arrival_records_a_second_read_start(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $target->slug,
            'cd_src' => $source->id,
        ]);

        $this->get($url)->assertOk();

        $this->assertDatabaseHas('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
        ]);
    }

    public function test_expired_signature_does_not_record_a_second_read(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->subMinute(), [
            'slug' => $target->slug,
            'cd_src' => $source->id,
        ]);

        $this->get($url)->assertOk();

        $this->assertDatabaseMissing('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
        ]);
    }

    public function test_tampered_source_parameter_does_not_record_a_second_read(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $decoy = $this->article('Esca', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $target->slug,
            'cd_src' => $source->id,
        ]);

        // Manomette cd_src dopo la firma: la firma non copre più il
        // valore realmente inviato, quindi hasValidSignature() deve
        // fallire.
        $tampered = str_replace('cd_src='.$source->id, 'cd_src='.$decoy->id, $url);

        $this->get($tampered)->assertOk();

        $this->assertDatabaseMissing('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
        ]);
    }

    public function test_a_signed_link_minted_for_one_target_cannot_be_replayed_against_another(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $mintedFor = $this->article('Destinazione originale', 'fisica');
        $decoyTarget = $this->article('Altra destinazione', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $mintedFor->slug,
            'cd_src' => $source->id,
        ]);

        // Riusa la firma per un altro slug: la firma copre l'intero URL
        // (path incluso), quindi cambiare lo slug la invalida.
        $replayed = str_replace($mintedFor->slug, $decoyTarget->slug, $url);

        $this->get($replayed)->assertOk();

        $this->assertDatabaseMissing('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
            'target_article_id' => $decoyTarget->id,
        ]);
    }

    public function test_scheduled_source_article_id_never_records_a_second_read(): void
    {
        $scheduledSource = $this->articleWithStatus('Programmato', 'fisica', Article::STATUS_SCHEDULED, now()->addDay());
        $target = $this->article('Prosecuzione', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $target->slug,
            'cd_src' => $scheduledSource->id,
        ]);

        $this->get($url)->assertOk();

        $this->assertDatabaseCount('article_continuation_events', 0);
    }

    public function test_self_target_never_records_a_second_read(): void
    {
        $article = $this->article('Corrente', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $article->slug,
            'cd_src' => $article->id,
        ]);

        $this->get($url)->assertOk();

        $this->assertDatabaseCount('article_continuation_events', 0);
    }

    public function test_draft_source_article_id_never_records_a_second_read(): void
    {
        $draftSource = $this->articleWithStatus('Bozza', 'fisica', Article::STATUS_DRAFT, null);
        $target = $this->article('Prosecuzione', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $target->slug,
            'cd_src' => $draftSource->id,
        ]);

        $this->get($url)->assertOk();

        $this->assertDatabaseCount('article_continuation_events', 0);
    }

    public function test_second_read_is_not_duplicated_on_refresh(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $target->slug,
            'cd_src' => $source->id,
        ]);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertSame(
            1,
            ArticleContinuationEvent::where('event_type', ArticleContinuationEvent::EVENT_SECOND_READ_START)->count()
        );
    }

    public function test_missing_signature_is_ignored_silently(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        // Stesso URL ma senza firma/scadenza: un utente potrebbe copiare
        // solo il path e incollarlo altrove.
        $response = $this->get(route('articolo', $target->slug).'?cd_src='.$source->id);

        $response->assertOk();
        $this->assertDatabaseMissing('article_continuation_events', [
            'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
        ]);
    }

    // ── Navigation resilience ────────────────────────────────────────

    public function test_reading_still_works_when_the_analytics_table_is_unavailable(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione', 'fisica');

        DB::statement('DROP TABLE article_continuation_events');

        $response = $this->get(route('articolo', $source->slug));

        $response->assertOk();
        $response->assertSee($source->title);
    }

    // ── Reportability ────────────────────────────────────────────────

    public function test_second_read_rate_is_computable_and_matches_manual_count(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $target = $this->article('Prosecuzione', 'fisica');

        // 2 impression (2 sessioni diverse), 1 second read.
        $this->get(route('articolo', $source->slug))->assertOk();
        $this->flushSession();
        $this->get(route('articolo', $source->slug))->assertOk();

        $url = URL::temporarySignedRoute('articolo', now()->addMinutes(30), [
            'slug' => $target->slug,
            'cd_src' => $source->id,
        ]);
        $this->get($url)->assertOk();

        $stats = app(ContinuationAnalyticsService::class)->statsFor($source->fresh());

        $this->assertSame(2, $stats['impressions']);
        $this->assertSame(1, $stats['second_reads']);
        $this->assertSame(0.5, $stats['second_read_rate']);
    }

    public function test_impression_write_adds_exactly_one_bounded_query(): void
    {
        $source = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione', 'fisica');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('articolo', $source->slug))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Budget dell'articolo con "Continua da qui" già stabilito da
        // PublicPageQueryBudgetTest (<=16, aggiornato dalla Mission 24/25
        // — Content Graph Public Consumer, +2 query bounded per
        // discoverableConceptsForArticle() nel JSON-LD `about`, dalla
        // Mission 43 — category source-debt #258, +1 query bounded per il
        // composer DB-first, e dal Trust Layer — riconciliazione Kairus,
        // trasparenza revisione pubblica, +1 query bounded per
        // ArticleRevisionTransparencyService::lastEditorialUpdate());
        // l'impression aggiunge esattamente 1 INSERT bounded, mai un N+1.
        $this->assertLessThanOrEqual(17, $queryCount);
    }

    public function test_second_read_rate_is_zero_with_no_impressions_rather_than_a_division_error(): void
    {
        $source = $this->article('Corrente', 'fisica');

        $stats = app(ContinuationAnalyticsService::class)->statsFor($source);

        $this->assertSame(0, $stats['impressions']);
        $this->assertSame(0, $stats['second_reads']);
        $this->assertSame(0.0, $stats['second_read_rate']);
    }

    // ── Helpers ──────────────────────────────────────────────────────

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
