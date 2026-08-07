<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectEditorialLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ], $overrides));
    }

    public function test_a_new_article_is_not_linked_when_no_default_editorial_project_exists(): void
    {
        $article = $this->makeArticle();

        $this->assertCount(0, $article->fresh()->projects);
    }

    public function test_a_new_article_is_linked_automatically_to_the_default_editorial_project(): void
    {
        $project = Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_IN_PROGRESS,
        ]);

        $article = $this->makeArticle();

        $this->assertTrue($article->fresh()->projects->contains($project));
    }

    public function test_the_link_is_recorded_as_a_system_activity_log(): void
    {
        $project = Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_IN_PROGRESS,
        ]);

        $article = $this->makeArticle(['title' => 'Articolo tracciato']);

        $log = ProjectActivityLog::where('project_id', $project->id)->where('subject_type', 'project_article')->first();

        $this->assertNotNull($log);
        $this->assertSame(ProjectActivityLog::SOURCE_SYSTEM, $log->source);
        $this->assertNull($log->user_id);
        $this->assertStringContainsString('Articolo tracciato', $log->action);
    }

    public function test_no_link_happens_when_the_default_editorial_project_is_completed(): void
    {
        Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_COMPLETED,
        ]);

        $article = $this->makeArticle();

        $this->assertCount(0, $article->fresh()->projects);
    }

    public function test_updating_an_existing_article_does_not_trigger_a_new_link_attempt(): void
    {
        $article = $this->makeArticle();

        // Il progetto predefinito nasce DOPO la creazione dell'articolo.
        Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_IN_PROGRESS,
        ]);

        $article->update(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $this->assertCount(0, $article->fresh()->projects);
    }

    public function test_a_manual_unlink_after_the_automatic_link_is_never_reverted(): void
    {
        $project = Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_IN_PROGRESS,
        ]);

        $article = $this->makeArticle();
        $this->assertTrue($article->fresh()->projects->contains($project));

        $project->articles()->detach($article->id);
        $article->update(['title' => 'Titolo modificato']);

        $this->assertCount(0, $article->fresh()->projects);
    }

    public function test_manual_linking_to_a_different_project_still_works_alongside_automatic_linking(): void
    {
        $defaultProject = Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_IN_PROGRESS,
        ]);
        $otherProject = Project::factory()->create();

        $article = $this->makeArticle();
        $otherProject->articles()->attach($article->id);

        $projects = $article->fresh()->projects;
        $this->assertTrue($projects->contains($defaultProject));
        $this->assertTrue($projects->contains($otherProject));
    }

    public function test_the_link_is_never_duplicated_when_called_twice(): void
    {
        $project = Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_IN_PROGRESS,
        ]);

        $article = $this->makeArticle();

        app(\App\Services\ProjectEditorialLinkService::class)->linkToDefaultProject($article->fresh());

        $this->assertSame(1, $project->articles()->where('articles.id', $article->id)->count());
    }
}
