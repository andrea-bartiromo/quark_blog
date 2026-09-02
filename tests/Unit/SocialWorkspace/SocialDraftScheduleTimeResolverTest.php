<?php

namespace Tests\Unit\SocialWorkspace;

use App\Services\SocialWorkspace\SocialDraftScheduleTimeResolver;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Europe/Rome nel 2026: passaggio all'ora legale (CET -> CEST) domenica 29
 * marzo 2026 alle 02:00 (l'intervallo 02:00-02:59 non esiste quel giorno);
 * passaggio all'ora solare (CEST -> CET) domenica 25 ottobre 2026 alle
 * 03:00 (l'intervallo 02:00-02:59 si ripete due volte quel giorno).
 */
class SocialDraftScheduleTimeResolverTest extends TestCase
{
    private function resolver(): SocialDraftScheduleTimeResolver
    {
        return new SocialDraftScheduleTimeResolver;
    }

    public function test_nonexistent_local_time_at_spring_forward_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/non esiste/');

        $this->resolver()->toUtc('2026-03-29', '02:30');
    }

    public function test_ambiguous_local_time_at_fall_back_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ambiguo/');

        $this->resolver()->toUtc('2026-10-25', '02:30');
    }

    public function test_time_just_before_spring_forward_gap_resolves_correctly(): void
    {
        $utc = $this->resolver()->toUtc('2026-03-29', '01:30');

        // 01:30 CET (UTC+1) = 00:30 UTC.
        $this->assertSame('2026-03-29 00:30:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_time_just_after_spring_forward_gap_resolves_correctly(): void
    {
        $utc = $this->resolver()->toUtc('2026-03-29', '03:30');

        // 03:30 CEST (UTC+2) = 01:30 UTC.
        $this->assertSame('2026-03-29 01:30:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_time_well_before_fall_back_ambiguity_resolves_to_cest(): void
    {
        $utc = $this->resolver()->toUtc('2026-10-25', '01:30');

        // 01:30 CEST (UTC+2, prima del cambio) = 23:30 UTC del giorno prima.
        $this->assertSame('2026-10-24 23:30:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_time_well_after_fall_back_ambiguity_resolves_to_cet(): void
    {
        $utc = $this->resolver()->toUtc('2026-10-25', '04:30');

        // 04:30 CET (UTC+1, dopo il cambio) = 03:30 UTC.
        $this->assertSame('2026-10-25 03:30:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_end_of_month_boundary(): void
    {
        $utc = $this->resolver()->toUtc('2026-01-31', '23:30');

        $this->assertSame('2026-01-31 22:30:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_end_of_year_boundary(): void
    {
        $utc = $this->resolver()->toUtc('2026-12-31', '23:30');

        // Dicembre è sempre ora solare (CET, UTC+1).
        $this->assertSame('2026-12-31 22:30:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolver()->toUtc('31-12-2026', '23:30');
    }

    public function test_round_trip_display_conversion_is_always_unambiguous(): void
    {
        $utc = $this->resolver()->toUtc('2026-06-15', '10:00');
        $display = $this->resolver()->toEditorialDisplay($utc);

        $this->assertSame('2026-06-15 10:00', $display->format('Y-m-d H:i'));
        $this->assertSame('Europe/Rome', $display->timezoneName);
    }
}
