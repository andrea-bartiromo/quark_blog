<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Red-team pre-merge (FASE 7): payload reali contro la pipeline di
 * rendering, non solo una revisione statica del codice ({!! !!} assente
 * era già stato verificato per grep in N2.11 — qui si prova con
 * un'iniezione VERA attraverso l'HTTP reale, chiudendo il gap tra
 * "il codice sembra sicuro" e "il payload esce davvero escapato").
 */
class CampaignInjectionPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_a_script_tag_in_the_campaign_body_is_never_rendered_as_live_html_in_the_preview(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Oggetto',
            'content' => ['body' => '<script>window.__xss_fired = true;</script><img src=x onerror="window.__xss_fired=true">'],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        // Escapato due volte per costruzione (una volta dal template
        // email, una seconda volta incorporando quell'HTML già escapato
        // nell'attributo srcdoc dell'iframe di anteprima) — quello che
        // conta per la sicurezza è che la forma ESEGUIBILE non compaia
        // mai, non la profondità esatta dell'escaping.
        $response->assertDontSee('<script>window.__xss_fired', false);
        $response->assertDontSee('<img src=x onerror', false);
    }

    public function test_svg_onload_payload_in_the_body_never_reaches_the_dom_unescaped(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Oggetto',
            'content' => ['body' => '<svg onload="alert(document.cookie)">'],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        $response->assertDontSee('<svg onload=', false);
    }

    public function test_javascript_and_data_urls_in_the_body_are_never_rendered_as_live_markup(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Oggetto',
            'content' => ['body' => '<a href="javascript:alert(1)">click</a><iframe src="data:text/html,<script>alert(1)</script>"></iframe>'],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        $response->assertDontSee('<a href="javascript:alert(1)">', false);
        $response->assertDontSee('<iframe src="data:text/html', false);
    }

    public function test_an_extremely_long_body_does_not_crash_the_preview(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Oggetto',
            'content' => ['body' => str_repeat("Riga di prova.\n", 100000)],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
    }

    public function test_unicode_bidi_control_characters_in_subject_do_not_break_rendering(): void
    {
        // U+202E (RIGHT-TO-LEFT OVERRIDE) è un vettore noto per mascherare
        // testo (es. rinominare un file .exe come sembrasse .txt) — qui
        // verifichiamo solo che non rompa il rendering o l'escaping, non
        // che venga rimosso (non è un obiettivo di questa missione
        // filtrare Unicode legittimo da un oggetto scritto da un editor
        // fidato).
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => "Oggetto normale \u{202E}elgnem oiggatnuP\u{202C}",
            'content' => ['body' => 'Corpo.'],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
    }

    public function test_a_malicious_subscriber_email_stored_out_of_band_is_still_escaped_in_the_email_footer(): void
    {
        // L'email del subscriber è normalmente validata alla creazione,
        // ma questo test verifica la difesa in profondità nel
        // TEMPLATE stesso: anche un valore anomalo già presente in DB
        // (fixture/import/scrittura diretta) non deve mai produrre
        // markup vivo nel footer che lo cita.
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create([
            'email' => 'evil"><script>alert(1)</script>@example.com',
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?subscriber_id='.$subscriber->id);

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
