<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_campaign_gets_a_uuid_automatically_on_creation(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $this->assertNotEmpty($campaign->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $campaign->uuid
        );
    }

    public function test_a_send_gets_a_uuid_automatically_on_creation(): void
    {
        $send = CommunicationSend::factory()->create();

        $this->assertNotEmpty($send->uuid);
    }

    public function test_campaign_status_scope_filters_correctly(): void
    {
        CommunicationCampaign::factory()->draft()->create();
        $scheduled = CommunicationCampaign::factory()->scheduled()->create();

        $result = CommunicationCampaign::query()->status(CommunicationCampaign::STATUS_SCHEDULED)->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($scheduled));
    }

    public function test_campaign_type_scope_filters_correctly(): void
    {
        CommunicationCampaign::factory()->create(['type' => CommunicationCampaign::TYPE_NEWSLETTER]);
        $comunicato = CommunicationCampaign::factory()->create(['type' => CommunicationCampaign::TYPE_COMUNICATO]);

        $result = CommunicationCampaign::query()->type(CommunicationCampaign::TYPE_COMUNICATO)->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($comunicato));
    }

    public function test_a_campaign_belongs_to_an_optional_project(): void
    {
        $project = Project::factory()->create();
        $campaign = CommunicationCampaign::factory()->create(['project_id' => $project->id]);
        $standalone = CommunicationCampaign::factory()->create();

        $this->assertTrue($campaign->project->is($project));
        $this->assertNull($standalone->project);
    }

    public function test_a_campaign_has_many_sends_and_can_reach_subscribers_through_them(): void
    {
        $campaign = CommunicationCampaign::factory()->create();
        $subscriber = CommunicationSubscriber::factory()->create();
        CommunicationSend::factory()->for($campaign, 'campaign')->for($subscriber, 'subscriber')->create();

        $this->assertCount(1, $campaign->sends);
        $this->assertTrue($campaign->subscribers->contains($subscriber));
    }

    public function test_a_send_belongs_to_a_campaign_and_a_subscriber(): void
    {
        $send = CommunicationSend::factory()->create();

        $this->assertInstanceOf(CommunicationCampaign::class, $send->campaign);
        $this->assertInstanceOf(CommunicationSubscriber::class, $send->subscriber);
    }

    public function test_the_unique_constraint_prevents_two_sends_for_the_same_campaign_and_subscriber(): void
    {
        $campaign = CommunicationCampaign::factory()->create();
        $subscriber = CommunicationSubscriber::factory()->create();
        CommunicationSend::factory()->for($campaign, 'campaign')->for($subscriber, 'subscriber')->create();

        $this->expectException(QueryException::class);

        CommunicationSend::factory()->for($campaign, 'campaign')->for($subscriber, 'subscriber')->create();
    }

    public function test_subscriber_confirmed_scope_returns_only_confirmed_subscribers(): void
    {
        CommunicationSubscriber::factory()->create();
        $confirmed = CommunicationSubscriber::factory()->confirmed()->create();

        $result = CommunicationSubscriber::query()->confirmed()->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($confirmed));
    }

    public function test_a_subscriber_has_many_sends(): void
    {
        $subscriber = CommunicationSubscriber::factory()->create();
        CommunicationSend::factory()->for($subscriber, 'subscriber')->create();

        $this->assertCount(1, $subscriber->sends);
    }
}
