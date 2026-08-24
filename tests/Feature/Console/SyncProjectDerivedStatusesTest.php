<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncProjectDerivedStatusesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_a_task_whose_derived_status_is_stale(): void
    {
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo',
            'slug' => 'articolo-console-test',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ]);

        $task = ProjectTask::factory()->publication()->create(['article_id' => $article->id]);

        // Forza manualmente uno stato derivato "vecchio" senza passare dal
        // service, per simulare un articolo cambiato mentre lo scheduler
        // non girava (es. server fermo).
        $task->forceFill(['derived_status' => ProjectTask::DERIVED_DRAFT])->saveQuietly();
        $article->forceFill(['status' => Article::STATUS_PUBLISHED])->saveQuietly();

        $this->artisan('projects:sync-derived-statuses')
            ->expectsOutputToContain('Task aggiornati: 1')
            ->assertExitCode(0);

        $this->assertSame(ProjectTask::DERIVED_PUBLISHED, $task->fresh()->derived_status);
    }

    public function test_command_is_registered_on_the_schedule(): void
    {
        $schedule = app(Schedule::class);
        $commands = collect($schedule->events())->map(fn ($event) => $event->command ?? '');

        $this->assertTrue($commands->contains(fn ($command) => str_contains($command, 'projects:sync-derived-statuses')));
    }
}
