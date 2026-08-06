<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_author_collaborator_cannot_create_a_document(): void
    {
        $project = Project::factory()->create();
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.projects.documents.create', $project))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_create_a_document_with_markdown_content(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->post(route('admin.progettazione.projects.documents.store', $project), [
            'title' => 'Brief editoriale — Enigma',
            'type' => ProjectDocument::TYPE_BRIEF,
            'status' => ProjectDocument::STATUS_DRAFT,
            'content' => "# Brief\n\nTesto.",
        ]);

        $response->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'documents']));
        $this->assertDatabaseHas('project_documents', [
            'project_id' => $project->id,
            'title' => 'Brief editoriale — Enigma',
            'version' => 1,
        ]);
    }

    public function test_updating_a_document_increments_its_version(): void
    {
        $project = Project::factory()->create();
        $document = ProjectDocument::factory()->for($project)->create(['version' => 1]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.documents.update', [$project, $document]), [
            'title' => $document->title,
            'type' => $document->type,
            'status' => $document->status,
            'content' => 'Nuovo contenuto',
        ]);

        $this->assertSame(2, $document->fresh()->version);
    }

    public function test_editor_can_delete_a_document(): void
    {
        $project = Project::factory()->create();
        $document = ProjectDocument::factory()->for($project)->create();

        $this->actingAs($this->editor())
            ->delete(route('admin.progettazione.projects.documents.destroy', [$project, $document]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'documents']));

        $this->assertDatabaseMissing('project_documents', ['id' => $document->id]);
    }

    /**
     * Correzione #5 approvata in revisione: la scheda Documenti di un
     * progetto e' paginata (15 per pagina) invece di un elenco illimitato.
     */
    public function test_documents_tab_paginates_at_fifteen_per_page(): void
    {
        $project = Project::factory()->create();
        ProjectDocument::factory()->for($project)->count(16)->create();

        $firstPage = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'documents']));

        $firstPage->assertOk();
        $firstPage->assertSee('page=2', false);
    }

    public function test_cross_project_documents_index_lists_documents_from_multiple_projects(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        ProjectDocument::factory()->for($projectA)->create(['title' => 'Documento A']);
        ProjectDocument::factory()->for($projectB)->create(['title' => 'Documento B']);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.documents.index-all'));

        $response->assertOk()->assertSeeText('Documento A')->assertSeeText('Documento B');
    }
}
