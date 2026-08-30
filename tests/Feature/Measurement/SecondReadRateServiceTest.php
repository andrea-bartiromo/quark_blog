<?php

namespace Tests\Feature\Measurement;

use App\Models\Article;
use App\Models\EditorialContinuityEvent;
use App\Models\User;
use App\Services\Measurement\MeasurementWindow;
use App\Services\Measurement\MetricResult;
use App\Services\Measurement\SecondReadRateService;
use App\Services\Telemetry\EditorialEventContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Measurement Closeout (Missione 3) — second-read rate.
 */
class SecondReadRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private Article $a1;

    private Article $a2;

    private Article $a3;

    protected function setUp(): void
    {
        parent::setUp();

        $author = User::factory()->create(['role' => 'editor']);
        $this->a1 = $this->article($author, 'Articolo Uno');
        $this->a2 = $this->article($author, 'Articolo Due');
        $this->a3 = $this->article($author, 'Articolo Tre');
    }

    private function article(User $author, string $title): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    private function seedView(string $session, Article $article, string $source = 'direct', ?\DateTimeInterface $at = null): void
    {
        EditorialContinuityEvent::create([
            'event_name' => EditorialEventContract::ARTICLE_VIEWED,
            'schema_version' => EditorialEventContract::SCHEMA_VERSION,
            'session_key' => hash('sha256', $session),
            'article_id' => $article->id,
            'source_channel' => $source,
            'occurred_at' => $at ?? now(),
        ]);
    }

    private function window(): MeasurementWindow
    {
        return MeasurementWindow::fromDates(now()->subDays(2)->toDateString(), now()->addDay()->toDateString());
    }

    public function test_a_single_session_with_one_article_counts_toward_the_denominator_only(): void
    {
        $this->seedView('s1', $this->a1);

        $result = (new SecondReadRateService)->overall($this->window(), minimumSessions: 1);

        $this->assertSame(1, $result['sessions_with_one_article']);
        $this->assertSame(0, $result['sessions_with_two_articles']);
        $this->assertSame(MetricResult::AVAILABLE, $result['rate']->status);
        $this->assertSame(0.0, $result['rate']->value);
    }

    public function test_a_session_with_two_distinct_articles_counts_as_a_second_read(): void
    {
        $this->seedView('s1', $this->a1);
        $this->seedView('s1', $this->a2);

        $result = (new SecondReadRateService)->overall($this->window(), minimumSessions: 1);

        $this->assertSame(1, $result['sessions_with_one_article']);
        $this->assertSame(1, $result['sessions_with_two_articles']);
        $this->assertSame(1.0, $result['rate']->value);
    }

    public function test_a_session_reading_the_same_article_twice_does_not_count_as_a_second_read(): void
    {
        $this->seedView('s1', $this->a1);
        $this->seedView('s1', $this->a1);

        $result = (new SecondReadRateService)->overall($this->window(), minimumSessions: 1);

        $this->assertSame(1, $result['sessions_with_one_article']);
        $this->assertSame(0, $result['sessions_with_two_articles']);
    }

    public function test_repeated_sessions_are_each_counted_independently(): void
    {
        $this->seedView('s1', $this->a1);
        $this->seedView('s1', $this->a2);
        $this->seedView('s2', $this->a1);
        $this->seedView('s3', $this->a1);
        $this->seedView('s3', $this->a2);
        $this->seedView('s3', $this->a3);

        $result = (new SecondReadRateService)->overall($this->window(), minimumSessions: 1);

        $this->assertSame(3, $result['sessions_with_one_article']);
        $this->assertSame(2, $result['sessions_with_two_articles']);
        $this->assertEqualsWithDelta(2 / 3, $result['rate']->value, 0.0001);
    }

    public function test_an_empty_window_reports_insufficient_data_not_zero(): void
    {
        $result = (new SecondReadRateService)->overall($this->window());

        $this->assertSame(MetricResult::INSUFFICIENT_DATA, $result['rate']->status);
        $this->assertNull($result['rate']->value);
    }

    public function test_a_sample_below_the_minimum_threshold_is_insufficient_data_not_a_real_rate(): void
    {
        $this->seedView('s1', $this->a1);
        $this->seedView('s1', $this->a2);

        // Soglia minima esplicitamente più alta del campione osservato (1
        // sessione): anche se il rapporto matematico sarebbe 1.0, non deve
        // essere pubblicato.
        $result = (new SecondReadRateService)->overall($this->window(), minimumSessions: 20);

        $this->assertSame(MetricResult::INSUFFICIENT_DATA, $result['rate']->status);
        $this->assertNull($result['rate']->value);
    }

    public function test_events_outside_the_window_are_excluded(): void
    {
        $this->seedView('s1', $this->a1, at: now()->subDays(30));
        $this->seedView('s1', $this->a2, at: now()->subDays(30));

        $result = (new SecondReadRateService)->overall($this->window(), minimumSessions: 1);

        $this->assertSame(0, $result['sessions_with_one_article']);
        $this->assertSame(MetricResult::INSUFFICIENT_DATA, $result['rate']->status);
    }

    public function test_an_event_exactly_at_the_lower_boundary_is_included(): void
    {
        $window = MeasurementWindow::fromDates(now()->toDateString(), now()->toDateString());
        $this->seedView('s1', $this->a1, at: now()->timezone(Article::EDITORIAL_TIMEZONE)->startOfDay());

        $result = (new SecondReadRateService)->overall($window, minimumSessions: 1);

        $this->assertSame(1, $result['sessions_with_one_article']);
    }

    public function test_an_event_at_the_start_of_the_day_after_the_window_is_excluded(): void
    {
        $window = MeasurementWindow::fromDates(now()->toDateString(), now()->toDateString());
        $this->seedView('s1', $this->a1, at: now()->timezone(Article::EDITORIAL_TIMEZONE)->addDay()->startOfDay());

        $result = (new SecondReadRateService)->overall($window, minimumSessions: 1);

        $this->assertSame(0, $result['sessions_with_one_article']);
    }

    public function test_segmentation_by_source_attributes_each_session_to_its_entry_source(): void
    {
        $this->seedView('s1', $this->a1, source: 'google');
        $this->seedView('s1', $this->a2, source: 'internal');

        $this->seedView('s2', $this->a1, source: 'newsletter');

        $bySource = (new SecondReadRateService)->bySource($this->window(), minimumSessions: 1);

        $google = $bySource->firstWhere('source_channel', 'google');
        $newsletter = $bySource->firstWhere('source_channel', 'newsletter');

        $this->assertNotNull($google);
        $this->assertSame(1, $google['sessions_with_one_article']);
        $this->assertSame(1, $google['sessions_with_two_articles']);

        $this->assertNotNull($newsletter);
        $this->assertSame(1, $newsletter['sessions_with_one_article']);
        $this->assertSame(0, $newsletter['sessions_with_two_articles']);
    }

    public function test_segmentation_never_exposes_a_session_identifier(): void
    {
        $this->seedView('s1', $this->a1, source: 'google');

        $bySource = (new SecondReadRateService)->bySource($this->window(), minimumSessions: 1);

        foreach ($bySource as $row) {
            // Conteggi aggregati come 'sessions_with_one_article' sono
            // legittimi (sono NUMERI, non identificatori); ciò che non deve
            // mai comparire è una chiave o un valore che porti un singolo
            // identificatore di sessione.
            $this->assertArrayNotHasKey('session_key', $row);
            $this->assertArrayNotHasKey('session_id', $row);

            foreach ($row as $value) {
                if (is_string($value)) {
                    $this->assertDoesNotMatchRegularExpression('/^[0-9a-f]{64}$/', $value);
                }
            }
        }
    }

    public function test_last_event_at_reflects_the_most_recent_event_regardless_of_window(): void
    {
        $this->seedView('s1', $this->a1, at: now()->subHour());

        $lastEventAt = (new SecondReadRateService)->lastEventAt();

        $this->assertNotNull($lastEventAt);
    }

    public function test_last_event_at_is_null_when_no_event_exists(): void
    {
        $this->assertNull((new SecondReadRateService)->lastEventAt());
    }
}
