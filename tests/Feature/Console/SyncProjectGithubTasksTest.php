<?php

namespace Tests\Feature\Console;

use App\Models\ProjectTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncProjectGithubTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.token' => 'fake-token',
            'services.github.repo' => 'andrea-bartiromo/quark_blog',
        ]);
    }

    public function test_command_syncs_a_task_whose_branch_has_no_pr_yet(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/pulls?')) {
                return Http::response([], 200);
            }
            if (str_contains($request->url(), '/branches/')) {
                return Http::response(['name' => 'x'], 200);
            }

            return Http::response(['message' => 'unexpected'], 404);
        });

        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        // Il task si sincronizza già alla creazione (hook su ProjectTask):
        // forziamo uno stato "sporco" per far trovare qualcosa da fare al
        // comando, come nel test analogo del sync articoli.
        $task->forceFill(['derived_status' => null, 'status_source' => ProjectTask::SOURCE_MANUAL])->saveQuietly();

        $this->artisan('projects:sync-github-tasks')
            ->expectsOutputToContain('Task aggiornati: 1')
            ->assertExitCode(0);

        $this->assertSame(ProjectTask::DERIVED_GH_BRANCH, $task->fresh()->derived_status);
    }

    public function test_command_does_not_throw_when_github_is_unreachable(): void
    {
        Http::fake(fn () => Http::response(['message' => 'Server Error'], 500));

        ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        $this->artisan('projects:sync-github-tasks')->assertExitCode(0);
    }

    public function test_command_is_registered_on_the_schedule(): void
    {
        $schedule = app(Schedule::class);
        $commands = collect($schedule->events())->map(fn ($event) => $event->command ?? '');

        $this->assertTrue($commands->contains(fn ($command) => str_contains($command, 'projects:sync-github-tasks')));
    }
}
