<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\CommunicationTestSend;
use App\Services\Communication\CampaignFreezeService;
use App\Services\Communication\CampaignTestSendService;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regressione obbligatoria per Provider Abstraction + Safe Test Send
 * (Mission 9): un invio di test non è MAI, in nessuna circostanza,
 * equivalente a un invio bulk. Verifica end-to-end che eseguire un
 * invio di test — anche ripetutamente, anche verso un iscritto che è
 * ANCHE nell'elenco destinatari bulk — non tocchi comm_sends, non
 * cambi comm_campaigns.status, e non renda "inviata" la campagna.
 */
class TestSendNeverAffectsBulkSendRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_send_leaves_the_bulk_recipient_queue_and_campaign_status_completely_untouched(): void
    {
        Mail::fake();
        Notification::fake();
        config(['communication.real_send_enabled' => true]);

        $sender = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Aggiornamento di prova',
            'content' => ['body' => 'Contenuto di prova.'],
        ]);

        // Un iscritto è anche nel bulk prepared — il test-send verso di
        // lui non deve mai marcare/toccare la sua riga comm_sends.
        $inBulkList = CommunicationSubscriber::factory()->confirmed()->create();
        $bulkSend = CommunicationSend::factory()->create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $inBulkList->id,
        ]);

        // Un secondo iscritto, mai stato nell'elenco bulk.
        $neverInBulkList = CommunicationSubscriber::factory()->confirmed()->create();

        app(CampaignFreezeService::class)->freeze($campaign, null);
        $campaign->refresh();

        $service = app(CampaignTestSendService::class);

        $service->send($campaign, $inBulkList, new RecordingEmailProvider, null);
        $service->send($campaign, $neverInBulkList, new RecordingEmailProvider, null);
        $service->send($campaign, $inBulkList, new RecordingEmailProvider, null);

        // La riga bulk preesistente resta esattamente come prima: ancora
        // 'queued', mai toccata da nessuno dei tre invii di test sopra.
        $bulkSend->refresh();
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $bulkSend->status);
        $this->assertSame(0, $bulkSend->attempts);
        $this->assertNull($bulkSend->sent_at);

        // Nessuna nuova riga comm_sends è mai stata creata per l'iscritto
        // mai presente nel bulk, nonostante abbia ricevuto un invio di test.
        $this->assertDatabaseMissing('comm_sends', [
            'campaign_id' => $campaign->id,
            'subscriber_id' => $neverInBulkList->id,
        ]);

        // Esattamente una riga comm_sends totale per questa campagna —
        // quella preesistente, invariata in numero.
        $this->assertSame(1, CommunicationSend::where('campaign_id', $campaign->id)->count());

        // La campagna non è mai transitata a 'sending'/'completed': tre
        // invii di test non equivalgono in alcun modo a un invio bulk.
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);

        $this->assertSame(3, CommunicationTestSend::where('campaign_id', $campaign->id)->count());
    }
}
