<?php

namespace Tests\Feature\Admin;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSourceReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_report_aggregates_sources_without_rates_or_emails(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        Newsletter::subscribe('private-one@example.test', 'homepage');
        Newsletter::subscribe('private-two@example.test', 'homepage');
        Newsletter::subscribe('legacy@example.test');

        $response = $this->actingAs($editor)->get(route('admin.newsletter'));

        $response->assertOk()
            ->assertSee('Iscrizioni per superficie')
            ->assertSee('Homepage')
            ->assertSee('Sconosciuta / legacy')
            ->assertDontSee('tasso di conversione');

        $this->assertTrue($response->viewData('sourceReport')->every(
            fn ($row) => ! isset($row->email)
        ));
    }
}
