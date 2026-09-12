<?php

namespace Tests\Feature\PublicPages;

use App\Models\NotFoundHit;
use App\Models\User;
use App\Services\PublicPages\NotFoundHitTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Cantiere 24 (programma 100-cantieri Kairus). NotFoundHitTracker
 * aggrega per path i 404 reali incontrati dal traffico pubblico — mai
 * una riga per hit, e mai i 404 sintetici prodotti da un audit interno
 * (Cantiere 22/23) o dalla navigazione di un redattore autenticato.
 */
class NotFoundHitTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_new_hit_for_an_unknown_path(): void
    {
        $request = Request::create('/questo-path-non-esiste', 'GET');

        app(NotFoundHitTracker::class)->recordHit($request);

        $this->assertDatabaseHas('not_found_hits', [
            'path' => '/questo-path-non-esiste',
            'hits' => 1,
        ]);
    }

    public function test_a_second_hit_on_the_same_path_increments_instead_of_duplicating(): void
    {
        $tracker = app(NotFoundHitTracker::class);

        $tracker->recordHit(Request::create('/vecchio-link', 'GET'));
        $tracker->recordHit(Request::create('/vecchio-link', 'GET'));
        $tracker->recordHit(Request::create('/vecchio-link', 'GET'));

        $this->assertSame(1, NotFoundHit::query()->count());
        $this->assertDatabaseHas('not_found_hits', [
            'path' => '/vecchio-link',
            'hits' => 3,
        ]);
    }

    public function test_different_paths_are_tracked_as_separate_rows(): void
    {
        $tracker = app(NotFoundHitTracker::class);

        $tracker->recordHit(Request::create('/uno', 'GET'));
        $tracker->recordHit(Request::create('/due', 'GET'));

        $this->assertSame(2, NotFoundHit::query()->count());
    }

    public function test_records_the_referer_and_updates_it_on_a_later_hit(): void
    {
        $tracker = app(NotFoundHitTracker::class);

        $tracker->recordHit(Request::create('/link-rotto', 'GET', server: [
            'HTTP_REFERER' => 'https://esempio.it/prima-pagina',
        ]));
        $tracker->recordHit(Request::create('/link-rotto', 'GET', server: [
            'HTTP_REFERER' => 'https://esempio.it/seconda-pagina',
        ]));

        $this->assertDatabaseHas('not_found_hits', [
            'path' => '/link-rotto',
            'last_referer' => 'https://esempio.it/seconda-pagina',
        ]);
    }

    /**
     * Cantiere 22/23: un audit interno (RedirectAndCanonicalIntegrityAudit)
     * visita deliberatamente vecchi slug che rispondono 404 come esito
     * CORRETTO — se questo registro li contasse, ogni esecuzione
     * dell'audit sporcherebbe il registro con 404 mai visti da un
     * visitatore reale.
     */
    public function test_a_request_carrying_the_internal_audit_header_is_never_recorded(): void
    {
        $request = Request::create('/slug-verificato-dallaudit', 'GET');
        $request->headers->set('X-Kairus-Internal-Audit', '1');

        app(NotFoundHitTracker::class)->recordHit($request);

        $this->assertSame(0, NotFoundHit::query()->count());
    }

    public function test_a_hit_from_an_authenticated_redazione_user_is_never_recorded(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $this->actingAs($author);

        app(NotFoundHitTracker::class)->recordHit(Request::create('/link-provato-dal-redattore', 'GET'));

        $this->assertSame(0, NotFoundHit::query()->count());
    }

    public function test_a_hit_from_an_authenticated_reader_without_redazione_access_is_recorded(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $this->actingAs($reader);

        app(NotFoundHitTracker::class)->recordHit(Request::create('/link-provato-da-un-lettore', 'GET'));

        $this->assertSame(1, NotFoundHit::query()->count());
    }
}
