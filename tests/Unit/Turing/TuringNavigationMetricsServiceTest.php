<?php

namespace Tests\Unit\Turing;

use App\Models\TuringChapterView;
use App\Services\Turing\TuringNavigationMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cantiere 68 (programma "100 cantieri Kairus").
 *
 * Rehearsal privacy-first della "navigazione aggregata" per lo Speciale
 * Turing: nessun test qui deve mai dimostrare che un identificativo di
 * visitatore/sessione/utente viene salvato — solo conteggi aggregati.
 */
class TuringNavigationMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stessa data di TuringNavigationMetricsService::TRACKING_STARTED_AT
     * (privata, quindi duplicata qui deliberatamente): i test che
     * dipendono dal tempo devono ancorarsi a questa data via
     * Carbon::setTestNow(), mai al "now" reale di esecuzione.
     */
    private const TRACKING_STARTED_AT = '2026-09-17 00:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): TuringNavigationMetricsService
    {
        return app(TuringNavigationMetricsService::class);
    }

    public function test_recording_a_view_creates_no_visitor_identifying_field(): void
    {
        $this->service()->recordView('enigma');

        $event = TuringChapterView::first();
        $this->assertNotNull($event);
        $this->assertSame('enigma', $event->chapter);

        // Nessun campo diverso da id/chapter/created_at deve esistere sulla
        // riga: la garanzia privacy-first è strutturale, non solo una
        // promessa nel docblock.
        $this->assertSame(['id', 'chapter', 'created_at'], array_keys($event->getAttributes()));
    }

    public function test_aggregate_is_insufficient_data_before_seven_days_have_passed(): void
    {
        Carbon::setTestNow(self::TRACKING_STARTED_AT);
        $this->service()->recordView('enigma');

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(3));
        $metrics = $this->service()->aggregateViews();

        $this->assertSame(TuringNavigationMetricsService::STATE_INSUFFICIENT_DATA, $metrics['enigma']['state']);
    }

    /**
     * Codex (PR #623, P2): l'orologio di raccolta si ancora al primo
     * evento MAI registrato, non a una data di deploy fissa — lo
     * Speciale resta dietro `config('turing.chapters_public')` (default
     * false) potenzialmente per settimane dopo il deploy di questa
     * strumentazione. Finché nessun evento reale è mai stato registrato,
     * lo stato deve restare "dati insufficienti" per sempre, mai un falso
     * "available" solo perché è passato molto tempo dal deploy del
     * codice (fail-closed, stesso principio già in uso nel resto del
     * programma).
     */
    public function test_aggregate_stays_insufficient_data_forever_if_no_event_was_ever_recorded(): void
    {
        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(365));

        $metrics = $this->service()->aggregateViews();

        $this->assertSame(TuringNavigationMetricsService::STATE_INSUFFICIENT_DATA, $metrics['hub']['state']);
        $this->assertSame(0, $metrics['hub']['days_collected']);
    }

    /**
     * Una volta che il gate si apre e il primo evento reale viene
     * registrato, un capitolo mai visitato deve comunque mostrare uno
     * zero reale (non "dati insufficienti") dopo 7 giorni dal PRIMO
     * evento — stesso principio "lo zero del campione resta zero" di
     * docs/DASHBOARD_DATA_EXPORT_V1.md, ma ancorato alla raccolta reale.
     */
    public function test_aggregate_is_available_with_a_real_zero_count_seven_days_after_the_first_real_event(): void
    {
        Carbon::setTestNow(self::TRACKING_STARTED_AT);
        $this->service()->recordView('enigma');

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(10));
        $metrics = $this->service()->aggregateViews();

        $this->assertSame(TuringNavigationMetricsService::STATE_AVAILABLE, $metrics['hub']['state']);
        $this->assertSame(0, $metrics['hub']['count']);
    }

    /**
     * Codex (PR #623, P2): se il gate si apre molto dopo il deploy del
     * codice, l'orologio deve partire dal primo evento reale, non dal
     * deploy — altrimenti "available" comparirebbe un istante dopo
     * l'apertura del gate, con zero vera raccolta avvenuta.
     */
    public function test_aggregate_stays_insufficient_data_right_after_the_gate_opens_long_after_deploy(): void
    {
        // Il gate si apre 100 giorni dopo il deploy del codice: se
        // l'orologio fosse ancora ancorato a TRACKING_STARTED_AT, questo
        // sarebbe già "available" (100 >= 7) nonostante la raccolta reale
        // sia iniziata un istante fa.
        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(100));
        $this->service()->recordView('enigma');

        $metrics = $this->service()->aggregateViews();

        $this->assertSame(TuringNavigationMetricsService::STATE_INSUFFICIENT_DATA, $metrics['enigma']['state']);
        $this->assertSame(0, $metrics['enigma']['days_collected']);
    }

    public function test_aggregate_counts_multiple_views_per_chapter_correctly_once_available(): void
    {
        $service = $this->service();

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(2));
        $service->recordView('hub');
        $service->recordView('enigma');
        $service->recordView('enigma');
        $service->recordView('ai');

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(10));
        $metrics = $service->aggregateViews();

        $this->assertSame(TuringNavigationMetricsService::STATE_AVAILABLE, $metrics['enigma']['state']);
        $this->assertSame(1, $metrics['hub']['count']);
        $this->assertSame(2, $metrics['enigma']['count']);
        $this->assertSame(1, $metrics['ai']['count']);
        $this->assertSame(0, $metrics['legacy']['count']);
        $this->assertSame(0, $metrics['computation']['count']);
        $this->assertSame(0, $metrics['intelligence']['count']);
    }

    public function test_aggregate_returns_every_known_chapter_even_with_zero_views(): void
    {
        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(10));

        $metrics = $this->service()->aggregateViews();

        $this->assertSame(TuringNavigationMetricsService::CHAPTERS, array_keys($metrics));
    }

    public function test_aggregate_stays_at_a_fixed_query_count_regardless_of_event_volume(): void
    {
        $service = $this->service();

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(2));
        foreach (range(1, 12) as $i) {
            $service->recordView(TuringNavigationMetricsService::CHAPTERS[$i % count(TuringNavigationMetricsService::CHAPTERS)]);
        }

        Carbon::setTestNow(Carbon::parse(self::TRACKING_STARTED_AT)->addDays(10));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->aggregateViews();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 2 query fisse (conteggi per capitolo + primo evento mai
        // registrato, vedi daysCollected()): mai una per riga né una per
        // capitolo, indipendentemente dal volume di eventi.
        $this->assertLessThanOrEqual(2, $queryCount, 'aggregateViews() deve restare O(1) in query indipendentemente dal volume di eventi.');
    }
}
