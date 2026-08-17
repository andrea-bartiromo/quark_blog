<?php

namespace Tests\Unit\Communication;

use App\Models\CommunicationCampaign;
use App\Services\Communication\CampaignStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CampaignStateMachineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validTransitions(): array
    {
        return [
            'draft -> scheduled' => [CommunicationCampaign::STATUS_DRAFT, CommunicationCampaign::STATUS_SCHEDULED],
            'draft -> sending' => [CommunicationCampaign::STATUS_DRAFT, CommunicationCampaign::STATUS_SENDING],
            'draft -> cancelled' => [CommunicationCampaign::STATUS_DRAFT, CommunicationCampaign::STATUS_CANCELLED],
            'scheduled -> draft' => [CommunicationCampaign::STATUS_SCHEDULED, CommunicationCampaign::STATUS_DRAFT],
            'scheduled -> sending' => [CommunicationCampaign::STATUS_SCHEDULED, CommunicationCampaign::STATUS_SENDING],
            'scheduled -> cancelled' => [CommunicationCampaign::STATUS_SCHEDULED, CommunicationCampaign::STATUS_CANCELLED],
            'sending -> completed' => [CommunicationCampaign::STATUS_SENDING, CommunicationCampaign::STATUS_COMPLETED],
            'sending -> failed' => [CommunicationCampaign::STATUS_SENDING, CommunicationCampaign::STATUS_FAILED],
            'sending -> cancelled' => [CommunicationCampaign::STATUS_SENDING, CommunicationCampaign::STATUS_CANCELLED],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'draft -> completed' => [CommunicationCampaign::STATUS_DRAFT, CommunicationCampaign::STATUS_COMPLETED],
            'draft -> failed' => [CommunicationCampaign::STATUS_DRAFT, CommunicationCampaign::STATUS_FAILED],
            'scheduled -> completed' => [CommunicationCampaign::STATUS_SCHEDULED, CommunicationCampaign::STATUS_COMPLETED],
            'sending -> draft' => [CommunicationCampaign::STATUS_SENDING, CommunicationCampaign::STATUS_DRAFT],
            'sending -> scheduled' => [CommunicationCampaign::STATUS_SENDING, CommunicationCampaign::STATUS_SCHEDULED],
            'completed -> draft' => [CommunicationCampaign::STATUS_COMPLETED, CommunicationCampaign::STATUS_DRAFT],
            'completed -> sending' => [CommunicationCampaign::STATUS_COMPLETED, CommunicationCampaign::STATUS_SENDING],
            'failed -> sending' => [CommunicationCampaign::STATUS_FAILED, CommunicationCampaign::STATUS_SENDING],
            'failed -> draft' => [CommunicationCampaign::STATUS_FAILED, CommunicationCampaign::STATUS_DRAFT],
            'cancelled -> draft' => [CommunicationCampaign::STATUS_CANCELLED, CommunicationCampaign::STATUS_DRAFT],
            'cancelled -> sending' => [CommunicationCampaign::STATUS_CANCELLED, CommunicationCampaign::STATUS_SENDING],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_valid_transition_succeeds(string $from, string $to): void
    {
        $campaign = CommunicationCampaign::factory()->create(['status' => $from]);

        $machine = new CampaignStateMachine;

        $this->assertTrue($machine->canTransition($campaign, $to));
        $this->assertTrue($machine->transition($campaign, $to));
        $this->assertSame($to, $campaign->status);
        $this->assertSame($to, $campaign->fresh()->status);
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transition_throws_and_never_writes(string $from, string $to): void
    {
        $campaign = CommunicationCampaign::factory()->create(['status' => $from]);

        $machine = new CampaignStateMachine;

        $this->assertFalse($machine->canTransition($campaign, $to));

        try {
            $machine->transition($campaign, $to);
            $this->fail("Attesa RuntimeException per {$from} -> {$to}.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($from, $e->getMessage());
            $this->assertStringContainsString($to, $e->getMessage());
        }

        $this->assertSame($from, $campaign->fresh()->status);
    }

    public function test_terminal_states_have_no_outgoing_transitions(): void
    {
        $machine = new CampaignStateMachine;

        foreach ([
            CommunicationCampaign::STATUS_COMPLETED,
            CommunicationCampaign::STATUS_FAILED,
            CommunicationCampaign::STATUS_CANCELLED,
        ] as $terminal) {
            $campaign = CommunicationCampaign::factory()->create(['status' => $terminal]);

            foreach (array_keys(CommunicationCampaign::statusOptions()) as $candidate) {
                if ($candidate === $terminal) {
                    continue;
                }

                $this->assertFalse(
                    $machine->canTransition($campaign, $candidate),
                    "{$terminal} non deve avere transizioni uscenti verso {$candidate}."
                );
            }
        }
    }

    public function test_transitioning_to_sending_sets_sending_started_at(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create(['sending_started_at' => null]);

        (new CampaignStateMachine)->transition($campaign, CommunicationCampaign::STATUS_SENDING);

        $this->assertNotNull($campaign->fresh()->sending_started_at);
    }

    public function test_transitioning_to_completed_sets_completed_at(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['status' => CommunicationCampaign::STATUS_SENDING]);

        (new CampaignStateMachine)->transition($campaign, CommunicationCampaign::STATUS_COMPLETED);

        $this->assertNotNull($campaign->fresh()->completed_at);
    }

    public function test_concurrent_conflicting_transition_loses_the_race_without_throwing(): void
    {
        // Simula una corsa: due "letture" della stessa campagna allo
        // stesso stato, la prima transiziona con successo, la seconda
        // (basata sullo stato ORMAI STANTIO) deve fallire silenziosamente
        // (false), non lanciare, e riallineare l'istanza in memoria.
        $campaign = CommunicationCampaign::factory()->create(['status' => CommunicationCampaign::STATUS_SENDING]);
        $staleCopy = CommunicationCampaign::find($campaign->id);

        $machine = new CampaignStateMachine;

        $this->assertTrue($machine->transition($campaign, CommunicationCampaign::STATUS_COMPLETED));
        $this->assertFalse($machine->transition($staleCopy, CommunicationCampaign::STATUS_FAILED));

        // L'istanza "stantia" è stata riallineata allo stato reale.
        $this->assertSame(CommunicationCampaign::STATUS_COMPLETED, $staleCopy->status);
        $this->assertSame(CommunicationCampaign::STATUS_COMPLETED, $campaign->fresh()->status);
    }

    /**
     * Red-team pre-merge (FASE 9, fuzzing esaustivo) — stesso principio
     * di SendStateMachineTest::allStatusPairs(): l'intero prodotto
     * cartesiano N×N degli stati, non i soli casi curati a mano sopra.
     * Dimostra meccanicamente che completed/failed/cancelled non
     * tornano mai a 'sending', in un solo test.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function allStatusPairs(): array
    {
        $statuses = [
            CommunicationCampaign::STATUS_DRAFT,
            CommunicationCampaign::STATUS_SCHEDULED,
            CommunicationCampaign::STATUS_SENDING,
            CommunicationCampaign::STATUS_COMPLETED,
            CommunicationCampaign::STATUS_FAILED,
            CommunicationCampaign::STATUS_CANCELLED,
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
        $campaign = CommunicationCampaign::factory()->create(['status' => $from]);
        $machine = new CampaignStateMachine;

        if ($machine->canTransition($campaign, $to)) {
            $this->assertTrue($machine->transition($campaign, $to));
            $this->assertSame($to, $campaign->fresh()->status);

            return;
        }

        $this->expectException(RuntimeException::class);
        $machine->transition($campaign, $to);
    }
}
