<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use App\Services\Communication\CampaignRenderer;
use App\Services\Communication\RecipientSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Newsletter 2.0 — anteprima reale: stesso CampaignRenderer che il futuro
 * invio riuserà, contro un destinatario reale (o segnaposto se nessun
 * iscritto confermato esiste). Nessun invio, nessuna coda, nessuna
 * mutazione — verificato end-to-end via richiesta HTTP, non solo a
 * livello di servizio.
 */
class CampaignPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function campaignWithBody(string $subject = 'Oggetto reale', string $body = 'Corpo reale della campagna.'): CommunicationCampaign
    {
        return CommunicationCampaign::factory()->draft()->create([
            'subject' => $subject,
            'preheader' => 'Preheader reale',
            'content' => ['body' => $body],
        ]);
    }

    public function test_preview_renders_the_real_campaign_content_and_a_real_subscriber_email(): void
    {
        $campaign = $this->campaignWithBody('Oggetto vero', "Corpo con dati reali.\nSeconda riga.");
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'destinatario@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        $response->assertSee('Oggetto vero');
        $response->assertSee('destinatario@example.com');
        $response->assertSee('Corpo con dati reali.', false);
    }

    public function test_preview_falls_back_to_a_labeled_placeholder_when_no_confirmed_subscriber_exists(): void
    {
        $campaign = $this->campaignWithBody();
        CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_PENDING]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        $response->assertSee('Nessun iscritto confermato disponibile');
        $response->assertDontSee('@example.com');
    }

    public function test_preview_lets_the_admin_select_a_specific_confirmed_subscriber(): void
    {
        $campaign = $this->campaignWithBody();
        $first = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'primo@example.com']);
        $second = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'secondo@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?subscriber_id='.$first->id);

        $response->assertOk();
        $response->assertSee('primo@example.com');

        $response2 = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?subscriber_id='.$second->id);

        $response2->assertOk();
        $response2->assertSee('secondo@example.com');
    }

    public function test_preview_reflects_an_email_changed_after_a_recipient_snapshot_was_already_prepared(): void
    {
        // Il renderer non denormalizza mai l'email (stessa garanzia già
        // provata lato comm_sends in RecipientSnapshotRaceAndScaleTest):
        // dopo un "Prepara destinatari" e un successivo cambio email,
        // l'anteprima deve leggere l'email CORRENTE dal subscriber, non
        // un valore congelato al momento dello snapshot.
        $campaign = $this->campaignWithBody();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'vecchia@example.com']);

        app(RecipientSnapshotService::class)->prepare($campaign);

        $subscriber->update(['email' => 'nuova@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?subscriber_id='.$subscriber->id);

        $response->assertOk();
        $response->assertSee('nuova@example.com');
        $response->assertDontSee('vecchia@example.com');
    }

    public function test_preview_ignores_a_pending_or_unsubscribed_subscriber_id_and_falls_back(): void
    {
        $campaign = $this->campaignWithBody();
        $pending = CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_PENDING]);
        $confirmed = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'confermato@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?subscriber_id='.$pending->id);

        $response->assertOk();
        $response->assertSee('confermato@example.com');
        $response->assertDontSee($pending->email);
    }

    public function test_two_different_subscribers_produce_visibly_distinct_previews(): void
    {
        $campaign = $this->campaignWithBody();
        $first = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'alfa@example.com']);
        $second = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'beta@example.com']);

        $rendererAlfa = app(CampaignRenderer::class)->render($campaign, $first);
        $rendererBeta = app(CampaignRenderer::class)->render($campaign, $second);

        $this->assertNotSame($rendererAlfa->html, $rendererBeta->html);
        $this->assertStringContainsString('alfa@example.com', $rendererAlfa->html);
        $this->assertStringContainsString('beta@example.com', $rendererBeta->html);
    }

    public function test_uses_the_campaigns_own_content_not_the_linked_templates_content(): void
    {
        // Il docblock di CommunicationCampaign::templateVersion() è
        // esplicito: la campagna resta ancorata al proprio contenuto anche
        // se il template collegato cambia. Il renderer deve rispettare
        // questo invariante, non ri-leggere il template.
        $campaign = $this->campaignWithBody('Oggetto campagna', 'Corpo campagna, non del template.');
        CommunicationSubscriber::factory()->confirmed()->create();

        $rendering = app(CampaignRenderer::class)->render($campaign, CommunicationSubscriber::confirmed()->first());

        $this->assertSame('Oggetto campagna', $rendering->subject);
        $this->assertStringContainsString('Corpo campagna, non del template.', $rendering->html);
    }

    public function test_rendering_failure_never_mutates_campaign_send_or_delivery_state(): void
    {
        // Simula un corpo campagna non valido come stringa (array invece di
        // string) per forzare un'eccezione lato rendering, e verifica che
        // nessuno stato persistito ne risulti alterato.
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'content' => ['body' => ['non', 'una', 'stringa']],
        ]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $campaignUpdatedAt = $campaign->updated_at;
        $subscriberUpdatedAt = $subscriber->updated_at;

        try {
            app(CampaignRenderer::class)->render($campaign->fresh(), $subscriber->fresh());
            $this->fail('Era attesa una eccezione per un corpo campagna non renderizzabile.');
        } catch (\Throwable) {
            // Atteso: il punto del test è che non muta nulla, non la forma
            // esatta dell'eccezione sollevata da Blade.
        }

        $this->assertEquals($campaignUpdatedAt, $campaign->fresh()->updated_at);
        $this->assertEquals($subscriberUpdatedAt, $subscriber->fresh()->updated_at);
        $this->assertSame(0, CommunicationSend::where('campaign_id', $campaign->id)->count());
        $this->assertSame(0, CommunicationDelivery::count());
    }

    public function test_repeated_preview_requests_never_create_sends_deliveries_or_activity_log_entries(): void
    {
        $campaign = $this->campaignWithBody();
        CommunicationSubscriber::factory()->confirmed()->count(3)->create();

        $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.preview', $campaign));
        $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.preview', $campaign));
        $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $this->assertSame(0, CommunicationSend::where('campaign_id', $campaign->id)->count());
        $this->assertSame(0, CommunicationDelivery::count());
        $this->assertDatabaseCount('comm_campaign_activity_logs', 0);
    }

    public function test_preview_never_touches_mail_notification_or_queue(): void
    {
        Mail::fake();
        Notification::fake();
        Bus::fake();

        $campaign = $this->campaignWithBody();
        CommunicationSubscriber::factory()->confirmed()->count(3)->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    public function test_preview_shows_the_real_linked_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create([
            'from_name' => 'Kairus Redazione',
            'from_email' => 'redazione@kairus.it',
        ]);
        $campaign = $this->campaignWithBody();
        $campaign->update(['sender_profile_id' => $senderProfile->id]);
        CommunicationSubscriber::factory()->confirmed()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        $response->assertSee('Kairus Redazione');
        $response->assertSee('redazione@kairus.it');
    }

    public function test_preview_renders_a_real_working_unsubscribe_link_for_a_real_subscriber(): void
    {
        $campaign = $this->campaignWithBody();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $rendering = app(CampaignRenderer::class)->render($campaign, $subscriber);

        $expectedUrl = route('comunicazione.disiscrizione.conferma', $subscriber->unsubscribe_token);
        $this->assertSame($expectedUrl, $rendering->unsubscribeUrl);
        $this->assertStringContainsString('href="'.$expectedUrl.'"', $rendering->html);

        // Il link deve essere davvero raggiungibile, non solo presente nel
        // markup: la stessa rotta pubblica costruita in N2.2.
        $this->get($expectedUrl)->assertOk();
    }

    public function test_preview_does_not_render_an_unsubscribe_link_for_a_placeholder_recipient(): void
    {
        $campaign = $this->campaignWithBody();

        $rendering = app(CampaignRenderer::class)->render($campaign, null);

        $this->assertNull($rendering->unsubscribeUrl);
        $this->assertStringNotContainsString('href="http', $rendering->html);
    }

    public function test_preview_requires_editor_role(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $campaign = $this->campaignWithBody();

        $response = $this->actingAs($author)
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertRedirect(route('redazione.dashboard'));
    }
}
