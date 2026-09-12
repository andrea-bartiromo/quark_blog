<?php

namespace Tests\Feature;

use App\Models\NotFoundHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Codex (PR #572): CommunicationUnsubscribeController::confirm()
     * risponde 404 con ->setStatusCode(404) invece di lanciare/abortire —
     * non passa mai dal render() di HttpException. Il meccanismo di
     * registrazione (middleware globale sulla risposta finale, non un
     * hook sull'eccezione) deve coprire anche questo caso.
     */
    public function test_a_404_returned_directly_by_a_controller_via_setstatuscode_is_recorded(): void
    {
        $this->get('/comunicazione/disiscrizione/questo-token-non-esiste')->assertNotFound();

        $this->assertDatabaseHas('not_found_hits', [
            'path' => '/comunicazione/disiscrizione/questo-token-non-esiste',
        ]);
    }

    /**
     * Limite noto e accettato (Codex, PR #572 — verificato empiricamente,
     * non solo dedotto): per un path che non corrisponde a NESSUNA rotta,
     * il routing lancia il 404 PRIMA che il gruppo 'web' (e quindi
     * StartSession) sia mai eseguito per quella richiesta — auth()->user()
     * risulta sempre un guest anche per un redattore con un cookie di
     * sessione valido, perché la sessione non viene mai caricata quando
     * nessuna rotta viene raggiunta. Un tentativo di correzione con
     * Route::fallback() nel gruppo 'web' e' stato SCARTATO dopo aver
     * riprodotto concretamente una regressione peggiore: un fallback GET
     * intercetta l'individuazione dei verbi alternativi di Laravel
     * (Illuminate\Routing\AbstractRouteCollection::matchAgainstRoutes()),
     * trasformando ogni 405 "Method Not Allowed" dell'app in un 404 —
     * confermato rompendo
     * AnalyticsExclusionControllerTest::test_exclude_route_only_accepts_post().
     * Avviare la sessione a livello di middleware globale del kernel
     * (invece che solo nel gruppo 'web') introdurrebbe un rischio più
     * ampio (doppio avvio di sessione per OGNI richiesta dell'app),
     * sproporzionato per un registro puramente osservativo. Impatto
     * accettato: un link mal digitato da un redattore autenticato verso
     * un path del tutto inesistente può comparire in questo registro
     * come traffico reale — rumore minimo e autoreferenziale (compare
     * nello stesso strumento che quel redattore consulta), mai una
     * corruzione di analytics o contenuti reali come nel finding P1 del
     * Cantiere 22.
     *
     * Nota di verifica: un test che tenti di provare questo scenario con
     * $this->actingAs() non lo riprodurrebbe correttamente — actingAs()
     * imposta l'utente direttamente sul guard in memoria, bypassando del
     * tutto la sessione (osservazione di Codex, confermata leggendo
     * InteractsWithAuthentication::be()). Anche un login reale via POST
     * seguito da una richiesta separata nello stesso metodo di test NON
     * distingue il caso: il container dell'applicazione (e quindi il
     * guard) resta lo stesso tra le due chiamate in un singolo test,
     * mascherando esattamente come actingAs() la stessa assenza di
     * sessione che si verificherebbe invece tra due processi PHP separati
     * in produzione. Questo test documenta perciò il comportamento
     * ACCETTATO, non un fix: un redattore autenticato conta come
     * traffico reale quando il path non corrisponde a nessuna rotta.
     */
    public function test_an_unmatched_path_is_recorded_even_when_hit_by_an_authenticated_redazione_user(): void
    {
        $this->get('/questo-path-non-corrisponde-a-nessuna-rotta-ospite')->assertNotFound();

        $this->assertSame(1, NotFoundHit::query()->count());
    }

    /**
     * Codex (PR #572): un fallimento di scrittura del registro (qui
     * simulato eliminando la tabella) non deve mai trasformare il 404
     * originale in un 500 — il registro e' puramente osservativo.
     */
    public function test_a_registry_write_failure_never_turns_the_404_into_a_500(): void
    {
        Schema::drop('not_found_hits');

        $this->get('/questo-percorso-attiva-un-fallimento-di-scrittura')->assertNotFound();
    }
}
