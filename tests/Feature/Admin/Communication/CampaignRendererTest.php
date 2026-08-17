<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignRendererTest extends TestCase
{
    use RefreshDatabase;

    private function campaignWithBody(string $subject = 'Oggetto', string $body = 'Corpo.'): CommunicationCampaign
    {
        return CommunicationCampaign::factory()->draft()->create([
            'subject' => $subject,
            'preheader' => 'Preheader',
            'content' => ['body' => $body],
        ]);
    }

    public function test_idempotency_key_is_deterministic_for_the_same_campaign_and_subscriber(): void
    {
        $campaign = $this->campaignWithBody();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $first = app(CampaignRenderer::class)->render($campaign, $subscriber);
        $second = app(CampaignRenderer::class)->render($campaign, $subscriber);

        $this->assertNotNull($first->idempotencyKey);
        $this->assertSame($first->idempotencyKey, $second->idempotencyKey);
        $this->assertSame(
            CampaignRenderer::idempotencyKey($campaign->id, $subscriber->id),
            $first->idempotencyKey
        );
    }

    public function test_idempotency_key_differs_across_subscribers_and_across_campaigns(): void
    {
        $campaignA = $this->campaignWithBody();
        $campaignB = $this->campaignWithBody();
        $subscriberA = CommunicationSubscriber::factory()->confirmed()->create();
        $subscriberB = CommunicationSubscriber::factory()->confirmed()->create();

        $renderer = app(CampaignRenderer::class);

        $keyAA = $renderer->render($campaignA, $subscriberA)->idempotencyKey;
        $keyAB = $renderer->render($campaignA, $subscriberB)->idempotencyKey;
        $keyBA = $renderer->render($campaignB, $subscriberA)->idempotencyKey;

        $this->assertNotSame($keyAA, $keyAB);
        $this->assertNotSame($keyAA, $keyBA);
    }

    public function test_idempotency_key_is_null_for_a_placeholder_recipient(): void
    {
        $campaign = $this->campaignWithBody();

        $rendering = app(CampaignRenderer::class)->render($campaign, null);

        $this->assertNull($rendering->idempotencyKey);
    }

    public function test_idempotency_key_is_stable_across_a_campaign_content_edit(): void
    {
        // Risposta esplicita alla domanda FASE 9: campaign_id+subscriber_id
        // basta, non serve una campaign_version. Modificare il contenuto
        // non cambia l'identità di consegna.
        $campaign = $this->campaignWithBody('Oggetto originale', 'Corpo originale.');
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $before = app(CampaignRenderer::class)->render($campaign, $subscriber);

        $campaign->update(['subject' => 'Oggetto modificato', 'content' => ['body' => 'Corpo modificato.']]);

        $after = app(CampaignRenderer::class)->render($campaign->fresh(), $subscriber);

        $this->assertSame($before->idempotencyKey, $after->idempotencyKey);
        $this->assertNotSame($before->subject, $after->subject);
        $this->assertStringContainsString('Corpo modificato.', $after->html);
    }

    public function test_text_fallback_is_derived_from_the_same_rendered_html_never_a_separate_source(): void
    {
        $campaign = $this->campaignWithBody('Oggetto testo', "Prima riga.\nSeconda riga.");
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'lettore@example.com']);

        $rendering = app(CampaignRenderer::class)->render($campaign, $subscriber);

        $this->assertNotSame('', $rendering->text);
        $this->assertStringContainsString('Kairus', $rendering->text);
        $this->assertStringContainsString('Oggetto testo', $rendering->text);
        $this->assertStringContainsString('Prima riga.', $rendering->text);
        $this->assertStringContainsString('lettore@example.com', $rendering->text);
        $this->assertStringNotContainsString('<', $rendering->text);
        $this->assertStringNotContainsString('>', $rendering->text);
    }

    public function test_reply_to_and_from_are_read_from_the_real_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create([
            'from_name' => 'Kairus',
            'from_email' => 'no-reply@kairus.it',
            'reply_to' => 'redazione@kairus.it',
        ]);
        $campaign = $this->campaignWithBody();
        $campaign->update(['sender_profile_id' => $senderProfile->id]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $rendering = app(CampaignRenderer::class)->render($campaign->fresh(), $subscriber);

        $this->assertSame('Kairus', $rendering->fromName);
        $this->assertSame('no-reply@kairus.it', $rendering->fromEmail);
        $this->assertSame('redazione@kairus.it', $rendering->replyTo);
    }

    public function test_campaign_identity_fields_are_populated(): void
    {
        $campaign = $this->campaignWithBody();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $rendering = app(CampaignRenderer::class)->render($campaign, $subscriber);

        $this->assertSame($campaign->id, $rendering->campaignId);
        $this->assertSame($campaign->uuid, $rendering->campaignUuid);
    }

    public function test_rendering_is_deterministic_for_the_same_inputs(): void
    {
        $campaign = $this->campaignWithBody();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $renderer = app(CampaignRenderer::class);
        $first = $renderer->render($campaign, $subscriber);
        $second = $renderer->render($campaign, $subscriber);

        $this->assertSame($first->html, $second->html);
        $this->assertSame($first->text, $second->text);
        $this->assertSame($first->subject, $second->subject);
    }

    /**
     * N2.11 — difesa in profondità: anche se la validazione in ingresso
     * (StoreCommunicationCampaignRequest e affini) già rifiuta \r/\n in
     * questi campi, il rendering — unico punto riusato da preview,
     * dry-run E futuro invio reale — non deve MAI produrre un header con
     * newline, indipendentemente da come i dati sono finiti nel DB (qui
     * scritti direttamente via Eloquent, bypassando il FormRequest,
     * come farebbe una fixture/import/scrittura diretta).
     */
    public function test_a_newline_smuggled_into_the_subject_via_direct_write_never_reaches_the_rendered_message(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'subject' => "Oggetto\r\nBcc: attacker@example.com",
            'preheader' => "Anteprima\nX-Injected: true",
            'content' => ['body' => 'Corpo.'],
            'sender_profile_id' => CommunicationSenderProfile::factory()->create([
                'from_name' => "Kairus\r\nBcc: attacker@example.com",
            ])->id,
        ]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $rendering = app(CampaignRenderer::class)->render($campaign, $subscriber);

        $this->assertStringNotContainsString("\r", $rendering->subject);
        $this->assertStringNotContainsString("\n", $rendering->subject);
        $this->assertStringNotContainsString("\r", (string) $rendering->preheader);
        $this->assertStringNotContainsString("\n", (string) $rendering->preheader);
        $this->assertStringNotContainsString("\r", (string) $rendering->fromName);
        $this->assertStringNotContainsString("\n", (string) $rendering->fromName);
    }
}
