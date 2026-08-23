<?php

namespace Tests\Unit\Communication;

use App\Mail\CommunicationTestSendMailable;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\MailerEmailProvider;
use App\Services\Communication\RenderedCampaignMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * MailerEmailProvider è l'unica implementazione REALE di
 * EmailDeliveryProvider in questo repository. Mail::fake() intercetta
 * Mail::to()->send() prima di qualunque transport reale — questi test
 * non usano mai credenziali reali, anche se presenti in .env, esattamente
 * come richiesto dalla missione (Provider Abstraction + Safe Test Send).
 */
class MailerEmailProviderTest extends TestCase
{
    use RefreshDatabase;

    private function rendered(array $overrides = []): RenderedCampaignMessage
    {
        return new RenderedCampaignMessage(
            subject: $overrides['subject'] ?? 'Oggetto di prova',
            preheader: null,
            html: $overrides['html'] ?? '<p>Corpo HTML di prova.</p>',
            text: $overrides['text'] ?? 'Corpo testo di prova.',
            fromName: $overrides['fromName'] ?? 'Kairus',
            fromEmail: $overrides['fromEmail'] ?? 'redazione@kairus.test',
            replyTo: $overrides['replyTo'] ?? null,
            recipientSubscriberId: $overrides['recipientSubscriberId'] ?? 1,
            recipientEmail: array_key_exists('recipientEmail', $overrides) ? $overrides['recipientEmail'] : 'iscritto@example.test',
            isPlaceholderRecipient: false,
            campaignId: 1,
            campaignUuid: 'uuid-di-prova',
            unsubscribeUrl: 'https://kairus.test/disiscrizione/token',
            idempotencyKey: 'idempotency-key-di-prova',
        );
    }

    public function test_it_sends_a_real_mailable_via_the_configured_mailer_and_returns_accepted(): void
    {
        Mail::fake();

        $result = (new MailerEmailProvider)->deliver($this->rendered());

        $this->assertTrue($result->isAccepted());
        Mail::assertSent(CommunicationTestSendMailable::class, function ($mail) {
            return $mail->hasTo('iscritto@example.test')
                && $mail->rendered->subject === 'Oggetto di prova';
        });
    }

    public function test_it_never_reaches_a_real_transport_because_mail_fake_intercepts_first(): void
    {
        Mail::fake();

        (new MailerEmailProvider)->deliver($this->rendered());

        // Nessuna email realmente "inviata" nel senso di un vero transport:
        // Mail::fake() sostituisce l'intero Mailer, quindi nessuna
        // credenziale reale (anche se presente in .env) viene mai
        // interpellata — questo è l'unico modo con cui questa classe può
        // essere collaudata in sicurezza.
        Mail::assertSent(CommunicationTestSendMailable::class);
        Mail::assertSentCount(1);
    }

    public function test_missing_recipient_email_is_a_permanent_failure_and_sends_nothing(): void
    {
        Mail::fake();

        $result = (new MailerEmailProvider)->deliver($this->rendered(['recipientEmail' => null]));

        $this->assertTrue($result->isPermanentFailure());
        $this->assertSame('missing_recipient_email', $result->reason);
        Mail::assertNothingSent();
    }

    public function test_the_idempotency_key_is_carried_through_to_the_delivery_result(): void
    {
        Mail::fake();

        $result = (new MailerEmailProvider)->deliver($this->rendered());

        $this->assertSame('idempotency-key-di-prova', $result->idempotencyKey);
    }

    public function test_html_and_text_bodies_reach_the_real_mailable_unaltered(): void
    {
        Mail::fake();

        (new MailerEmailProvider)->deliver($this->rendered([
            'html' => '<p>Contenuto HTML unico di prova.</p>',
            'text' => 'Contenuto testo unico di prova.',
        ]));

        Mail::assertSent(CommunicationTestSendMailable::class, function ($mail) {
            return $mail->rendered->html === '<p>Contenuto HTML unico di prova.</p>'
                && $mail->rendered->text === 'Contenuto testo unico di prova.';
        });
    }

    public function test_delivery_result_status_matches_the_communication_delivery_result_vocabulary(): void
    {
        Mail::fake();

        $result = (new MailerEmailProvider)->deliver($this->rendered());

        $this->assertSame(DeliveryResult::STATUS_ACCEPTED, $result->status);
    }
}
