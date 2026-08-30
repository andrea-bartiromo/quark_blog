<?php

namespace Tests\Feature\Measurement;

use App\Services\Telemetry\ContinuitySessionKey;
use Tests\TestCase;

/**
 * Measurement Closeout (Missione 2/5) — lo pseudonimo di sessione deve
 * essere stabile, non reversibile e mai confondibile con l'id di sessione
 * grezzo.
 */
class ContinuitySessionKeyTest extends TestCase
{
    public function test_the_same_session_id_always_derives_the_same_pseudonym(): void
    {
        $key = new ContinuitySessionKey;

        $first = $key->derive('some-session-id-abc123');
        $second = $key->derive('some-session-id-abc123');

        $this->assertSame($first, $second);
    }

    public function test_two_different_session_ids_derive_different_pseudonyms(): void
    {
        $key = new ContinuitySessionKey;

        $this->assertNotSame(
            $key->derive('session-a'),
            $key->derive('session-b'),
        );
    }

    public function test_the_pseudonym_is_a_64_char_hex_digest_matching_the_contract(): void
    {
        $key = new ContinuitySessionKey;

        $derived = $key->derive('any-session-id');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $derived);
    }

    public function test_the_pseudonym_never_equals_the_raw_session_id(): void
    {
        $key = new ContinuitySessionKey;
        $sessionId = 'aVeryPlausibleLaravelSessionIdentifier1234';

        $this->assertNotSame($sessionId, $key->derive($sessionId));
        $this->assertStringNotContainsString($sessionId, $key->derive($sessionId));
    }

    public function test_the_pseudonym_is_not_a_bare_sha256_of_the_session_id(): void
    {
        // Se fosse un semplice hash senza chiave, chiunque conoscesse
        // l'id di sessione potrebbe ricalcolare lo stesso pseudonimo e
        // ritrovare tutti gli eventi di quella sessione — l'HMAC con
        // APP_KEY esiste proprio per impedirlo.
        $key = new ContinuitySessionKey;
        $sessionId = 'session-under-test';

        $this->assertNotSame(hash('sha256', $sessionId), $key->derive($sessionId));
    }

    public function test_for_current_request_returns_null_without_a_started_session(): void
    {
        $key = new ContinuitySessionKey;

        // Nessuna richiesta HTTP in corso in questo test: la sessione
        // dell'applicazione non è avviata.
        $this->assertNull($key->forCurrentRequest());
    }
}
