<?php

namespace Tests\Unit\Communication;

use App\Services\Communication\DeliveryResult;
use App\Services\Communication\NullEmailProvider;
use App\Services\Communication\RecordingEmailProvider;
use App\Services\Communication\RenderedCampaignMessage;
use PHPUnit\Framework\TestCase;

class RecordingEmailProviderTest extends TestCase
{
    private function message(string $idempotencyKey = 'key-1'): RenderedCampaignMessage
    {
        return new RenderedCampaignMessage(
            subject: 'Oggetto',
            preheader: null,
            html: '<p>Corpo</p>',
            text: 'Corpo',
            fromName: 'Kairus',
            fromEmail: 'no-reply@kairus.it',
            replyTo: null,
            recipientSubscriberId: 1,
            recipientEmail: 'destinatario@example.com',
            isPlaceholderRecipient: false,
            campaignId: 1,
            campaignUuid: 'uuid-1',
            unsubscribeUrl: 'https://kairus.it/disiscrizione/token',
            idempotencyKey: $idempotencyKey,
        );
    }

    public function test_default_behavior_always_accepts(): void
    {
        $provider = new RecordingEmailProvider;

        $result = $provider->deliver($this->message());

        $this->assertTrue($result->isAccepted());
        $this->assertNotNull($result->providerMessageId);
        $this->assertSame('key-1', $result->idempotencyKey);
    }

    public function test_records_every_attempt_in_memory(): void
    {
        $provider = new RecordingEmailProvider;

        $provider->deliver($this->message('key-1'));
        $provider->deliver($this->message('key-2'));
        $provider->deliver($this->message('key-3'));

        $this->assertSame(3, $provider->attemptCount());
        $this->assertCount(3, $provider->attempts());
        $this->assertCount(3, $provider->results());
        $this->assertSame('key-2', $provider->attempts()[1]->idempotencyKey);
    }

    public function test_will_return_queues_canned_results_in_fifo_order(): void
    {
        $provider = (new RecordingEmailProvider)->willReturn(
            new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED),
            new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout simulato'),
            new DeliveryResult(status: DeliveryResult::STATUS_PERMANENT_FAILURE, reason: 'indirizzo non valido'),
        );

        $first = $provider->deliver($this->message());
        $second = $provider->deliver($this->message());
        $third = $provider->deliver($this->message());
        $fourth = $provider->deliver($this->message());

        $this->assertTrue($first->isAccepted());
        $this->assertTrue($second->isTransientFailure());
        $this->assertTrue($third->isPermanentFailure());
        // Coda esaurita: torna al default (accepted).
        $this->assertTrue($fourth->isAccepted());
    }

    public function test_resolve_using_takes_precedence_over_queued_results(): void
    {
        $provider = (new RecordingEmailProvider)
            ->willReturn(new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED))
            ->resolveUsing(fn (RenderedCampaignMessage $m) => new DeliveryResult(
                status: DeliveryResult::STATUS_REJECTED,
                reason: 'sempre rifiutato per test',
            ));

        $result = $provider->deliver($this->message());

        $this->assertTrue($result->isPermanentFailure());
        $this->assertSame('sempre rifiutato per test', $result->reason);
    }

    public function test_reset_clears_attempts_results_and_configuration(): void
    {
        $provider = (new RecordingEmailProvider)->willReturn(
            new DeliveryResult(status: DeliveryResult::STATUS_REJECTED)
        );
        $provider->deliver($this->message());

        $provider->reset();

        $this->assertSame(0, $provider->attemptCount());
        $this->assertSame([], $provider->results());

        // Configurazione ripulita: torna al default (accepted).
        $result = $provider->deliver($this->message());
        $this->assertTrue($result->isAccepted());
    }

    public function test_null_provider_always_accepts_and_records_nothing(): void
    {
        $provider = new NullEmailProvider;

        $result = $provider->deliver($this->message('key-x'));

        $this->assertTrue($result->isAccepted());
        $this->assertSame('key-x', $result->idempotencyKey);
    }

    /**
     * Prova statica che NullEmailProvider e RecordingEmailProvider — gli
     * UNICI due provider implementati in questa missione — non
     * contengono alcuna chiamata di rete o di invio reale. Una guardia
     * di regressione permanente: se in futuro qualcuno aggiungesse una
     * vera chiamata HTTP/SMTP a queste classi "fake", questo test
     * fallisce immediatamente.
     */
    public function test_fake_provider_source_contains_no_network_or_mail_calls(): void
    {
        $forbidden = [
            'curl_init', 'curl_exec', 'fsockopen', 'pfsockopen',
            'stream_socket_client', 'stream_socket_server',
            'Http::', 'GuzzleHttp', 'Mail::', 'Notification::',
            'mail(', 'imap_open', 'ftp_connect', 'dns_get_record', 'getmxrr',
        ];

        foreach ([
            __DIR__.'/../../../app/Services/Communication/NullEmailProvider.php',
            __DIR__.'/../../../app/Services/Communication/RecordingEmailProvider.php',
        ] as $file) {
            $source = file_get_contents($file);
            $this->assertNotFalse($source, "Impossibile leggere {$file}.");

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($file)." contiene un riferimento vietato a rete/invio reale: '{$needle}'."
                );
            }
        }
    }
}
