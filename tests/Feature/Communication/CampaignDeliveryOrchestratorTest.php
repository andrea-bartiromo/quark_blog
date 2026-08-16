<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDeliveryOrchestrator;
use App\Services\Communication\CampaignRenderer;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RecordingEmailProvider;
use App\Services\Communication\SendProcessingOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CampaignDeliveryOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private function orchestrator(): CampaignDeliveryOrchestrator
    {
        return app(CampaignDeliveryOrchestrator::class);
    }

    private function sendingCampaign(?CommunicationSenderProfile $senderProfile = null): CommunicationCampaign
    {
        return CommunicationCampaign::factory()->create([
            'status' => CommunicationCampaign::STATUS_SENDING,
            'sender_profile_id' => ($senderProfile ?? CommunicationSenderProfile::factory()->create())->id,
        ]);
    }

    private function queuedSend(CommunicationCampaign $campaign, ?CommunicationSubscriber $subscriber = null): CommunicationSend
    {
        return CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => ($subscriber ?? CommunicationSubscriber::factory()->confirmed()->create())->id,
            'status' => CommunicationSend::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
    }

    public function test_happy_path_accepted_marks_send_as_sent(): void
    {
        $send = $this->queuedSend($this->sendingCampaign());
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame(SendProcessingOutcome::SENT, $outcome->outcome);
        $fresh = $send->fresh();
        $this->assertSame(CommunicationSend::STATUS_SENT, $fresh->status);
        $this->assertNotNull($fresh->sent_at);
        $this->assertNotNull($fresh->provider_message_id);
        $this->assertSame(1, $fresh->attempts);
        $this->assertSame(1, $provider->attemptCount());
    }

    public function test_already_sending_send_is_skipped_not_double_processed(): void
    {
        $send = $this->queuedSend($this->sendingCampaign());
        $send->update(['status' => CommunicationSend::STATUS_SENDING]);
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame(SendProcessingOutcome::SKIPPED, $outcome->outcome);
        $this->assertSame(0, $provider->attemptCount());
        $this->assertSame(CommunicationSend::STATUS_SENDING, $send->fresh()->status);
    }

    public function test_subscriber_unsubscribed_after_queue_fails_without_calling_the_provider(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $send = $this->queuedSend($this->sendingCampaign(), $subscriber);
        $subscriber->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame(SendProcessingOutcome::FAILED, $outcome->outcome);
        $this->assertSame('subscriber_not_eligible', $outcome->reason);
        $this->assertSame(0, $provider->attemptCount(), 'Il provider fake non deve mai essere invocato per un iscritto non più eleggibile.');
        $fresh = $send->fresh();
        $this->assertSame(CommunicationSend::STATUS_FAILED, $fresh->status);
        $this->assertSame('subscriber_not_eligible', $fresh->failure_reason);
    }

    public function test_pending_subscriber_is_not_eligible(): void
    {
        $subscriber = CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_PENDING]);
        $send = $this->queuedSend($this->sendingCampaign(), $subscriber);
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame('subscriber_not_eligible', $outcome->reason);
        $this->assertSame(0, $provider->attemptCount());
    }

    public function test_campaign_no_longer_sending_fails_without_calling_the_provider(): void
    {
        $campaign = $this->sendingCampaign();
        $send = $this->queuedSend($campaign);
        // Simula una riga già reclamata da un worker precedente, poi la
        // campagna passa a 'completed' altrove prima che questo worker
        // riprenda l'elaborazione.
        $send->update(['status' => CommunicationSend::STATUS_QUEUED]);
        $campaign->update(['status' => CommunicationCampaign::STATUS_COMPLETED, 'completed_at' => now()]);
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame('campaign_not_sendable', $outcome->reason);
        $this->assertSame(0, $provider->attemptCount());
    }

    public function test_deleted_campaign_fails_without_calling_the_provider(): void
    {
        $campaign = $this->sendingCampaign();
        $send = $this->queuedSend($campaign);
        $campaign->delete();
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame('campaign_not_sendable', $outcome->reason);
        $this->assertSame(0, $provider->attemptCount());
    }

    public function test_archived_sender_profile_fails_without_calling_the_provider(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create(['status' => CommunicationSenderProfile::STATUS_ARCHIVED]);
        $send = $this->queuedSend($this->sendingCampaign($senderProfile));
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame('sender_invalid', $outcome->reason);
        $this->assertSame(0, $provider->attemptCount());
    }

    public function test_missing_sender_profile_fails_without_calling_the_provider(): void
    {
        $campaign = CommunicationCampaign::factory()->create([
            'status' => CommunicationCampaign::STATUS_SENDING,
            'sender_profile_id' => null,
        ]);
        $send = $this->queuedSend($campaign);
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame('sender_invalid', $outcome->reason);
        $this->assertSame(0, $provider->attemptCount());
    }

    public function test_render_exception_fails_immediately_without_calling_the_provider(): void
    {
        // Corpo non renderizzabile (array invece di stringa) forza
        // un'eccezione Blade lato rendering, prima di qualunque chiamata
        // al provider.
        $campaign = $this->sendingCampaign();
        $campaign->update(['content' => ['body' => ['non', 'una', 'stringa']]]);
        $send = $this->queuedSend($campaign);
        $provider = new RecordingEmailProvider;

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame('render_exception', $outcome->reason);
        $this->assertSame(0, $provider->attemptCount());
        $this->assertSame(CommunicationSend::STATUS_FAILED, $send->fresh()->status);
    }

    /**
     * Colma un gap esplicitamente segnalato dalla missione N2.10: un
     * timeout/crash del provider stesso (non un DeliveryResult di
     * fallimento, un'eccezione PHP vera e propria durante la chiamata)
     * deve propagare, mai essere catturato — la riga resta 'sending'
     * deliberatamente, la stessa ambiguità reale già documentata nel
     * docblock della classe e in CommunicationDelivery. Solo
     * StaleSendRecoveryService (revisione MANUALE, mai automatica) può
     * sbloccarla in seguito.
     */
    public function test_a_provider_exception_propagates_and_leaves_the_row_deliberately_sending(): void
    {
        $send = $this->queuedSend($this->sendingCampaign());
        $provider = new RecordingEmailProvider;
        $provider->resolveUsing(function () {
            throw new \RuntimeException('timeout simulato dal provider fake');
        });

        try {
            $this->orchestrator()->processSend($send, $provider);
            $this->fail("Un'eccezione dal provider deve propagare, mai essere catturata silenziosamente.");
        } catch (\RuntimeException $e) {
            $this->assertSame('timeout simulato dal provider fake', $e->getMessage());
        }

        $this->assertSame(CommunicationSend::STATUS_SENDING, $send->fresh()->status);
    }

    public function test_transient_failure_is_retried_and_returns_to_queued(): void
    {
        $send = $this->queuedSend($this->sendingCampaign());
        $provider = (new RecordingEmailProvider)->willReturn(
            new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout simulato')
        );

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame(SendProcessingOutcome::RETRIED, $outcome->outcome);
        $fresh = $send->fresh();
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertSame('timeout simulato', $fresh->failure_reason);
        $this->assertNull($fresh->sent_at);
    }

    public function test_transient_failure_exceeding_max_attempts_fails_permanently(): void
    {
        $send = $this->queuedSend($this->sendingCampaign());
        $provider = new RecordingEmailProvider;
        $orchestrator = $this->orchestrator();

        // 3 tentativi consecutivi, sempre transient failure: al terzo,
        // max attempts (default 3) è raggiunto e il fallimento diventa
        // definitivo.
        $provider->willReturn(
            new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout 1'),
            new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout 2'),
            new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout 3'),
        );

        $first = $orchestrator->processSend($send->fresh(), $provider);
        $this->assertSame(SendProcessingOutcome::RETRIED, $first->outcome);

        $second = $orchestrator->processSend($send->fresh(), $provider);
        $this->assertSame(SendProcessingOutcome::RETRIED, $second->outcome);

        $third = $orchestrator->processSend($send->fresh(), $provider);
        $this->assertSame(SendProcessingOutcome::FAILED, $third->outcome);
        $this->assertSame('max_attempts_exceeded', $third->reason);

        $fresh = $send->fresh();
        $this->assertSame(CommunicationSend::STATUS_FAILED, $fresh->status);
        $this->assertSame(3, $fresh->attempts);
        $this->assertSame(3, $provider->attemptCount());
    }

    public function test_permanent_failure_never_retries(): void
    {
        $send = $this->queuedSend($this->sendingCampaign());
        $provider = (new RecordingEmailProvider)->willReturn(
            new DeliveryResult(status: DeliveryResult::STATUS_PERMANENT_FAILURE, reason: 'configurazione non valida')
        );

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame(SendProcessingOutcome::FAILED, $outcome->outcome);
        $fresh = $send->fresh();
        $this->assertSame(CommunicationSend::STATUS_FAILED, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertSame('configurazione non valida', $fresh->failure_reason);
    }

    public function test_rejected_never_retries(): void
    {
        $send = $this->queuedSend($this->sendingCampaign());
        $provider = (new RecordingEmailProvider)->willReturn(
            new DeliveryResult(status: DeliveryResult::STATUS_REJECTED, reason: 'indirizzo non valido')
        );

        $outcome = $this->orchestrator()->processSend($send, $provider);

        $this->assertSame(SendProcessingOutcome::FAILED, $outcome->outcome);
        $this->assertSame(CommunicationSend::STATUS_FAILED, $send->fresh()->status);
    }

    public function test_cancel_campaign_marks_queued_sends_cancelled_and_never_processes_them(): void
    {
        $campaign = $this->sendingCampaign();
        $stillQueued = $this->queuedSend($campaign);
        $provider = new RecordingEmailProvider;

        $cancelled = $this->orchestrator()->cancelCampaign($campaign);

        $this->assertTrue($cancelled);
        $this->assertSame(CommunicationCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
        $this->assertSame(CommunicationSend::STATUS_CANCELLED, $stillQueued->fresh()->status);

        // Un tentativo di processare comunque la riga (già cancellata)
        // deve essere respinto dalla state machine, mai chiamare il
        // provider.
        $outcome = $this->orchestrator()->processSend($stillQueued->fresh(), $provider);
        $this->assertSame(SendProcessingOutcome::SKIPPED, $outcome->outcome);
        $this->assertSame(0, $provider->attemptCount());
    }

    public function test_campaign_cancelled_mid_batch_fails_remaining_queued_sends_via_natural_revalidation(): void
    {
        // Scenario realistico "mid-flight": una campagna con due righe in
        // coda, un processo concorrente cancella la campagna (UPDATE
        // diretto, come farebbe un admin altrove) DOPO che la prima riga
        // è già stata elaborata ma PRIMA della seconda. La seconda riga è
        // ancora 'queued' quando processSend() la reclama (claim riuscito,
        // nessuna corsa persa) — è la revalidazione, non il claim, a
        // fermarla: nessuna interruzione a metà di un claim già in corso,
        // solo un fallimento naturale con motivo esplicito.
        $campaign = $this->sendingCampaign();
        $first = $this->queuedSend($campaign);
        $second = $this->queuedSend($campaign);
        $provider = new RecordingEmailProvider;

        $firstOutcome = $this->orchestrator()->processSend($first, $provider);
        $this->assertSame(SendProcessingOutcome::SENT, $firstOutcome->outcome);

        CommunicationCampaign::where('id', $campaign->id)->update(['status' => CommunicationCampaign::STATUS_CANCELLED]);

        $secondOutcome = $this->orchestrator()->processSend($second, $provider);

        $this->assertSame(SendProcessingOutcome::FAILED, $secondOutcome->outcome);
        $this->assertSame('campaign_not_sendable', $secondOutcome->reason);
        $this->assertSame(CommunicationSend::STATUS_FAILED, $second->fresh()->status);
        // Il provider fake non viene mai raggiunto per la seconda riga:
        // resta a 1 (solo la prima, andata a buon fine prima della
        // cancellazione).
        $this->assertSame(1, $provider->attemptCount());
    }

    public function test_run_campaign_transitions_draft_to_sending_to_completed_and_reports_correctly(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
        ]);

        $accepted1 = CommunicationSubscriber::factory()->confirmed()->create();
        $accepted2 = CommunicationSubscriber::factory()->confirmed()->create();
        $this->queuedSend($campaign, $accepted1);
        $this->queuedSend($campaign, $accepted2);

        $provider = new RecordingEmailProvider;

        $report = $this->orchestrator()->runCampaign($campaign, $provider);

        $this->assertNotNull($report);
        $this->assertSame(2, $report->eligible);
        $this->assertSame(2, $report->accepted);
        $this->assertSame(0, $report->transientFailed);
        $this->assertSame(0, $report->permanentFailed);
        $this->assertSame(CommunicationCampaign::STATUS_COMPLETED, $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->sending_started_at);
        $this->assertNotNull($campaign->fresh()->completed_at);
        $this->assertSame(2, $provider->attemptCount());
    }

    public function test_run_campaign_returns_null_when_the_campaign_cannot_transition_to_sending(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['status' => CommunicationCampaign::STATUS_COMPLETED]);
        $provider = new RecordingEmailProvider;

        $report = $this->orchestrator()->runCampaign($campaign, $provider);

        $this->assertNull($report);
        $this->assertSame(0, $provider->attemptCount());
    }

    /**
     * BUG REALE trovato durante la simulazione end-to-end a 10.000
     * (N2.14) e corretto qui: runCampaign() chiamava processQueue() una
     * sola volta, poi transizionava SEMPRE a 'completed' — anche quando
     * un fallimento transitorio aveva rimesso una riga a 'queued' per un
     * retry. Quella riga restava bloccata per sempre sotto una campagna
     * ormai terminale, senza mai diventare né 'sent' né 'failed'.
     * runCampaign() ora rielabora la coda in più round finché non resta
     * più nulla di 'queued' (o fino a $maxAttempts round), esattamente
     * come farebbe un invio reale con più tentativi sullo stesso
     * destinatario.
     */
    public function test_a_transient_failure_is_fully_retried_within_a_single_run_campaign_call(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
        ]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $this->queuedSend($campaign, $subscriber);

        $provider = (new RecordingEmailProvider)->willReturn(
            new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout simulato'),
            new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout simulato'),
            new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'ok-al-terzo-tentativo'),
        );

        $report = $this->orchestrator()->runCampaign($campaign, $provider);

        $this->assertNotNull($report);
        $this->assertSame(1, $report->eligible);
        $this->assertSame(1, $report->accepted);
        $this->assertSame(2, $report->transientFailed);
        $this->assertSame(0, $report->permanentFailed);
        $this->assertSame(3, $provider->attemptCount());

        $send = CommunicationSend::where('campaign_id', $campaign->id)->firstOrFail();
        $this->assertSame(CommunicationSend::STATUS_SENT, $send->status);
        $this->assertSame(3, $send->attempts);
        $this->assertSame(CommunicationCampaign::STATUS_COMPLETED, $campaign->fresh()->status);
    }

    public function test_a_transient_failure_exhausting_all_attempts_becomes_permanently_failed_within_run_campaign(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
        ]);
        $this->queuedSend($campaign);

        $provider = new RecordingEmailProvider;
        $provider->resolveUsing(fn () => new DeliveryResult(
            status: DeliveryResult::STATUS_TRANSIENT_FAILURE,
            reason: 'timeout persistente simulato',
        ));

        $report = $this->orchestrator()->runCampaign($campaign, $provider, maxAttempts: 3);

        $this->assertNotNull($report);
        $this->assertSame(0, $report->accepted);
        $this->assertSame(2, $report->transientFailed);
        $this->assertSame(1, $report->permanentFailed);
        $this->assertSame(3, $provider->attemptCount());

        $send = CommunicationSend::where('campaign_id', $campaign->id)->firstOrFail();
        $this->assertSame(CommunicationSend::STATUS_FAILED, $send->status);
        $this->assertSame('max_attempts_exceeded', $send->failure_reason);
        $this->assertSame(3, $send->attempts);
        $this->assertSame(CommunicationCampaign::STATUS_COMPLETED, $campaign->fresh()->status);
    }

    public function test_a_campaign_cancelled_mid_run_never_crashes_transitioning_at_the_end(): void
    {
        // Se runCampaign() viene interrotto (in questo test, simulando
        // una cancellazione avvenuta tra un round e l'altro) la
        // campagna è già 'cancelled' — terminale, come 'completed'. La
        // transizione finale deve limitarsi a non fare nulla, mai
        // lanciare RuntimeException per una transizione cancelled->completed
        // genuinamente non ammessa dalla tabella.
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
        ]);
        $this->queuedSend($campaign);

        $provider = new RecordingEmailProvider;
        $provider->resolveUsing(function ($message) use ($campaign) {
            // Cancella la campagna DURANTE la chiamata al provider, per
            // simulare un attore concorrente — il round corrente
            // completa comunque il processSend() in corso.
            $this->orchestrator()->cancelCampaign($campaign->fresh());

            return new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, reason: 'timeout');
        });

        $report = $this->orchestrator()->runCampaign($campaign, $provider, maxAttempts: 3);

        $this->assertNotNull($report);
        $this->assertSame(CommunicationCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
    }

    public function test_delivered_message_carries_the_canonical_idempotency_key(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $campaign = $this->sendingCampaign();
        $send = $this->queuedSend($campaign, $subscriber);
        $provider = new RecordingEmailProvider;

        $this->orchestrator()->processSend($send, $provider);

        $this->assertSame(1, $provider->attemptCount());
        $this->assertSame(
            CampaignRenderer::idempotencyKey($campaign->id, $subscriber->id),
            $provider->attempts()[0]->idempotencyKey
        );
    }

    public function test_orchestration_never_touches_mail_notification_or_queue(): void
    {
        Mail::fake();
        Notification::fake();
        Bus::fake();

        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
        ]);
        $this->queuedSend($campaign);
        $provider = new RecordingEmailProvider;

        $this->orchestrator()->runCampaign($campaign, $provider);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Bus::assertNothingDispatched();
    }
}
