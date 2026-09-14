<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 50 (programma "100 cantieri Kairus", dipende dal Cantiere
 * 49): `Category::featured_article_id`, selezione manuale di un
 * articolo "in evidenza" per una categoria — asse ortogonale ad
 * `Article::featured` (hero homepage sito-wide, Cantiere 36). La
 * pagina pubblica deve mostrarlo SOLO quando resta davvero idoneo
 * (pubblicato, ancora associato a questa categoria) — vedi
 * Category::featuredArticleForDisplay().
 */
class CategoryFeaturedArticleTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedCategory(array $overrides = []): Category
    {
        // Slug fuori da config('laboratorio.categories'), stesso motivo
        // già documentato in CategoryCuratorNoteTest.
        return Category::create(array_merge([
            'name' => 'Categoria Cantiere 50',
            'slug' => 'categoria-cantiere-50',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ], $overrides));
    }

    private function article(string $categorySlug, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $categorySlug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ], $overrides));
    }

    public function test_a_category_without_a_featured_article_shows_no_featured_section(): void
    {
        $category = $this->publishedCategory();

        $response = $this->get(route('categoria', $category->slug));

        $response->assertOk();
        $response->assertDontSee('In evidenza');
    }

    public function test_a_published_featured_article_in_this_category_is_shown(): void
    {
        $category = $this->publishedCategory();
        $article = $this->article($category->slug, ['title' => 'Articolo in evidenza di prova']);
        $category->update(['featured_article_id' => $article->id]);

        $response = $this->get(route('categoria', $category->slug));

        $response->assertOk();
        $response->assertSee('In evidenza');
        $response->assertSee('Articolo in evidenza di prova');
    }

    public function test_a_featured_article_that_is_a_draft_is_not_shown(): void
    {
        $category = $this->publishedCategory();
        $article = $this->article($category->slug, [
            'title' => 'Articolo bozza in evidenza',
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
        ]);
        $category->update(['featured_article_id' => $article->id]);

        $response = $this->get(route('categoria', $category->slug));

        $response->assertOk();
        $response->assertDontSee('In evidenza');
        $response->assertDontSee('Articolo bozza in evidenza');
    }

    public function test_a_featured_article_whose_primary_category_changed_is_not_shown(): void
    {
        $category = $this->publishedCategory();
        $article = $this->article($category->slug, ['title' => 'Articolo migrato altrove']);
        $category->update(['featured_article_id' => $article->id]);

        // L'editore sposta l'articolo in un'altra categoria, senza mai
        // toccare featured_article_id: la selezione diventa stantia.
        $article->update(['category' => 'energia']);

        $response = $this->get(route('categoria', $category->slug));

        $response->assertOk();
        $response->assertDontSee('In evidenza');
    }

    public function test_a_featured_article_associated_only_as_a_secondary_category_is_still_shown(): void
    {
        $category = $this->publishedCategory();
        $article = $this->article('energia', ['title' => 'Articolo con categoria secondaria']);
        $article->secondaryCategories()->attach($category->id);
        $category->update(['featured_article_id' => $article->id]);

        $response = $this->get(route('categoria', $category->slug));

        $response->assertOk();
        $response->assertSee('In evidenza');
        $response->assertSee('Articolo con categoria secondaria');
    }

    public function test_editor_can_set_a_featured_article(): void
    {
        $category = $this->publishedCategory();
        $article = $this->article($category->slug);

        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'featured_article_id' => (string) $article->id,
        ]);

        $this->assertSame($article->id, $category->fresh()->featured_article_id);
    }

    public function test_editor_can_clear_the_featured_article(): void
    {
        $category = $this->publishedCategory();
        $article = $this->article($category->slug);
        $category->update(['featured_article_id' => $article->id]);

        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'featured_article_id' => '',
        ]);

        $this->assertNull($category->fresh()->featured_article_id);
    }

    public function test_guest_cannot_set_a_featured_article(): void
    {
        $category = $this->publishedCategory();
        $article = $this->article($category->slug);

        $this->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'featured_article_id' => (string) $article->id,
        ])->assertRedirect(route('login'));

        $this->assertNull($category->fresh()->featured_article_id);
    }

    public function test_a_non_existent_featured_article_id_is_rejected(): void
    {
        $category = $this->publishedCategory();

        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => '1',
            'status' => Category::STATUS_PUBLISHED,
            'featured_article_id' => '999999',
        ])->assertSessionHasErrors('featured_article_id');

        $this->assertNull($category->fresh()->featured_article_id);
    }

    /**
     * L'anteprima admin (Cantiere 11) riusa la stessa vista pubblica
     * tramite CategoryDiscoveryPageData — deve riflettere l'articolo in
     * evidenza esattamente come la pagina reale, senza divergere.
     */
    public function test_the_admin_preview_of_a_draft_category_also_reflects_the_featured_article(): void
    {
        $category = Category::create([
            'name' => 'Bozza Con Articolo In Evidenza',
            'slug' => 'bozza-con-articolo-in-evidenza',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);
        $article = $this->article($category->slug, ['title' => 'Articolo visibile solo in anteprima']);
        $category->update(['featured_article_id' => $article->id]);

        $response = $this->actingAs($this->editor())->get(route('admin.categories.preview', $category));

        $response->assertOk();
        $response->assertSee('In evidenza');
        $response->assertSee('Articolo visibile solo in anteprima');
    }
}
