<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * S5: docs/STORAGE_AUDIT.md aveva già identificato "failed_jobs mai
 * ripulita: nessun comando di prune schedulato" come gap da colmare prima
 * che il volume crescesse. queue:prune-failed è un comando nativo Laravel
 * (nessuna nuova infrastruttura) — qui solo schedulato, con 30 giorni di
 * retention invece del default nativo di 24 ore.
 */
class QueuePruneFailedScheduleTest extends TestCase
{
    public function test_queue_prune_failed_is_registered_on_the_schedule_with_a_thirty_day_retention(): void
    {
        $schedule = app(Schedule::class);
        $commands = collect($schedule->events())->map(fn ($event) => $event->command ?? '');

        $this->assertTrue(
            $commands->contains(fn ($command) => str_contains($command, 'queue:prune-failed') && str_contains($command, '--hours=720'))
        );
    }
}
