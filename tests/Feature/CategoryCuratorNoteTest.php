<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 49 (programma "100 cantieri Kairus", dipende dai Cantieri
 * 1-8): `Category::curator_note`, campo editoriale opzionale scritto
 * solo da un umano in admin (mai auto-generato), che sostituisce il
 * paragrafo "Editorial Focus" generico — identico oggi per ogni
 * categoria — sulla pagina pubblica quando compilato. Stesso pattern di
 * ContentCluster::curator_note.
 */
class CategoryCuratorNoteTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function publishedCategory(array $overrides = []): Category
    {
        // Slug deliberatamente fuori da config('laboratorio.categories'):
        // la migration create_categories_table semina già quegli slug
        // (es. 'energia') come righe reali, quindi riusarne uno qui
        // romperebbe il vincolo UNIQUE su categories.slug.
        return Category::create(array_merge([
            'name' => 'Categoria Cantiere 49',
            'slug' => 'categoria-cantiere-49',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ], $overrides));
    }

    public function test_a_category_without_a_curator_note_shows_the_generic_editorial_focus_copy(): void
    {
        $category = $this->publishedCategory();

        $response = $this->get(route('categoria', $category->slug));

        $response->assertOk();
        $response->assertSee('Kairus seleziona notizie, ricerca, scenari e innovazioni per raccontare');
    }

    public function test_a_category_with_a_curator_note_shows_it_instead_of_the_generic_copy(): void
    {
        $category = $this->publishedCategory([
            'curator_note' => 'Qui raccontiamo la transizione energetica con dati verificabili, non slogan.',
        ]);

        $response = $this->get(route('categoria', $category->slug));

        $response->assertOk();
        $response->assertSee('Qui raccontiamo la transizione energetica con dati verificabili, non slogan.');
        $response->assertDontSee('Kairus seleziona notizie, ricerca, scenari e innovazioni per raccontare');
    }

    public function test_editor_can_set_a_curator_note_on_create(): void
    {
        $this->actingAs($this->editor())->post(route('admin.categories.store'), [
            'name' => 'Scienza',
            'slug' => '',
            'is_active' => '1',
            'curator_note' => 'La nostra bussola per parlare di metodo scientifico.',
        ]);

        $category = Category::where('name', 'Scienza')->firstOrFail();

        $this->assertSame('La nostra bussola per parlare di metodo scientifico.', $category->curator_note);
    }

    public function test_editor_can_update_the_curator_note(): void
    {
        $category = $this->publishedCategory();

        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'curator_note' => 'Nota aggiornata dal curatore.',
        ]);

        $this->assertSame('Nota aggiornata dal curatore.', $category->fresh()->curator_note);
    }

    public function test_editor_can_clear_an_existing_curator_note(): void
    {
        $category = $this->publishedCategory(['curator_note' => 'Nota da rimuovere.']);

        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'curator_note' => '',
        ]);

        $this->assertNull($category->fresh()->curator_note);
    }

    /**
     * Codex (PR #607): senza la regola `string`, la validazione `max:2000`
     * su un array (es. curator_note[]=a&curator_note[]=b, mai possibile
     * dalla textarea del form reale ma costruibile in una richiesta
     * arbitraria) conterebbe gli ELEMENTI dell'array invece dei
     * caratteri, lasciando passare un array che poi finirebbe scritto
     * così com'è nella colonna testo — stesso principio già applicato
     * alla validazione equivalente di ContentCluster::curator_note.
     */
    public function test_a_curator_note_submitted_as_an_array_is_rejected(): void
    {
        $category = $this->publishedCategory();

        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'curator_note' => ['non', 'e', 'una', 'stringa'],
        ])->assertSessionHasErrors('curator_note');

        $this->assertNull($category->fresh()->curator_note);
    }

    public function test_guest_cannot_set_a_curator_note(): void
    {
        $category = $this->publishedCategory();

        $this->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'curator_note' => 'Iniezione non autorizzata.',
        ])->assertRedirect(route('login'));

        $this->assertNull($category->fresh()->curator_note);
    }

    /**
     * L'anteprima admin (Cantiere 11) riusa la stessa vista pubblica
     * tramite CategoryDiscoveryPageData — deve riflettere la nota del
     * curatore esattamente come la pagina reale, senza divergere.
     */
    public function test_the_admin_preview_of_a_draft_category_also_reflects_the_curator_note(): void
    {
        $category = Category::create([
            'name' => 'Bozza Con Nota',
            'slug' => 'bozza-con-nota',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
            'curator_note' => 'Nota visibile anche in anteprima.',
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.categories.preview', $category));

        $response->assertOk();
        $response->assertSee('Nota visibile anche in anteprima.');
    }
}
