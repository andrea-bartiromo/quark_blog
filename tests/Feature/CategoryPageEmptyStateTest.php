<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CATEGORY ARCHITECTURE — FASE 4 (/categoria/{slug} quality gate): la
 * griglia articoli usava @foreach senza ramo @empty, quindi una categoria
 * senza alcun articolo pubblicato (il caso di partenza per Fisica, prima
 * che venga pubblicato il primo articolo) renderizzava una griglia vuota
 * senza alcun messaggio — hero ed "Editorial Focus" restano intatti, ma la
 * sezione "Ultimi articoli" appariva come uno spazio bianco non
 * spiegato, indistinguibile da una pagina rotta. La vista è generica
 * (stesso template per qualunque slug): il messaggio non nomina Fisica né
 * alcuna categoria specifica.
 */
class CategoryPageEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_with_no_published_articles_shows_an_honest_empty_state(): void
    {
        // 'fisica' è già seminata dalla migration da config('laboratorio.categories')
        // (PR #253): firstOrCreate evita una collisione sul suo slug univoco senza
        // dipendere dal fatto che sia o meno già presente in un dato momento.
        Category::firstOrCreate(
            ['slug' => 'fisica'],
            ['name' => 'Fisica', 'is_active' => true, 'sort_order' => 0]
        );

        $response = $this->get(route('categoria', 'fisica'));

        $response->assertOk();
        $response->assertSee('Nessun articolo pubblicato ancora');

        // La pagina resta comunque un contenitore editoriale valido, non
        // una shell vuota: hero e titolo categoria sono presenti a
        // prescindere dal conteggio articoli.
        $response->assertSee('Fisica');
    }

    public function test_a_category_with_published_articles_does_not_show_the_empty_state(): void
    {
        // Stessa nota di sopra: 'fisica' è già seminata dalla migration.
        Category::firstOrCreate(
            ['slug' => 'fisica'],
            ['name' => 'Fisica', 'is_active' => true, 'sort_order' => 0]
        );

        $author = User::factory()->create(['role' => 'author']);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Un articolo di fisica',
            'slug' => 'un-articolo-di-fisica',
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);

        $response = $this->get(route('categoria', 'fisica'));

        $response->assertOk();
        $response->assertDontSee('Nessun articolo pubblicato ancora');
        $response->assertSee('Un articolo di fisica');
    }

    public function test_the_empty_state_message_is_generic_not_hardcoded_to_a_single_category(): void
    {
        // Stessa vista, stesso messaggio, per una categoria diversa da
        // Fisica: dimostra che il fix non è un caso speciale nascosto.
        Category::create([
            'name' => 'Nuova Sezione',
            'slug' => 'nuova-sezione-vuota',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->get(route('categoria', 'nuova-sezione-vuota'));

        $response->assertOk();
        $response->assertSee('Nessun articolo pubblicato ancora');
        $response->assertSee('Nuova Sezione');
    }

    /**
     * Regressione per la causa alla radice di questo file di test: la
     * migration `create_categories_table` semina ogni categoria elencata in
     * config('laboratorio.categories') (incluso 'fisica' da PR #253). Un
     * test che ricrea con Category::create() uno slug già seminato collide
     * sull'unique index — il bug non era nel prodotto, ma nell'assunzione
     * del test. Questa asserzione blocca la stessa classe di bug per
     * qualunque categoria futura aggiunta al catalogo canonico.
     */
    public function test_every_canonical_category_from_config_is_seeded_by_migration(): void
    {
        $slugs = array_keys(config('laboratorio.categories', []));

        $this->assertNotEmpty($slugs);

        foreach ($slugs as $slug) {
            $this->assertDatabaseHas('categories', [
                'slug' => $slug,
                'is_active' => true,
            ]);
        }
    }
}
