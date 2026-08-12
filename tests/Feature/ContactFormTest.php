<?php

namespace Tests\Feature;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    public function test_contact_form_uses_configured_recipient_and_reply_to(): void
    {
        config([
            'mail.contact_to' => 'contatti-test@example.com',
            'mail.from.address' => 'noreply-test@example.com',
        ]);

        Mail::shouldReceive('raw')
            ->once()
            ->withArgs(function (string $body, callable $callback): bool {
                $message = new Message(new Email);

                $callback($message);

                $symfonyMessage = $message->getSymfonyMessage();
                $to = $symfonyMessage->getTo();
                $replyTo = $symfonyMessage->getReplyTo();

                $this->assertCount(1, $to);
                $this->assertSame('contatti-test@example.com', $to[0]->getAddress());
                $this->assertCount(1, $replyTo);
                $this->assertSame('mario@example.com', $replyTo[0]->getAddress());
                $this->assertSame('Mario Rossi', $replyTo[0]->getName());
                $this->assertSame('[Kairus] Nuovo messaggio: Richiesta di prova', $symfonyMessage->getSubject());
                $this->assertStringContainsString('Nuovo messaggio dal form contatti di Kairus', $body);
                $this->assertStringContainsString('Email: mario@example.com', $body);

                return true;
            });

        $response = $this->post(route('contatti.send'), $this->validPayload());

        $response->assertRedirect(route('contatti', ['sent' => '1']));
    }

    public function test_missing_required_fields_do_not_send_mail(): void
    {
        Mail::shouldReceive('raw')->never();

        $response = $this->from(route('contatti'))->post(route('contatti.send'), []);

        $response->assertRedirect(route('contatti'));
        $response->assertSessionHasErrors(['nome', 'email', 'oggetto', 'messaggio', 'privacy']);
        $response->assertSessionMissing('contact_sent');
    }

    public function test_malformed_email_does_not_send_mail_and_preserves_input(): void
    {
        Mail::shouldReceive('raw')->never();

        $payload = $this->validPayload(['email' => 'not-an-email']);
        $response = $this->from(route('contatti'))->post(route('contatti.send'), $payload);

        $response->assertRedirect(route('contatti'));
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHasInput('nome', $payload['nome']);
        $response->assertSessionHasInput('email', $payload['email']);
        $response->assertSessionHasInput('oggetto', $payload['oggetto']);
        $response->assertSessionHasInput('messaggio', $payload['messaggio']);
        $response->assertSessionMissing('contact_sent');
    }

    public function test_message_shorter_than_twenty_characters_does_not_send_mail(): void
    {
        Mail::shouldReceive('raw')->never();

        $payload = $this->validPayload(['messaggio' => str_repeat('x', 19)]);
        $response = $this->from(route('contatti'))->post(route('contatti.send'), $payload);

        $response->assertRedirect(route('contatti'));
        $response->assertSessionHasErrors(['messaggio']);
        $response->assertSessionHasInput('messaggio', $payload['messaggio']);
        $response->assertSessionMissing('contact_sent');
    }

    public function test_privacy_must_be_accepted_before_sending_mail(): void
    {
        Mail::shouldReceive('raw')->never();

        $payload = $this->validPayload();
        unset($payload['privacy']);

        $response = $this->from(route('contatti'))->post(route('contatti.send'), $payload);

        $response->assertRedirect(route('contatti'));
        $response->assertSessionHasErrors(['privacy']);
        $response->assertSessionHasInput('nome', $payload['nome']);
        $response->assertSessionHasInput('email', $payload['email']);
        $response->assertSessionMissing('contact_sent');
    }

    public function test_mail_transport_exception_does_not_expose_internal_details(): void
    {
        $marker = 'smtp-secret-marker';

        Mail::shouldReceive('raw')
            ->once()
            ->andThrow(new RuntimeException($marker));

        $payload = $this->validPayload();
        $response = $this->from(route('contatti'))->post(route('contatti.send'), $payload);

        $response->assertRedirect(route('contatti'));
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHasInput('nome', $payload['nome']);
        $response->assertSessionHasInput('email', $payload['email']);
        $response->assertSessionHasInput('oggetto', $payload['oggetto']);
        $response->assertSessionHasInput('messaggio', $payload['messaggio']);
        $response->assertSessionMissing('contact_sent');

        $errors = session('errors')->get('email');
        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsString($marker, implode(' ', $errors));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nome' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'oggetto' => 'Richiesta di prova',
            'messaggio' => 'Questo è un messaggio di prova sufficientemente lungo.',
            'privacy' => '1',
        ], $overrides);
    }
}
