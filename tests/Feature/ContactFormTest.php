<?php

namespace Tests\Feature;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    public function test_contact_form_uses_configured_recipient(): void
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

                $to = $message->getSymfonyMessage()->getTo();

                $this->assertCount(1, $to);
                $this->assertSame('contatti-test@example.com', $to[0]->getAddress());
                $this->assertStringContainsString('Nuovo messaggio dal form contatti di Kairus', $body);

                return true;
            });

        $response = $this->post(route('contatti.send'), [
            'nome' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'oggetto' => 'Richiesta di prova',
            'messaggio' => 'Questo è un messaggio di prova sufficientemente lungo.',
            'privacy' => '1',
        ]);

        $response->assertRedirect(route('contatti', ['sent' => '1']));
    }
}
