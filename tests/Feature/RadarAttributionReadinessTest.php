<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use App\Services\EditorialRadar\EditorialRadarProviderGraphService;
use App\Services\EditorialRadar\Providers\AttributionOpportunityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadarAttributionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_placement_data_is_reported_insufficient_not_article_attribution(): void
    {
        Newsletter::subscribe('reader@example.test', 'article');

        $provider = app(AttributionOpportunityProvider::class);
        $status = $provider->availability();

        $this->assertSame('insufficient', $status['newsletter_article_attribution']['status']);
        $this->assertSame(1, $status['newsletter_article_attribution']['article_surface_signups']);
        $this->assertTrue($provider->opportunities()->isEmpty());
        $this->assertNotNull(app(EditorialRadarProviderGraphService::class)->opportunities());
    }
}
