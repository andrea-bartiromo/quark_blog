<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S9 — media.user_id ha un vincolo FK con onDelete('cascade')
 * (database/migrations/2026_05_01_132106_create_media_table.php): a
 * differenza di articles.user_id, che CollaboratorController::destroy()
 * riassegna esplicitamente a un editor/admin prima di eliminare il
 * collaboratore, media.user_id non veniva mai toccato. Il vincolo FK
 * cascade cancellava quindi silenziosamente dal DB ogni riga Media
 * caricata da quel collaboratore — non un semplice file orfano, ma una
 * vera perdita della voce di catalogo (metadati, tracciabilita', ricerca
 * in Libreria) per file che restano fisicamente sul disco e possono
 * essere ancora attivamente in uso come copertina di un articolo
 * pubblicato, senza alcun avviso all'operatore.
 */
class CollaboratorMediaOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_deleting_a_collaborator_does_not_cascade_delete_their_media_library_entries(): void
    {
        $admin = $this->admin();
        $collaborator = User::factory()->create(['role' => 'author']);

        $media = Media::create([
            'user_id' => $collaborator->id,
            'filename' => 'copertina.jpg',
            'disk_name' => 'copertina-in-uso.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1000,
        ]);

        // In uso attivo come copertina di un articolo pubblicato: il caso
        // realmente pericoloso, non solo un file inutilizzato.
        Article::create([
            'user_id' => $collaborator->id,
            'title' => 'Articolo con copertina del collaboratore',
            'slug' => 'articolo-copertina-collaboratore',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'cover_image' => 'copertina-in-uso.jpg',
            'status' => 'published',
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ]);

        $this->actingAs($admin)->delete(route('admin.collaborators.destroy', $collaborator));

        $this->assertNotNull(
            Media::find($media->id),
            'La riga Media del collaboratore rimosso non deve sparire dal DB (cascade FK): il file resta sul disco e in uso, ma diventerebbe invisibile e non gestibile dalla Libreria media.'
        );
    }

    public function test_the_reassigned_media_owner_is_the_same_editor_articles_are_reassigned_to(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $admin = $this->admin();
        $collaborator = User::factory()->create(['role' => 'author']);

        $media = Media::create([
            'user_id' => $collaborator->id,
            'filename' => 'foto.jpg',
            'disk_name' => 'foto-collaboratore.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 500,
        ]);

        Article::create([
            'user_id' => $collaborator->id,
            'title' => 'Altro articolo del collaboratore',
            'slug' => 'altro-articolo-collaboratore',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $this->actingAs($admin)->delete(route('admin.collaborators.destroy', $collaborator));

        $reassignedArticle = Article::where('slug', 'altro-articolo-collaboratore')->firstOrFail();

        $this->assertSame(
            $reassignedArticle->user_id,
            $media->fresh()->user_id,
            'Media e Article riassegnati dallo stesso destroy() devono finire allo stesso nuovo proprietario, non a due utenti diversi.'
        );
    }
}
