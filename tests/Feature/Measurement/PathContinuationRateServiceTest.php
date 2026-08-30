<?php

namespace Tests\Feature\Measurement;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\EditorialContinuityEvent;
use App\Models\User;
use App\Services\Measurement\MeasurementWindow;
use App\Services\Measurement\MetricResult;
use App\Services\Measurement\PathContinuationRateService;
use App\Services\Telemetry\EditorialEventContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Measurement Closeout (Missione 4) — path continuation rate. Il
 * denominatore è SEMPRE article.transition_available, mai una pageview
 * generica: questi test lo esercitano seminando gli eventi direttamente,
 * cosa che permette anche di coprire scenari (membership disattivate,
 * contenuti non pubblici) senza dover ricostruire l'intera pagina.
 */
class PathContinuationRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'editor']);
    }

    private function article(string $title = 'Articolo'): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    private function seedAvailable(
        string $session,
        Article $source,
        Article $target,
        string $type = EditorialEventContract::TRANSITION_NEXT,
        ?ContentCluster $cluster = null,
        ?\DateTimeInterface $at = null,
    ): EditorialContinuityEvent {
        return EditorialContinuityEvent::create([
            'event_name' => EditorialEventContract::TRANSITION_AVAILABLE,
            'schema_version' => EditorialEventContract::SCHEMA_VERSION,
            'session_key' => hash('sha256', $session),
            'article_id' => $source->id,
            'target_article_id' => $target->id,
            'content_cluster_id' => $cluster?->id,
            'transition_type' => $type,
            'source_channel' => 'direct',
            'occurred_at' => $at ?? now(),
        ]);
    }

    private function seedFollowedView(string $session, Article $target, ?\DateTimeInterface $at = null): void
    {
        EditorialContinuityEvent::create([
            'event_name' => EditorialEventContract::ARTICLE_VIEWED,
            'schema_version' => EditorialEventContract::SCHEMA_VERSION,
            'session_key' => hash('sha256', $session),
            'article_id' => $target->id,
            'source_channel' => 'direct',
            'occurred_at' => $at ?? now()->addSecond(),
        ]);
    }

    private function window(): MeasurementWindow
    {
        return MeasurementWindow::fromDates(now()->subDays(2)->toDateString(), now()->addDay()->toDateString());
    }

    public function test_an_available_transition_that_is_followed_counts_in_both_numerator_and_denominator(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');

        $this->seedAvailable('s1', $a, $b);
        $this->seedFollowedView('s1', $b);

        $result = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 1);

        $this->assertSame(1, $result['available']);
        $this->assertSame(1, $result['followed']);
        $this->assertSame(1.0, $result['rate']->value);
    }

    public function test_an_available_transition_never_clicked_counts_only_in_the_denominator(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');

        $this->seedAvailable('s1', $a, $b);

        $result = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 1);

        $this->assertSame(1, $result['available']);
        $this->assertSame(0, $result['followed']);
        $this->assertSame(0.0, $result['rate']->value);
    }

    public function test_the_first_article_of_a_path_has_no_previous_transition_available_event_by_construction(): void
    {
        // Nessun evento previous seminato per il primo elemento: la
        // proprietà è garantita dal producer reale (ArticleController), non
        // da questa classe — qui verifichiamo solo che l'assenza di eventi
        // per un tipo produce correttamente insufficient_data e non un
        // errore di divisione.
        $result = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 1);

        $this->assertSame(0, $result['available']);
        $this->assertSame(MetricResult::INSUFFICIENT_DATA, $result['rate']->status);
    }

    public function test_a_transition_followed_by_a_different_session_does_not_count_as_followed(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');

        $this->seedAvailable('s1', $a, $b);
        // Sessione diversa: non deve contare come "seguita" da chi ha visto
        // il controllo disponibile.
        $this->seedFollowedView('s2', $b);

        $result = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 1);

        $this->assertSame(0, $result['followed']);
    }

    public function test_duplicate_availability_events_for_the_same_pair_are_not_double_counted_by_the_recorder(): void
    {
        // Il recorder reale deduplica per sessione — qui verifichiamo che il
        // servizio conta comunque correttamente se (per ipotesi difensiva)
        // due righe esistessero: ciascuna riga disponibile è un denominatore
        // a sé, coerente con "quante volte un controllo era disponibile".
        $a = $this->article('A');
        $b = $this->article('B');

        $this->seedAvailable('s1', $a, $b, at: now()->subMinutes(2));
        $this->seedAvailable('s2', $a, $b, at: now()->subMinute());
        $this->seedFollowedView('s1', $b, at: now()->subMinutes(2)->addSecond());

        $result = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 1);

        $this->assertSame(2, $result['available']);
        $this->assertSame(1, $result['followed']);
    }

    public function test_segmentation_by_transition_type_isolates_previous_next_and_continua_da_qui(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');
        $c = $this->article('C');

        $this->seedAvailable('s1', $a, $b, EditorialEventContract::TRANSITION_NEXT);
        $this->seedFollowedView('s1', $b);

        $this->seedAvailable('s2', $a, $c, EditorialEventContract::TRANSITION_CONTINUA_DA_QUI);

        $byType = (new PathContinuationRateService)->byTransitionType($this->window(), minimumAvailable: 1);

        $next = $byType->firstWhere('transition_type', EditorialEventContract::TRANSITION_NEXT);
        $continua = $byType->firstWhere('transition_type', EditorialEventContract::TRANSITION_CONTINUA_DA_QUI);
        $previous = $byType->firstWhere('transition_type', EditorialEventContract::TRANSITION_PREVIOUS);

        $this->assertSame(1, $next['available']);
        $this->assertSame(1, $next['followed']);

        $this->assertSame(1, $continua['available']);
        $this->assertSame(0, $continua['followed']);

        // Nessun evento 'previous' seminato: non deve comparire affatto
        // (available() a zero è filtrato, non mostrato come 0/0).
        $this->assertNull($previous);
    }

    public function test_segmentation_by_path_groups_correctly_and_excludes_transitions_without_a_cluster(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');
        $c = $this->article('C');
        $cluster = ContentCluster::factory()->create(['is_active' => true]);

        $this->seedAvailable('s1', $a, $b, cluster: $cluster);
        $this->seedFollowedView('s1', $b);

        // continua_da_qui fuori Percorso (fallback di categoria): non ha un
        // content_cluster_id e non deve comparire nella segmentazione per
        // Percorso, pur restando nel totale complessivo.
        $this->seedAvailable('s2', $a, $c, EditorialEventContract::TRANSITION_CONTINUA_DA_QUI, cluster: null);

        $byPath = (new PathContinuationRateService)->byPath($this->window(), minimumAvailable: 1);

        $this->assertCount(1, $byPath);
        $this->assertSame($cluster->id, $byPath->first()['content_cluster_id']);
        $this->assertSame(1, $byPath->first()['available']);
        $this->assertSame(1, $byPath->first()['followed']);

        $overall = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 1);
        $this->assertSame(2, $overall['available']);
    }

    public function test_events_outside_the_window_are_excluded(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');

        $this->seedAvailable('s1', $a, $b, at: now()->subDays(30));

        $result = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 1);

        $this->assertSame(0, $result['available']);
    }

    public function test_a_sample_below_minimum_available_is_insufficient_data(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');

        $this->seedAvailable('s1', $a, $b);
        $this->seedFollowedView('s1', $b);

        $result = (new PathContinuationRateService)->overall($this->window(), minimumAvailable: 20);

        $this->assertSame(MetricResult::INSUFFICIENT_DATA, $result['rate']->status);
    }
}
