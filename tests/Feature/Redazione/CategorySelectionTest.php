<?php

namespace Tests\Feature\Redazione;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GROWTH S2 — FASE 2 (Fisica integration audit): copre il fix del bug
 * trovato durante l'audit. StoreArticleRequest::rules() validava
 * 'category' contro l'elenco statico di config('laboratorio.categories')
 * (un ARRAY DI SOLE 6 CHIAVI, MAI aggiornato quando "fisica" e' diventata
 * categoria editoriale primaria), diversamente da Admin\StoreArticleRequest
 * (che valida solo 'required', si affida al form popolato da
 * Category::options()). Un redattore/autore Redazione non poteva quindi
 * MAI pubblicare un articolo in una categoria DB reale se non compresa in
 * quello snapshot statico — "fisica" ne era l'esempio concreto, ma il bug
 * e' generale: qualunque categoria creata via Admin\CategoryController
 * DOPO il deploy iniziale restava invisibile e bloccata per Redazione.
 *
 * Il fix allinea Redazione alla stessa fonte gia' usata da Admin
 * (Category::options(), le categorie DB realmente attive) sia per il
 * dropdown del form sia per la validazione.
 */
class CategorySelectionTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function articlePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Articolo di prova',
            'excerpt' => 'Sommario di prova',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
        ], $overrides);
    }

    public function test_fisica_is_seeded_as_an_active_category_by_the_categories_migration(): void
    {
        $this->assertDatabaseHas('categories', [
            'slug' => 'fisica',
            'name' => 'Fisica',
            'is_active' => true,
        ]);
    }

    public function test_redazione_article_form_offers_fisica_as_a_category_option(): void
    {
        $author = $this->author();

        $response = $this->actingAs($author)->get(route('redazione.articles.create'));

        $response->assertOk();
        $response->assertSee('value="fisica"', false);
        $response->assertSee('Fisica', false);
    }

    public function test_an_author_can_publish_an_article_in_the_fisica_category(): void
    {
        $author = $this->author();

        $response = $this->actingAs($author)->post(
            route('redazione.articles.store'),
            $this->articlePayload(['category' => 'fisica'])
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('articles', [
            'title' => 'Articolo di prova',
            'category' => 'fisica',
            'user_id' => $author->id,
        ]);
    }

    public function test_an_author_can_still_update_a_draft_to_the_fisica_category(): void
    {
        $author = $this->author();
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Bozza da spostare',
            'slug' => 'bozza-da-spostare-'.uniqid(),
            'excerpt' => 'Sommario.',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($author)->put(
            route('redazione.articles.update', $article),
            $this->articlePayload(['category' => 'fisica'])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame('fisica', $article->fresh()->category);
    }

    // Prova che il fix e' generale, non un caso speciale per "fisica":
    // qualunque categoria creata via l'admin DOPO il deploy iniziale deve
    // essere immediatamente selezionabile da Redazione, senza toccare
    // config/laboratorio.php.
    public function test_a_category_created_after_deploy_is_immediately_selectable_by_redazione(): void
    {
        Category::create([
            'name' => 'Nuova Area',
            'slug' => 'nuova-area',
            'is_active' => true,
            'sort_order' => 99,
        ]);
        $author = $this->author();

        $formResponse = $this->actingAs($author)->get(route('redazione.articles.create'));
        $formResponse->assertOk();
        $formResponse->assertSee('value="nuova-area"', false);

        $storeResponse = $this->actingAs($author)->post(
            route('redazione.articles.store'),
            $this->articlePayload(['category' => 'nuova-area'])
        );
        $storeResponse->assertSessionHasNoErrors();
    }

    // Simmetrico: una categoria disattivata dall'admin non deve restare
    // selezionabile da Redazione (Category::options() filtra su
    // is_active — un comportamento che config('laboratorio.categories')
    // non poteva nemmeno esprimere).
    public function test_a_deactivated_category_is_rejected_by_redazione_validation(): void
    {
        Category::where('slug', 'ambiente')->update(['is_active' => false]);
        $author = $this->author();

        $response = $this->actingAs($author)->post(
            route('redazione.articles.store'),
            $this->articlePayload(['category' => 'ambiente'])
        );

        $response->assertSessionHasErrors('category');
    }

    // Nessuna regressione sulle categorie originali: restano tutte
    // valide esattamente come prima del fix.
    public function test_all_original_categories_remain_valid_for_redazione(): void
    {
        $author = $this->author();

        foreach (array_keys(config('laboratorio.categories')) as $slug) {
            $response = $this->actingAs($author)->post(
                route('redazione.articles.store'),
                $this->articlePayload(['category' => $slug, 'title' => 'Articolo '.$slug])
            );

            $response->assertSessionHasNoErrors();
        }
    }
}
