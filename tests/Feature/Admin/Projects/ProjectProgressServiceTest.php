<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\ProjectProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_with_no_tasks_has_zero_progress(): void
    {
        $project = Project::factory()->create(['progress' => 50]);

        $progress = app(ProjectProgressService::class)->recalculate($project);

        $this->assertSame(0, $progress);
        $this->assertSame(0, $project->fresh()->progress);
    }

    public function test_progress_is_the_percentage_of_completed_tasks(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);

        $this->assertSame(50, $project->fresh()->progress);
    }

    public function test_cancelled_tasks_are_excluded_from_both_numerator_and_denominator(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);

        // 1 completata su 1 non-annullata = 100%, non 1/3 = 33%.
        $this->assertSame(100, $project->fresh()->progress);
    }

    public function test_a_project_where_every_task_is_cancelled_has_zero_progress(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);

        $this->assertSame(0, $project->fresh()->progress);
    }

    public function test_progress_rounds_to_the_nearest_integer(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);

        // 1/3 = 33.33...% -> arrotondato a 33.
        $this->assertSame(33, $project->fresh()->progress);
    }

    public function test_progress_recalculates_automatically_when_a_task_is_updated(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);

        $this->assertSame(0, $project->fresh()->progress);

        $task->update(['manual_status' => ProjectTask::STATUS_COMPLETED]);

        $this->assertSame(100, $project->fresh()->progress);
    }

    public function test_progress_recalculates_automatically_when_a_task_is_deleted(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        $todo = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);

        $this->assertSame(50, $project->fresh()->progress);

        $todo->delete();

        $this->assertSame(100, $project->fresh()->progress);
    }

    public function test_recalculating_never_changes_operational_status(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_PLANNED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);

        $this->assertSame(Project::STATUS_PLANNED, $project->fresh()->operational_status);
    }
}
