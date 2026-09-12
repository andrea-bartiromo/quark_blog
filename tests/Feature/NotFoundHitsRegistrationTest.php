<?php

namespace Tests\Feature;

use App\Models\NotFoundHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 24 (programma 100-cantieri Kairus). Verifica il collegamento
 * reale in bootstrap/app.php (App\Services\PublicPages\NotFoundHitTracker
 * agganciato al render() di HttpException per il codice 404) — non solo
 * il servizio isolato, ma l'intera richiesta HTTP end-to-end.
 */
class NotFoundHitsRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_real_404_response_is_recorded_in_the_registry(): void
    {
        $this->get('/questo-percorso-non-esiste-davvero')->assertNotFound();

        $this->assertDatabaseHas('not_found_hits', [
            'path' => '/questo-percorso-non-esiste-davvero',
            'hits' => 1,
        ]);
    }

    /**
     * Stesso principio del fix Cantiere 22 per le analytics di
     * visualizzazione articolo: un audit interno (Cantiere 23) visita
     * deliberatamente path che rispondono 404 come esito corretto — non
     * devono mai comparire in questo registro.
     */
    public function test_a_404_from_an_internal_audit_request_is_not_recorded(): void
    {
        $this->withHeaders(['X-Kairus-Internal-Audit' => '1'])
            ->get('/questo-percorso-e-verificato-solo-dallaudit')
            ->assertNotFound();

        $this->assertSame(0, NotFoundHit::query()->count());
    }
}
