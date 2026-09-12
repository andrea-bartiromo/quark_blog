<?php

namespace Tests\Feature\Console;

use App\Services\PublicPages\NotFoundHitTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Cantiere 24 (programma 100-cantieri Kairus): pages:not-found-registry
 * è di sola lettura — non è un gate di rilascio, è un catalogo di link
 * rotti reali per un editore/operatore.
 */
class NotFoundHitsReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_no_hits_by_default(): void
    {
        $this->artisan('pages:not-found-registry')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun 404 registrato dal traffico reale.');
    }

    public function test_lists_recorded_hits_ordered_by_occurrences(): void
    {
        $tracker = app(NotFoundHitTracker::class);
        $tracker->recordHit(Request::create('/link-poco-visitato', 'GET'));
        foreach (range(1, 3) as $i) {
            $tracker->recordHit(Request::create('/link-molto-visitato', 'GET'));
        }

        $this->artisan('pages:not-found-registry')
            ->assertExitCode(0)
            ->expectsOutputToContain('/link-molto-visitato')
            ->expectsOutputToContain('/link-poco-visitato');
    }

    public function test_json_output_is_produced_successfully(): void
    {
        app(NotFoundHitTracker::class)->recordHit(Request::create('/qualche-link', 'GET'));

        $this->artisan('pages:not-found-registry', ['--json' => true])->assertExitCode(0);
    }

    public function test_limit_option_restricts_the_number_of_rows(): void
    {
        $tracker = app(NotFoundHitTracker::class);
        $tracker->recordHit(Request::create('/link-uno', 'GET'));
        $tracker->recordHit(Request::create('/link-due', 'GET'));

        $this->artisan('pages:not-found-registry', ['--limit' => 1])
            ->assertExitCode(0)
            ->expectsOutputToContain('1 path distinti mostrati (limite: 1).');
    }
}
