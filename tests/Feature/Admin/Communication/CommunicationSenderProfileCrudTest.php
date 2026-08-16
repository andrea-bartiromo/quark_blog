<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationSenderProfileCrudTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Redazione Kairus',
            'from_name' => 'Kairus',
            'from_email' => 'redazione@kairus.it',
            'reply_to' => 'redazione@kairus.it',
            'provider' => CommunicationSenderProfile::PROVIDER_SMTP,
        ], $overrides);
    }

    // ── Permessi ─────────────────────────────────────────────────

    public function test_a_guest_cannot_reach_the_sender_profiles_index(): void
    {
        $this->get(route('admin.comunicazione.sender-profiles.index'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_reach_the_sender_profiles_index(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.comunicazione.sender-profiles.index'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_an_author_cannot_store_a_sender_profile(): void
    {
        $this->actingAs($this->author())
            ->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload())
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseCount('comm_sender_profiles', 0);
    }

    public function test_an_author_cannot_update_a_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->actingAs($this->author())
            ->put(route('admin.comunicazione.sender-profiles.update', $senderProfile), $this->validPayload())
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_an_author_cannot_delete_a_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->actingAs($this->author())
            ->delete(route('admin.comunicazione.sender-profiles.destroy', $senderProfile))
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseHas('comm_sender_profiles', ['id' => $senderProfile->id, 'deleted_at' => null]);
    }

    // ── CRUD: store ──────────────────────────────────────────────

    public function test_an_editor_can_create_a_sender_profile(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload());

        $senderProfile = CommunicationSenderProfile::firstOrFail();
        $response->assertRedirect(route('admin.comunicazione.sender-profiles.show', $senderProfile));

        $this->assertSame('Redazione Kairus', $senderProfile->name);
        $this->assertSame('Kairus', $senderProfile->from_name);
        $this->assertSame('redazione@kairus.it', $senderProfile->from_email);
        $this->assertSame(CommunicationSenderProfile::PROVIDER_SMTP, $senderProfile->provider);
        $this->assertSame(CommunicationSenderProfile::STATUS_ACTIVE, $senderProfile->status);
        $this->assertNotEmpty($senderProfile->uuid);
        $this->assertSame($editor->id, $senderProfile->created_by);
        $this->assertSame($editor->id, $senderProfile->updated_by);
    }

    public function test_an_admin_can_also_create_a_sender_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload());

        $this->assertDatabaseCount('comm_sender_profiles', 1);
    }

    public function test_name_is_required(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload(['name' => '']));

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('comm_sender_profiles', 0);
    }

    public function test_from_email_is_required_and_must_be_a_valid_email(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload(['from_email' => 'non-una-email']));

        $response->assertSessionHasErrors('from_email');
        $this->assertDatabaseCount('comm_sender_profiles', 0);
    }

    /**
     * N2.11 — audit di sicurezza: from_name diventa il display-name
     * dell'header From di un'email reale quando un provider verrà
     * collegato. Un \r o \n al suo interno è un vettore classico di CRLF
     * header injection, rifiutato già qui in ingresso.
     */
    public function test_from_name_cannot_contain_a_newline(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload([
                'from_name' => "Kairus\r\nBcc: attacker@example.com",
            ]));

        $response->assertSessionHasErrors('from_name');
        $this->assertDatabaseCount('comm_sender_profiles', 0);
    }

    public function test_reply_to_is_optional(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload(['reply_to' => '']));

        $this->assertNull(CommunicationSenderProfile::firstOrFail()->reply_to);
    }

    public function test_a_new_sender_profile_can_be_marked_as_default(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload(['is_default' => '1']));

        $this->assertTrue(CommunicationSenderProfile::firstOrFail()->is_default);
    }

    public function test_a_new_sender_profile_defaults_to_not_default_when_the_checkbox_is_omitted(): void
    {
        $this->actingAs($this->editor())->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload());

        $this->assertFalse(CommunicationSenderProfile::firstOrFail()->is_default);
    }

    // ── CRUD: update ─────────────────────────────────────────────

    public function test_an_editor_can_update_a_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create(['name' => 'Nome originale']);

        $this->actingAs($this->editor())->put(
            route('admin.comunicazione.sender-profiles.update', $senderProfile),
            $this->validPayload(['name' => 'Nome aggiornato', 'status' => CommunicationSenderProfile::STATUS_ACTIVE])
        );

        $this->assertSame('Nome aggiornato', $senderProfile->fresh()->name);
    }

    public function test_updating_can_change_the_status(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->actingAs($this->editor())->put(
            route('admin.comunicazione.sender-profiles.update', $senderProfile),
            $this->validPayload(['status' => CommunicationSenderProfile::STATUS_ARCHIVED])
        );

        $this->assertSame(CommunicationSenderProfile::STATUS_ARCHIVED, $senderProfile->fresh()->status);
    }

    public function test_unchecking_the_default_box_on_update_clears_it(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->default()->create();

        $this->actingAs($this->editor())->put(
            route('admin.comunicazione.sender-profiles.update', $senderProfile),
            $this->validPayload(['status' => CommunicationSenderProfile::STATUS_ACTIVE])
            // is_default assente: checkbox non spuntata.
        );

        $this->assertFalse($senderProfile->fresh()->is_default);
    }

    // ── Archivia / Elimina ───────────────────────────────────────

    public function test_an_editor_can_archive_an_active_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->actingAs($this->editor())->post(route('admin.comunicazione.sender-profiles.archive', $senderProfile));

        $this->assertSame(CommunicationSenderProfile::STATUS_ARCHIVED, $senderProfile->fresh()->status);
    }

    public function test_a_sender_profile_with_no_linked_campaigns_can_be_deleted(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $response = $this->actingAs($this->editor())->delete(route('admin.comunicazione.sender-profiles.destroy', $senderProfile));

        $response->assertRedirect(route('admin.comunicazione.sender-profiles.index'));
        $this->assertSoftDeleted('comm_sender_profiles', ['id' => $senderProfile->id]);
    }

    public function test_a_sender_profile_used_by_a_campaign_cannot_be_deleted(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();
        CommunicationCampaign::factory()->create(['sender_profile_id' => $senderProfile->id]);

        $response = $this->actingAs($this->editor())->delete(route('admin.comunicazione.sender-profiles.destroy', $senderProfile));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('comm_sender_profiles', ['id' => $senderProfile->id, 'deleted_at' => null]);
    }

    public function test_a_sender_profile_used_by_a_campaign_can_still_be_archived(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();
        CommunicationCampaign::factory()->create(['sender_profile_id' => $senderProfile->id]);

        $this->actingAs($this->editor())->post(route('admin.comunicazione.sender-profiles.archive', $senderProfile));

        $this->assertSame(CommunicationSenderProfile::STATUS_ARCHIVED, $senderProfile->fresh()->status);
    }

    // ── Empty state / index ────────────────────────────────────────

    public function test_the_index_shows_an_empty_state_with_creation_cta(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.sender-profiles.index'));

        $response->assertOk();
        $response->assertSee('Nessun mittente trovato');
        $response->assertSee('Nuovo mittente');
    }

    public function test_the_status_filter_returns_only_matching_sender_profiles(): void
    {
        $active = CommunicationSenderProfile::factory()->create(['name' => 'Attivo di prova']);
        CommunicationSenderProfile::factory()->archived()->create(['name' => 'Archiviato di prova']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.sender-profiles.index', ['status' => CommunicationSenderProfile::STATUS_ACTIVE]));

        $response->assertSee($active->name);
        $response->assertDontSee('Archiviato di prova');
    }

    // ── B4A: nessuna capacità di invio introdotta ──────────────────

    public function test_the_sender_profile_show_page_never_offers_a_send_action(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.sender-profiles.show', $senderProfile));

        $response->assertOk();
        $response->assertDontSee('Invia ora');
        $response->assertDontSee('Invio di prova');
        $response->assertDontSee('Invia test');
    }

    public function test_creating_and_managing_sender_profiles_never_creates_a_communication_send(): void
    {
        $editor = $this->editor();
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->actingAs($editor)->post(route('admin.comunicazione.sender-profiles.store'), $this->validPayload(['name' => 'Altro mittente']));
        $this->actingAs($editor)->post(route('admin.comunicazione.sender-profiles.archive', $senderProfile));

        $this->assertDatabaseCount('comm_sends', 0);
    }
}
