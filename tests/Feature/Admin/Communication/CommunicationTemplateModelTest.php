<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationTemplateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_template_gets_a_uuid_automatically_on_creation(): void
    {
        $template = CommunicationTemplate::factory()->create();

        $this->assertNotEmpty($template->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $template->uuid
        );
    }

    public function test_a_template_has_many_versions_ordered_from_newest(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1]);
        $v2 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 2]);

        $this->assertSame([$v2->id, $v1->id], $template->versions->pluck('id')->all());
    }

    public function test_a_template_points_to_its_active_version(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $version = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1]);
        $template->update(['active_version_id' => $version->id]);

        $this->assertTrue($template->fresh()->activeVersion->is($version));
    }

    public function test_active_scope_excludes_archived_templates(): void
    {
        $active = CommunicationTemplate::factory()->create();
        CommunicationTemplate::factory()->archived()->create();

        $result = CommunicationTemplate::active()->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($active));
    }

    public function test_a_template_has_many_campaigns_through_template_id(): void
    {
        $template = CommunicationTemplate::factory()->create();
        CommunicationCampaign::factory()->create(['template_id' => $template->id]);

        $this->assertCount(1, $template->campaigns);
    }

    public function test_a_campaign_can_reference_a_template_and_a_specific_version(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $version = CommunicationTemplateVersion::factory()->for($template, 'template')->create();
        $campaign = CommunicationCampaign::factory()->create([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
        ]);

        $this->assertTrue($campaign->template->is($template));
        $this->assertTrue($campaign->templateVersion->is($version));
    }

    /**
     * Il principio cardine del Blueprint (Sezione 7): una campagna resta
     * ancorata alla versione che aveva selezionato, non a quella corrente
     * del template, anche dopo che il template è avanzato di versione.
     */
    public function test_a_campaign_keeps_its_pinned_version_after_the_template_gets_a_new_active_version(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'subject' => 'Oggetto v1']);
        $template->update(['active_version_id' => $v1->id]);

        $campaign = CommunicationCampaign::factory()->create([
            'template_id' => $template->id,
            'template_version_id' => $v1->id,
        ]);

        $v2 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 2, 'subject' => 'Oggetto v2']);
        $template->update(['active_version_id' => $v2->id]);

        $this->assertSame('Oggetto v1', $campaign->fresh()->templateVersion->subject);
        $this->assertSame('Oggetto v2', $template->fresh()->activeVersion->subject);
    }
}
