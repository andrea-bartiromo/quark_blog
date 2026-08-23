<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDeliveryOrchestrator;
use App\Services\Communication\CampaignFreezeService;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regressione obbligatoria per Recipient Snapshot + Campaign Freeze
 * (Mission 8): il congelamento NON deve mai autorizzare un invio verso
 * un iscritto che si disiscrive DOPO il congelamento ma PRIMA della
 * consegna reale futura. Zero email reali in ogni scenario — il
 * provider usato qui è sempre RecordingEmailProvider (fake, in
 * memoria), mai un vero Mailer, e Mail::fake()/Notification::fake()
 * restano attivi per l'intero test come doppia garanzia indipendente
 * dal provider stesso.
 *
 * Questo test esercita la CampaignDeliveryOrchestrator reale già
 * esistente e mai modificata da questa missione: dimostra che il
 * congelamento (che blocca contenuto e l'elenco comm_sends) non
 * indebolisce in alcun modo la rivalutazione live già presente in
 * CampaignDeliveryOrchestrator::revalidate(), che continua a rileggere
 * lo stato fresco dell'iscritto dal DB al momento del claim.
 */
class CampaignFreezeZeroSendRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_subscriber_who_unsubscribes_after_freeze_never_receives_the_frozen_campaign(): void
    {
        Mail::fake();
        Notification::fake();

        $sender = CommunicationSenderProfile::factory()->create();

        $campaign = CommunicationCampaign::factory()->scheduled()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Aggiornamento congelato',
            'content' => ['body' => 'Contenuto bloccato al congelamento.'],
        ]);

        $staysConfirmed = CommunicationSubscriber::factory()->confirmed()->create();
        $unsubscribesAfterFreeze = CommunicationSubscriber::factory()->confirmed()->create();

        foreach ([$staysConfirmed, $unsubscribesAfterFreeze] as $subscriber) {
            CommunicationSend::factory()->create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        app(CampaignFreezeService::class)->freeze($campaign, null);
        $this->assertTrue($campaign->fresh()->isFrozen());

        // L'iscritto si disiscrive DOPO il congelamento — esattamente lo
        // scenario critico della missione. La sua riga comm_sends resta
        // 'queued' (il congelamento non la tocca, per design): è la
        // revalidation dell'orchestratore, non il congelamento, a doverla
        // fermare al momento dell'invio.
        $unsubscribesAfterFreeze->update([
            'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        $provider = new RecordingEmailProvider;
        $report = app(CampaignDeliveryOrchestrator::class)->runCampaign($campaign, $provider);

        $this->assertNotNull($report);
        $this->assertSame(2, $report->eligible);
        $this->assertSame(1, $report->accepted);
        $this->assertSame(1, $report->permanentFailed);

        $staysConfirmedSend = CommunicationSend::where('campaign_id', $campaign->id)
            ->where('subscriber_id', $staysConfirmed->id)
            ->firstOrFail();
        $unsubscribedSend = CommunicationSend::where('campaign_id', $campaign->id)
            ->where('subscriber_id', $unsubscribesAfterFreeze->id)
            ->firstOrFail();

        $this->assertSame(CommunicationSend::STATUS_SENT, $staysConfirmedSend->status);
        $this->assertSame(CommunicationSend::STATUS_FAILED, $unsubscribedSend->status);
        $this->assertSame('subscriber_not_eligible', $unsubscribedSend->failure_reason);

        // Il provider fake ha ricevuto un solo tentativo di consegna reale
        // (per l'iscritto ancora confermato) — mai per l'iscritto
        // disiscritto dopo il congelamento.
        $this->assertCount(1, $provider->attempts());

        // Nessuna email reale, in nessun momento del test — indipendente
        // dal fatto che il provider sia già di per sé un fake.
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_freezing_a_campaign_by_itself_sends_no_mail(): void
    {
        Mail::fake();
        Notification::fake();

        $sender = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        CommunicationSend::factory()->create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
        ]);

        app(CampaignFreezeService::class)->freeze($campaign, null);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
    }
}
