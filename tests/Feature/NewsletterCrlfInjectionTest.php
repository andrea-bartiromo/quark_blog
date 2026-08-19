<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * S8 — regressione per la CRLF injection nella regola di validazione
 * 'email' di Laravel (GHSA-5vg9-5847-vvmq / CVE-2026-48019, corretta in
 * laravel/framework <13.10.0 e <12.60.0). L'unico punto dell'app dove
 * l'input email di un utente NON autenticato finisce in un header di
 * mail (NewsletterController::subscribe() -> Mail::send()::to($subscriber
 * ->email)) era la superficie reale: un local-part quoted contenente
 * CR/LD grezzi, se accettato dal validatore RFC822 come indirizzo
 * valido, avrebbe permesso di iniettare header aggiuntivi (es. Bcc:) nel
 * messaggio inviato. La versione corretta rifiuta qualunque valore che
 * contenga \r o \n prima ancora di interpellare il parser RFC822 (vedi
 * Illuminate\Validation\Concerns\ValidatesAttributes::validateEmail()).
 */
class NewsletterCrlfInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_containing_a_crlf_header_injection_payload_is_rejected(): void
    {
        Mail::fake();

        $payload = "\"attacker\r\nBcc: victim@evil.test\"@example.com";

        $response = $this->from('/')->post('/newsletter/subscribe', [
            'email' => $payload,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('newsletter', 0);
        Mail::assertNothingSent();
    }

    public function test_an_email_containing_a_bare_lf_injection_payload_is_rejected(): void
    {
        Mail::fake();

        $payload = "\"attacker\nBcc: victim@evil.test\"@example.com";

        $response = $this->from('/')->post('/newsletter/subscribe', [
            'email' => $payload,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('newsletter', 0);
        Mail::assertNothingSent();
    }

    // Baseline di non-regressione: un indirizzo legittimo continua a
    // funzionare normalmente dopo l'aggiornamento di laravel/framework.
    public function test_a_legitimate_email_is_still_accepted_and_subscribes(): void
    {
        Mail::fake();

        $response = $this->from('/')->post('/newsletter/subscribe', [
            'email' => 'reader@example.com',
        ]);

        $response->assertSessionDoesntHaveErrors('email');
        $this->assertDatabaseHas('newsletter', [
            'email' => 'reader@example.com',
        ]);
    }

    public function test_the_honeypot_still_short_circuits_before_validation_runs(): void
    {
        Mail::fake();

        $response = $this->from('/')->post('/newsletter/subscribe', [
            'email' => 'reader@example.com',
            'website' => 'https://bot.example.test',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseCount('newsletter', 0);
        Mail::assertNothingSent();
    }
}
