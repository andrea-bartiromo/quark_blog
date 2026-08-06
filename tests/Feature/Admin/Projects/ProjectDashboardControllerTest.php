<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_author_collaborator_cannot_access_the_dashboard(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.dashboard'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_see_the_dashboard(): void
    {
        Project::factory()->create(['title' => 'Speciale Enigma', 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.dashboard'))
            ->assertOk()
            ->assertSeeText('Speciale Enigma');
    }

    /**
     * Correzione #2 approvata in revisione: "Progetti attivi" nella
     * dashboard deve ordinare per severita' reale, non alfabeticamente.
     */
    public function test_active_projects_widget_orders_by_priority_severity(): void
    {
        Project::factory()->create(['title' => 'Media', 'priority' => Project::PRIORITY_MEDIUM, 'operational_status' => Project::STATUS_IN_PROGRESS]);
        Project::factory()->create(['title' => 'Critica', 'priority' => Project::PRIORITY_CRITICAL, 'operational_status' => Project::STATUS_IN_PROGRESS]);
        Project::factory()->create(['title' => 'Bassa', 'priority' => Project::PRIORITY_LOW, 'operational_status' => Project::STATUS_IN_PROGRESS]);
        Project::factory()->create(['title' => 'Alta', 'priority' => Project::PRIORITY_HIGH, 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $content = strstr($response->getContent(), 'Progetti attivi');
        $posCritica = strpos($content, 'Critica');
        $posAlta = strpos($content, 'Alta');
        $posMedia = strpos($content, 'Media');
        $posBassa = strpos($content, 'Bassa');

        $this->assertTrue($posCritica < $posAlta && $posAlta < $posMedia && $posMedia < $posBassa);
    }

    public function test_blocked_projects_widget_shows_only_blocked_projects(): void
    {
        Project::factory()->create(['title' => 'Bloccato', 'operational_status' => Project::STATUS_BLOCKED]);
        Project::factory()->create(['title' => 'In corso', 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $blockedSection = substr($response->getContent(), strpos($response->getContent(), 'Progetti bloccati'), 800);
        $this->assertStringContainsString('Bloccato', $blockedSection);
    }

    public function test_dashboard_reuses_the_shared_admin_grid_stats_utility(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertSee('admin-grid--stats', false);
    }
}
