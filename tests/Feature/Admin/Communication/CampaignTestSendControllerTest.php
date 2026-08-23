<?php

namespace Tests\Feature\Admin\Communication;

use App\Mail\CommunicationTestSendMailable;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\CommunicationTestSend;
use App\Models\User;
use App\Services\Communication\CampaignFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CampaignTestSendControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function readyFrozenCampaign(): CommunicationCampaign
    {
        $sender = CommunicationSenderProfile::factory()->create();

        $campaign = CommunicationCampaign::factory()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto di prova',
            'content' => ['body' => 'Corpo di prova.'],
        ]);

        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        CommunicationSend::factory()->create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
        ]);

        app(CampaignFreezeService::class)->freeze($campaign, null);

        return $campaign->fresh();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $campaign = $this->readyFrozenCampaign();

        $response = $this->get(route('admin.comunicazione.campaigns.test-send.form', $campaign));

        $response->assertRedirect(route('login'));
    }

    public function test_the_form_is_unambiguously_labeled_test_send_not_send_campaign(): void
    {
        $campaign = $this->readyFrozenCampaign();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.test-send.form', $campaign));

        $response->assertOk();
        $response->assertSee('Invio di test');
        $response->assertDontSee('Invia campagna');
    }

    public function test_the_form_shows_the_disabled_state_by_default(): void
    {
        $campaign = $this->readyFrozenCampaign();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.test-send.form', $campaign));

        $response->assertOk();
        $response->assertSee('Invio di test disabilitato');
    }

    public function test_the_form_lists_only_confirmed_subscribers_via_search(): void
    {
        $campaign = $this->readyFrozenCampaign();
        $confirmed = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'trovami@example.test']);
        CommunicationSubscriber::factory()->create(['email' => 'nonconfermato@example.test']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.test-send.form', ['campaign' => $campaign, 'q' => 'trovami']));

        $response->assertOk();
        $response->assertSee($confirmed->email);
        $response->assertDontSee('nonconfermato@example.test');
    }

    public function test_test_send_is_rejected_when_not_enabled_even_with_a_valid_recipient(): void
    {
        $campaign = $this->readyFrozenCampaign();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        Mail::fake();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.test-send', $campaign), [
                'subscriber_id' => $subscriber->id,
            ]);

        $response->assertSessionHasErrors('test_send');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('comm_test_sends', 0);
    }

    public function test_test_send_is_rejected_when_the_campaign_is_not_frozen(): void
    {
        config(['communication.real_send_enabled' => true]);

        $sender = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        Mail::fake();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.test-send', $campaign), [
                'subscriber_id' => $subscriber->id,
            ]);

        $response->assertSessionHasErrors('test_send');
        Mail::assertNothingSent();
    }

    public function test_a_successful_test_send_records_one_row_and_sends_exactly_one_real_mailable(): void
    {
        config(['communication.real_send_enabled' => true]);
        $editor = $this->editor();
        $campaign = $this->readyFrozenCampaign();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'destinatario@example.test']);

        Mail::fake();

        $response = $this->actingAs($editor)
            ->post(route('admin.comunicazione.campaigns.test-send', $campaign), [
                'subscriber_id' => $subscriber->id,
            ]);

        $response->assertRedirect(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'sends']));
        $this->assertDatabaseCount('comm_test_sends', 1);

        $testSend = CommunicationTestSend::firstOrFail();
        $this->assertSame(CommunicationTestSend::STATUS_ACCEPTED, $testSend->status);
        $this->assertSame($editor->id, $testSend->triggered_by);

        Mail::assertSentCount(1);
        Mail::assertSent(CommunicationTestSendMailable::class, fn ($mail) => $mail->hasTo('destinatario@example.test'));
    }

    public function test_test_send_rejects_a_missing_subscriber_id(): void
    {
        config(['communication.real_send_enabled' => true]);
        $campaign = $this->readyFrozenCampaign();

        Mail::fake();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.test-send', $campaign), []);

        $response->assertSessionHasErrors('subscriber_id');
        Mail::assertNothingSent();
    }

    public function test_test_send_rejects_a_subscriber_id_that_is_not_confirmed(): void
    {
        config(['communication.real_send_enabled' => true]);
        $campaign = $this->readyFrozenCampaign();
        $unconfirmed = CommunicationSubscriber::factory()->create();

        Mail::fake();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.test-send', $campaign), [
                'subscriber_id' => $unconfirmed->id,
            ]);

        $response->assertSessionHasErrors('subscriber_id');
        Mail::assertNothingSent();
    }

    public function test_the_test_send_button_only_appears_once_the_campaign_is_frozen(): void
    {
        $sender = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.show', $campaign));

        $response->assertOk();
        $response->assertDontSee('Invio di test');
    }

    public function test_the_sends_tab_lists_recent_test_sends_separately_from_bulk_recipients(): void
    {
        config(['communication.real_send_enabled' => true]);
        $editor = $this->editor();
        $campaign = $this->readyFrozenCampaign();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        Mail::fake();
        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.test-send', $campaign), [
            'subscriber_id' => $subscriber->id,
        ]);

        $response = $this->actingAs($editor)
            ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'sends']));

        $response->assertOk();
        $response->assertSee('Invii di test');
        $response->assertSee('Accettato dal provider');
    }
}
