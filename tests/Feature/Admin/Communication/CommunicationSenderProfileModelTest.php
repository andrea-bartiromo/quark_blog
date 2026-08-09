<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationSenderProfileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sender_profile_gets_a_uuid_automatically_on_creation(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->assertNotEmpty($senderProfile->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $senderProfile->uuid
        );
    }

    public function test_active_scope_excludes_archived_sender_profiles(): void
    {
        $active = CommunicationSenderProfile::factory()->create();
        CommunicationSenderProfile::factory()->archived()->create();

        $result = CommunicationSenderProfile::active()->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($active));
    }

    public function test_a_sender_profile_has_many_campaigns_through_sender_profile_id(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();
        CommunicationCampaign::factory()->create(['sender_profile_id' => $senderProfile->id]);

        $this->assertCount(1, $senderProfile->campaigns);
    }

    public function test_a_campaign_can_reference_a_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->create(['sender_profile_id' => $senderProfile->id]);

        $this->assertTrue($campaign->senderProfile->is($senderProfile));
    }

    // ── Predefinito: al più uno alla volta ──────────────────────

    public function test_marking_a_sender_profile_as_default_unsets_the_previous_default(): void
    {
        $first = CommunicationSenderProfile::factory()->default()->create();
        $second = CommunicationSenderProfile::factory()->create();

        $second->update(['is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_only_one_sender_profile_can_be_default_even_when_created_directly(): void
    {
        CommunicationSenderProfile::factory()->default()->create();
        CommunicationSenderProfile::factory()->default()->create();

        $this->assertSame(1, CommunicationSenderProfile::where('is_default', true)->count());
    }

    public function test_an_archived_sender_profile_can_never_be_marked_as_default(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->archived()->create(['is_default' => true]);

        $this->assertFalse($senderProfile->fresh()->is_default);
    }

    public function test_archiving_a_default_sender_profile_clears_the_default_flag(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->default()->create();

        $senderProfile->update(['status' => CommunicationSenderProfile::STATUS_ARCHIVED]);

        $this->assertFalse($senderProfile->fresh()->is_default);
    }

    public function test_unsetting_the_default_flag_does_not_affect_other_profiles(): void
    {
        $first = CommunicationSenderProfile::factory()->default()->create();
        $second = CommunicationSenderProfile::factory()->create(['is_default' => false]);

        $first->update(['is_default' => false]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);
    }
}
