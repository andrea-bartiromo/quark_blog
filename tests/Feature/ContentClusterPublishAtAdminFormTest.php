<?php

namespace Tests\Feature;

use App\Models\ContentCluster;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 05 Mission 8 — form admin per ContentCluster::publish_at
 * (docs/PERCORSI_SCHEDULING_V1_SPEC.md, PR #320). Input datetime-local in
 * Europe/Rome, come per il form articolo, convertito e persistito in UTC
 * dal mutator del modello.
 */
class ContentClusterPublishAtAdminFormTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    public function test_editor_can_set_a_future_publish_at_from_the_edit_form(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => null]);

        $response = $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), [
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'is_active' => '1',
            'sort_order' => 0,
            'publish_at' => '2027-03-15T10:30',
        ]);

        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));

        $expectedUtc = Carbon::createFromFormat('Y-m-d\TH:i', '2027-03-15T10:30', 'Europe/Rome')->utc();
        $this->assertTrue($cluster->fresh()->publish_at->equalTo($expectedUtc));
    }

    public function test_clearing_publish_at_restores_legacy_immediate_visibility(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => now()->addWeek()]);

        $response = $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), [
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'is_active' => '1',
            'sort_order' => 0,
            'publish_at' => '',
        ]);

        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertNull($cluster->fresh()->publish_at);
    }

    public function test_edit_form_shows_the_current_publish_at_prefilled_in_rome_time(): void
    {
        $editor = $this->editor();
        // 15:00 UTC = 17:00 Europe/Rome in agosto (CEST, UTC+2).
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'publish_at' => '2026-08-20 15:00:00',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk();
        $response->assertSee('value="2026-08-20T17:00"', false);
    }

    public function test_edit_form_shows_a_disclaimer_that_public_enforcement_is_not_yet_wired(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk();
        $response->assertSee('non applicano ancora questa regola', false);
    }

    public function test_an_invalid_publish_at_format_is_rejected_with_a_validation_error(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();

        $response = $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), [
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'is_active' => '1',
            'sort_order' => 0,
            'publish_at' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors('publish_at');
    }
}
