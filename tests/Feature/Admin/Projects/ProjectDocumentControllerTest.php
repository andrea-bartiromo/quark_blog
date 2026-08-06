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

    /**
     * Rifinitura UX: la vista trasversale "Documenti" deve avere una CTA
     * "+ Nuovo documento" evidente.
     */
    public function test_cross_project_documents_index_shows_a_new_document_cta(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.documents.index-all'));

        $response->assertSeeText('Nuovo documento');
        $response->assertSee(route('admin.progettazione.documents.create-pick-project'), false);
    }

    public function test_cross_project_documents_index_empty_state_has_operative_text_and_cta(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.documents.index-all'));

        $response->assertSeeText('Crea il primo documento');
    }

    public function test_document_project_picker_lists_projects_with_a_link_to_create_the_document(): void
    {
        $project = Project::factory()->create(['title' => 'Speciale Enigma']);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.documents.create-pick-project'));

        $response->assertOk();
        $response->assertSeeText('Speciale Enigma');
        $response->assertSee(route('admin.progettazione.projects.documents.create', $project), false);
    }

    public function test_document_project_picker_shows_empty_state_with_new_project_cta_when_no_projects_exist(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.documents.create-pick-project'));

        $response->assertOk();
        $response->assertSeeText('Non esiste ancora nessun progetto');
        $response->assertSee(route('admin.progettazione.projects.create'), false);
    }
}
