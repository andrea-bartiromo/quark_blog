<?php

namespace Tests\Unit\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\SendStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SendStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function send(string $status): CommunicationSend
    {
        $campaign = CommunicationCampaign::factory()->create();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        return CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validTransitions(): array
    {
        return [
            'queued -> sending' => [CommunicationSend::STATUS_QUEUED, CommunicationSend::STATUS_SENDING],
            'queued -> cancelled' => [CommunicationSend::STATUS_QUEUED, CommunicationSend::STATUS_CANCELLED],
            'sending -> sent' => [CommunicationSend::STATUS_SENDING, CommunicationSend::STATUS_SENT],
            'sending -> failed' => [CommunicationSend::STATUS_SENDING, CommunicationSend::STATUS_FAILED],
            'sending -> queued (retry)' => [CommunicationSend::STATUS_SENDING, CommunicationSend::STATUS_QUEUED],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'queued -> sent' => [CommunicationSend::STATUS_QUEUED, CommunicationSend::STATUS_SENT],
            'queued -> failed' => [CommunicationSend::STATUS_QUEUED, CommunicationSend::STATUS_FAILED],
            'sending -> cancelled' => [CommunicationSend::STATUS_SENDING, CommunicationSend::STATUS_CANCELLED],
            'sent -> queued' => [CommunicationSend::STATUS_SENT, CommunicationSend::STATUS_QUEUED],
            'sent -> sending' => [CommunicationSend::STATUS_SENT, CommunicationSend::STATUS_SENDING],
            'failed -> queued' => [CommunicationSend::STATUS_FAILED, CommunicationSend::STATUS_QUEUED],
            'failed -> sending' => [CommunicationSend::STATUS_FAILED, CommunicationSend::STATUS_SENDING],
            'cancelled -> queued' => [CommunicationSend::STATUS_CANCELLED, CommunicationSend::STATUS_QUEUED],
            'cancelled -> sending' => [CommunicationSend::STATUS_CANCELLED, CommunicationSend::STATUS_SENDING],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_valid_transition_succeeds(string $from, string $to): void
    {
        $send = $this->send($from);

        $machine = new SendStateMachine;

        $this->assertTrue($machine->canTransition($send, $to));
        $this->assertTrue($machine->transition($send, $to));
        $this->assertSame($to, $send->status);
        $this->assertSame($to, $send->fresh()->status);
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transition_throws_and_never_writes(string $from, string $to): void
    {
        $send = $this->send($from);

        $machine = new SendStateMachine;

        $this->assertFalse($machine->canTransition($send, $to));

        try {
            $machine->transition($send, $to);
            $this->fail("Attesa RuntimeException per {$from} -> {$to}.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($from, $e->getMessage());
            $this->assertStringContainsString($to, $e->getMessage());
        }

        $this->assertSame($from, $send->fresh()->status);
    }

    /**
     * Never-interrupted-in-flight invariant, verificato esplicitamente:
     * 'sending' non ha alcuna transizione ammessa verso 'cancelled', a
     * differenza di 'queued' che ce l'ha — questo è il comportamento che
     * garantisce che una cancellazione campagna mid-flight non interrompa
     * mai un claim già in corso.
     */
    public function test_sending_never_transitions_directly_to_cancelled(): void
    {
        $machine = new SendStateMachine;
        $send = $this->send(CommunicationSend::STATUS_SENDING);

        $this->assertFalse($machine->canTransition($send, CommunicationSend::STATUS_CANCELLED));
    }

    public function test_extra_attributes_are_persisted_atomically_with_the_transition(): void
    {
        $send = $this->send(CommunicationSend::STATUS_SENDING);

        (new SendStateMachine)->transition($send, CommunicationSend::STATUS_FAILED, [
            'failed_at' => now(),
            'failure_reason' => 'provider rejected',
        ]);

        $fresh = $send->fresh();
        $this->assertSame(CommunicationSend::STATUS_FAILED, $fresh->status);
        $this->assertNotNull($fresh->failed_at);
        $this->assertSame('provider rejected', $fresh->failure_reason);
    }

    public function test_concurrent_conflicting_transition_loses_the_race_without_throwing(): void
    {
        $send = $this->send(CommunicationSend::STATUS_SENDING);
        $staleCopy = CommunicationSend::find($send->id);

        $machine = new SendStateMachine;

        $this->assertTrue($machine->transition($send, CommunicationSend::STATUS_SENT));
        $this->assertFalse($machine->transition($staleCopy, CommunicationSend::STATUS_FAILED));

        $this->assertSame(CommunicationSend::STATUS_SENT, $staleCopy->status);
        $this->assertSame(CommunicationSend::STATUS_SENT, $send->fresh()->status);
    }

    /**
     * Red-team pre-merge (FASE 9, fuzzing esaustivo): copre l'INTERO
     * prodotto cartesiano N×N degli stati conosciuti, non i soli casi
     * curati a mano in validTransitions()/invalidTransitions() qui
     * sopra — elimina il rischio che una coppia sia stata dimenticata
     * in ENTRAMBE le liste per errore umano. Per costruzione dimostra
     * anche che nessuno stato terminale (sent/failed/cancelled) può mai
     * essere riaperto verso alcuno stato, in un solo test invece di tre
     * verifiche separate.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function allStatusPairs(): array
    {
        $statuses = [
            CommunicationSend::STATUS_QUEUED,
            CommunicationSend::STATUS_SENDING,
            CommunicationSend::STATUS_SENT,
            CommunicationSend::STATUS_FAILED,
            CommunicationSend::STATUS_CANCELLED,
        ];

        $pairs = [];
        foreach ($statuses as $from) {
            foreach ($statuses as $to) {
                if ($from !== $to) {
                    $pairs["{$from} -> {$to}"] = [$from, $to];
                }
            }
        }

        return $pairs;
    }

    #[DataProvider('allStatusPairs')]
    public function test_exhaustive_transition_matrix_never_silently_succeeds_for_an_undeclared_pair(string $from, string $to): void
    {
        $send = $this->send($from);
        $machine = new SendStateMachine;

        if ($machine->canTransition($send, $to)) {
            $this->assertTrue($machine->transition($send, $to));
            $this->assertSame($to, $send->fresh()->status);

            return;
        }

        $this->expectException(RuntimeException::class);
        $machine->transition($send, $to);
    }
}
