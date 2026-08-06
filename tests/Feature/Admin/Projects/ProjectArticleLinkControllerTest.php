<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectArticleLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(): Article
    {
        return Article::create([
            'user_id' => $this->editor()->id,
            'title' => 'Come l\'AI sta reinventando la diagnosi medica',
            'slug' => 'ai-diagnosi-medica-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
        ]);
    }

    public function test_author_collaborator_cannot_link_an_article(): void
    {
        $project = Project::factory()->create();
        $article = $this->article();
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->post(route('admin.progettazione.projects.articles.link', $project), ['article_id' => $article->id])
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_link_an_existing_article(): void
    {
        $project = Project::factory()->create();
        $article = $this->article();

        $this->actingAs($this->editor())
            ->post(route('admin.progettazione.projects.articles.link', $project), ['article_id' => $article->id])
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'articles']));

        $this->assertTrue($project->fresh()->articles->contains($article));
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Articolo collegato: «'.$article->title.'»',
        ]);
    }

    public function test_linking_the_same_article_twice_does_not_error_or_duplicate(): void
    {
        $project = Project::factory()->create();
        $article = $this->article();
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.progettazione.projects.articles.link', $project), ['article_id' => $article->id]);
        $second = $this->actingAs($editor)->post(route('admin.progettazione.projects.articles.link', $project), ['article_id' => $article->id]);

        $second->assertSessionHasNoErrors();
        $this->assertSame(1, $project->articles()->where('articles.id', $article->id)->count());
    }

    public function test_editor_can_unlink_an_article(): void
    {
        $project = Project::factory()->create();
        $article = $this->article();
        $project->articles()->attach($article->id);

        $this->actingAs($this->editor())
            ->delete(route('admin.progettazione.projects.articles.unlink', [$project, $article]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'articles']));

        $this->assertFalse($project->fresh()->articles->contains($article));
    }

    public function test_article_form_shows_linked_projects_block_only_when_linked(): void
    {
        $project = Project::factory()->create(['title' => 'Speciale Enigma']);
        $linkedArticle = $this->article();
        $unlinkedArticle = $this->article();
        $project->articles()->attach($linkedArticle->id);

        $editor = $this->editor();

        $this->actingAs($editor)
            ->get(route('admin.articles.edit', $linkedArticle))
            ->assertSeeText('Progetti collegati')
            ->assertSeeText('Speciale Enigma');

        $this->actingAs($editor)
            ->get(route('admin.articles.edit', $unlinkedArticle))
            ->assertDontSeeText('Progetti collegati');
    }

    public function test_article_create_form_does_not_error_when_no_article_exists_yet(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.articles.create'))
            ->assertOk();
    }
}
