<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDryRunService;
use App\Services\Communication\CampaignFreezeService;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;
use Tests\TestCase;

class CampaignDryRunTest extends TestCase
{
    use RefreshDatabase;

    private function dryRunService(): CampaignDryRunService
    {
        return app(CampaignDryRunService::class);
    }

    private function draftCampaignWithQueuedRecipients(int $count = 1): CommunicationCampaign
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
        ]);

        for ($i = 0; $i < $count; $i++) {
            CommunicationSend::create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
                'status' => CommunicationSend::STATUS_QUEUED,
            ]);
        }

        return $campaign;
    }

    public function test_the_run_method_only_accepts_a_recording_email_provider_by_type(): void
    {
        // Garanzia a livello di firma, non solo di convenzione: un
        // dry-run con un provider diverso da un fake non deve nemmeno
        // poter compilare. Verifica via reflection sul type hint reale.
        $method = new ReflectionMethod(CampaignDryRunService::class, 'run');
        $providerParam = $method->getParameters()[1];

        $this->assertSame(RecordingEmailProvider::class, $providerParam->getType()?->getName());
    }

    public function test_dry_run_reports_the_six_canonical_counters(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(3);

        $report = $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        $this->assertSame([
            'eligible' => 3,
            'skipped' => 0,
            'rendered' => 3,
            'accepted' => 3,
            'transient_failed' => 0,
            'permanent_failed' => 0,
        ], $report->toArray());
    }

    public function test_dry_run_covers_the_full_failure_distribution(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(0);

        $accepted = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $transient = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $permanent = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        // Un iscritto disiscritto DOPO lo snapshot fallisce in
        // revalidazione (mai "skipped" — quella categoria è riservata a
        // corse di claim perse, vedi SendProcessingOutcome) e ricade
        // nel ramo "default" di CampaignRunReport::withOutcome(),
        // quindi conta come renderizzato + fallito definitivo.
        $ineligibleSubscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $revalidationFailure = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $ineligibleSubscriber->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $ineligibleSubscriber->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED]);

        $provider = new RecordingEmailProvider;
        $provider->resolveUsing(function ($message) use ($accepted, $transient, $permanent) {
            $subscriberId = $message->recipientSubscriberId;

            return match ($subscriberId) {
                $accepted->subscriber_id => new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'ok', idempotencyKey: $message->idempotencyKey),
                $transient->subscriber_id => new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, providerMessageId: null, idempotencyKey: $message->idempotencyKey, reason: 'timeout'),
                $permanent->subscriber_id => new DeliveryResult(status: DeliveryResult::STATUS_PERMANENT_FAILURE, providerMessageId: null, idempotencyKey: $message->idempotencyKey, reason: 'rejected'),
                default => new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'ok', idempotencyKey: $message->idempotencyKey),
            };
        });

        $report = $this->dryRunService()->run($campaign, $provider);

        $this->assertSame(4, $report->eligible);
        $this->assertSame(0, $report->skipped);
        $this->assertSame(1, $report->accepted);
        // Il resolver risponde SEMPRE transient_failure per questo
        // iscritto: il dry-run ora rielabora la coda round dopo round
        // (correzione N2.14 di runCampaign(), la stessa macchina usata
        // dal dry-run) esattamente come un invio reale — 2 tentativi
        // ritentati (round 1 e 2) prima di esaurire i tentativi al
        // round 3, dove diventa un fallimento definitivo.
        $this->assertSame(2, $report->transientFailed);
        // Il fallimento esplicito del provider (rejected) + il fallimento
        // di revalidazione (iscritto non più confermato) + il transient
        // esaurito (max_attempts_exceeded) sommano a tre.
        $this->assertSame(3, $report->permanentFailed);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $revalidationFailure->fresh()->status);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $transient->fresh()->status);
    }

    /**
     * Batch 04B Mission 9 — "zero-eligible": nessuna riga comm_sends
     * preparata per questa campagna. Il dry-run deve restare sicuro e
     * riportare zero ovunque, non fallire né inventare un conteggio.
     */
    public function test_dry_run_on_a_campaign_with_zero_prepared_recipients_reports_all_zeros(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(0);

        $report = $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        $this->assertSame([
            'eligible' => 0,
            'skipped' => 0,
            'rendered' => 0,
            'accepted' => 0,
            'transient_failed' => 0,
            'permanent_failed' => 0,
        ], $report->toArray());
    }

    /**
     * Batch 04B Mission 9 — "frozen-snapshot-still-live-validated": il
     * dry-run è la simulazione dell'intera pipeline reale (stesso
     * CampaignDeliveryOrchestrator), quindi deve dimostrare la stessa
     * garanzia già provata a livello di orchestratore puro da
     * CampaignFreezeZeroSendRegressionTest — congelare una campagna non
     * indebolisce la rivalutazione live: un iscritto che si disiscrive
     * DOPO il congelamento continua a risultare correttamente escluso
     * anche quando lo si osserva attraverso lo schermo di dry-run, non
     * solo attraverso l'orchestratore chiamato direttamente.
     */
    public function test_dry_run_on_a_frozen_campaign_still_excludes_a_subscriber_who_unsubscribed_after_freeze(): void
    {
        $campaign = CommunicationCampaign::factory()->scheduled()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Aggiornamento congelato',
            'content' => ['body' => 'Contenuto bloccato al congelamento.'],
        ]);

        $staysConfirmed = CommunicationSubscriber::factory()->confirmed()->create();
        $unsubscribesAfterFreeze = CommunicationSubscriber::factory()->confirmed()->create();

        foreach ([$staysConfirmed, $unsubscribesAfterFreeze] as $subscriber) {
            CommunicationSend::create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'status' => CommunicationSend::STATUS_QUEUED,
            ]);
        }

        app(CampaignFreezeService::class)->freeze($campaign, null);
        $this->assertTrue($campaign->fresh()->isFrozen());

        $unsubscribesAfterFreeze->update([
            'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        $report = $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        $this->assertSame(2, $report->eligible);
        $this->assertSame(1, $report->accepted);
        $this->assertSame(1, $report->permanentFailed);

        // Il congelamento resta intatto e nessuna riga comm_sends è stata
        // realmente modificata dal dry-run (rollback garantito da
        // CampaignDryRunService), a differenza del test equivalente che
        // esercita l'orchestratore reale.
        $this->assertTrue($campaign->fresh()->isFrozen());
        $this->assertSame(
            CommunicationSend::STATUS_QUEUED,
            CommunicationSend::where('campaign_id', $campaign->id)->where('subscriber_id', $unsubscribesAfterFreeze->id)->firstOrFail()->status
        );
    }

    public function test_dry_run_never_persists_any_mutation_to_campaign_or_sends(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(2);
        $send = CommunicationSend::where('campaign_id', $campaign->id)->first();

        $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->sending_started_at);
        $this->assertNull($campaign->fresh()->completed_at);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $send->fresh()->status);
        $this->assertSame(0, $send->fresh()->attempts);
        $this->assertNull($send->fresh()->sent_at);
    }

    public function test_the_campaign_passed_by_reference_reflects_the_rolled_back_state_not_the_in_flight_one(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(1);

        $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        // Durante l'esecuzione l'istanza sarebbe temporaneamente
        // 'completed' (stessa strada di runCampaign()) — dopo il
        // rollback deve tornare a riflettere lo stato reale su disco,
        // mai mentire al chiamante.
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->status);
    }

    public function test_dry_run_is_repeatable_with_identical_results(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(5);

        $first = $this->dryRunService()->run($campaign, new RecordingEmailProvider);
        $second = $this->dryRunService()->run($campaign->fresh(), new RecordingEmailProvider);

        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_dry_run_never_touches_mail_notification_or_queue(): void
    {
        Mail::fake();
        Notification::fake();
        Bus::fake();

        $campaign = $this->draftCampaignWithQueuedRecipients(2);

        $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    public function test_dry_run_source_never_references_a_real_provider_or_transport(): void
    {
        $source = file_get_contents(app_path('Services/Communication/CampaignDryRunService.php'));

        foreach (['Mail::', 'Notification::', 'Bus::', 'Http::', 'curl_', 'fsockopen', 'Swift_', 'Symfony\\Component\\Mailer'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    /**
     * Review manuale mirata sul commit 3298098 (requisito 4): l'unico
     * chiamante di produzione di runCampaign() è questo servizio, e non
     * cattura l'eccezione da nessuna parte — solo un `finally` per il
     * rollback (sempre eseguito, esito compreso) e il refresh
     * dell'istanza in memoria. L'eccezione deve risalire fino al
     * chiamante (il controller admin, poi la risposta HTTP) senza
     * essere mai trasformata in un esito pulito né ritentata qui.
     */
    public function test_a_lost_race_during_dry_run_propagates_uncaught_while_the_transaction_still_rolls_back(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(1);
        $send = CommunicationSend::where('campaign_id', $campaign->id)->firstOrFail();

        $provider = new RecordingEmailProvider;
        $provider->resolveUsing(function () use ($send) {
            DB::table('comm_sends')->where('id', $send->id)->update(['status' => CommunicationSend::STATUS_QUEUED]);

            return new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'dry-run-race');
        });

        try {
            $this->dryRunService()->run($campaign, $provider);
            $this->fail("Il dry-run deve propagare l'eccezione, mai catturarla o restituire un report pulito.");
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('perso', $e->getMessage());
        }

        // Il rollback (finally) avviene comunque: nessuno stato fantasma
        // resta visibile, la campagna in memoria riflette il DB reale.
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->status);
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
        $this->assertSame(1, $provider->attemptCount());
    }
}
