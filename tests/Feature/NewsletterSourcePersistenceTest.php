<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSourcePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_cta_source_is_persisted_from_allowlist(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.test', 'source' => 'homepage', 'website' => '']);
        $this->assertDatabaseHas('newsletter', ['email' => 'reader@example.test', 'source' => 'homepage']);
    }

    public function test_arbitrary_source_is_rejected(): void
    {
        $this->from('/')->post(route('newsletter.subscribe'), ['email' => 'reader@example.test', 'source' => str_repeat('x', 100), 'website' => ''])
            ->assertSessionHasErrors('source');
        $this->assertDatabaseMissing('newsletter', ['email' => 'reader@example.test']);
    }

    public function test_legacy_or_missing_source_remains_null(): void
    {
        Newsletter::subscribe('legacy@example.test');
        $this->assertDatabaseHas('newsletter', ['email' => 'legacy@example.test', 'source' => null]);
    }

    public function test_existing_subscriber_keeps_first_acquisition_source(): void
    {
        Newsletter::subscribe('reader@example.test', 'popup');
        Newsletter::subscribe('reader@example.test', 'article');
        $this->assertDatabaseHas('newsletter', ['email' => 'reader@example.test', 'source' => 'popup']);
    }
}
