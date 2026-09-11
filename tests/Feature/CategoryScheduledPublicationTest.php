<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\Search\TrovaEntitySearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Prompt 1-2 (pianificazione categorie): stato bozza/programmato/pubblicato
 * su Category, e Category::publiclyVisible()/isPubliclyVisible() come
 * contratto unico di visibilità pubblica — stesso pattern già certificato
 * per i Percorsi in ContentClusterPubliclyVisibleScopeTest.
 *
 * Prima sezione: contratto a livello di modello (le 5 combinazioni
 * richieste — bozza, programmata futura, programmata all'istante esatto di
 * apertura, disattivata, pubblicata). Seconda sezione: non-esposizione su
 * ogni superficie pubblica realmente raggiungibile (route categoria,
 * header, category-bar, footer, sidebar, home, notizie, ricerca, TROVA,
 * sitemap, breadcrumb/JSON-LD) mentre la categoria resta selezionabile nei
 * form editoriali (Category::options(), invariato).
 */
class CategoryScheduledPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(User $author, string $category, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Contratto a livello di modello
    // ---------------------------------------------------------------

    public function test_draft_category_is_not_publicly_visible(): void
    {
        $category = Category::create([
            'name' => 'Bozza Editoriale',
            'slug' => 'bozza-editoriale',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $this->assertFalse($category->fresh()->isPubliclyVisible());
        $this->assertSame('Bozza', $category->fresh()->effectiveVisibilityLabel());
        $this->assertFalse(Category::publiclyVisible()->whereKey($category->id)->exists());
        $this->assertNull($category->fresh()->published_at);
    }

    public function test_scheduled_category_in_the_future_is_not_publicly_visible(): void
    {
        $category = Category::create([
            'name' => 'Programmata Futura',
            'slug' => 'programmata-futura',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ]);

        $this->assertFalse($category->fresh()->isPubliclyVisible());
        $this->assertSame('Programmata', $category->fresh()->effectiveVisibilityLabel());
        $this->assertFalse(Category::publiclyVisible()->whereKey($category->id)->exists());
    }

    public function test_scheduled_category_at_the_exact_opening_instant_is_publicly_visible(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00', 'UTC'));

        $category = Category::create([
            'name' => 'Programmata Ora',
            'slug' => 'programmata-ora',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now(),
        ]);

        $this->assertTrue($category->fresh()->isPubliclyVisible());
        $this->assertSame('Pubblica', $category->fresh()->effectiveVisibilityLabel());
        $this->assertTrue(Category::publiclyVisible()->whereKey($category->id)->exists());

        Carbon::setTestNow();
    }

    public function test_deactivated_category_is_never_publicly_visible_even_when_published(): void
    {
        $category = Category::create([
            'name' => 'Disattivata Pubblicata',
            'slug' => 'disattivata-pubblicata',
            'is_active' => false,
            'status' => Category::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->assertFalse($category->fresh()->isPubliclyVisible());
        $this->assertSame('Disattivata', $category->fresh()->effectiveVisibilityLabel());
        $this->assertFalse(Category::publiclyVisible()->whereKey($category->id)->exists());
    }

    public function test_published_category_is_publicly_visible(): void
    {
        $category = Category::create([
            'name' => 'Pubblicata',
            'slug' => 'pubblicata-test',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        $this->assertTrue($category->fresh()->isPubliclyVisible());
        $this->assertSame('Pubblica', $category->fresh()->effectiveVisibilityLabel());
        $this->assertTrue(Category::publiclyVisible()->whereKey($category->id)->exists());
        // published senza published_at esplicito: il booted() hook lo
        // imposta a now() al primo salvataggio da "published".
        $this->assertNotNull($category->fresh()->published_at);
    }

    // ---------------------------------------------------------------
    // Non-esposizione sulle superfici pubbliche
    // ---------------------------------------------------------------

    private function scheduledFutureCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Categoria Futura',
            'slug' => 'categoria-futura',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ], $overrides));
    }

    private function draftCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Categoria Bozza',
            'slug' => 'categoria-bozza',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ], $overrides));
    }

    public function test_scheduled_future_category_page_returns_404(): void
    {
        $category = $this->scheduledFutureCategory();

        $this->get(route('categoria', $category->slug))->assertNotFound();
    }

    public function test_draft_category_page_returns_404(): void
    {
        $category = $this->draftCategory();

        $this->get(route('categoria', $category->slug))->assertNotFound();
    }

    public function test_scheduled_future_category_is_excluded_from_header_and_footer_and_category_bar(): void
    {
        $category = $this->scheduledFutureCategory(['sort_order' => -1]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('href="'.route('categoria', $category->slug).'"', false);
        $response->assertDontSee($category->name, false);
    }

    public function test_scheduled_future_category_is_excluded_from_sidebar_topic_cloud(): void
    {
        $category = $this->scheduledFutureCategory();
        $author = $this->author();
        // Serve un articolo pubblicato per rendere la pagina autore
        // eleggibile (Trust Layer V1).
        $this->publishedArticle($author, 'intelligenza-artificiale');

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertDontSee('href="'.route('categoria', $category->slug).'"', false);
    }

    public function test_scheduled_future_category_is_excluded_from_notizie_pill_row(): void
    {
        $category = $this->scheduledFutureCategory();

        $response = $this->get(route('notizie'));

        $response->assertOk();
        $response->assertDontSee('href="'.route('categoria', $category->slug).'"', false);
    }

    public function test_scheduled_future_category_is_excluded_from_search_filter_dropdown(): void
    {
        $category = $this->scheduledFutureCategory();

        $response = $this->get(route('ricerca'));

        $response->assertOk();
        $response->assertDontSee('<option value="'.$category->slug.'"', false);
    }

    public function test_scheduled_future_category_is_excluded_from_trova_search(): void
    {
        $category = $this->scheduledFutureCategory(['name' => 'Trovabilissima']);
        $author = $this->author();
        $this->publishedArticle($author, $category->slug);

        $results = app(TrovaEntitySearchService::class)->search('Trovabilissima');

        $this->assertTrue($results['categories']->isEmpty());
    }

    public function test_published_category_with_a_published_article_is_found_by_trova_search(): void
    {
        $category = Category::create([
            'name' => 'Trovabile Pubblica',
            'slug' => 'trovabile-pubblica',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);
        $author = $this->author();
        $this->publishedArticle($author, $category->slug);

        $results = app(TrovaEntitySearchService::class)->search('Trovabile Pubblica');

        $this->assertTrue($results['categories']->pluck('slug')->contains($category->slug));
    }

    public function test_scheduled_future_category_is_excluded_from_sitemap(): void
    {
        $category = $this->scheduledFutureCategory();

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee('/categoria/'.$category->slug, false);
    }

    public function test_published_category_appears_in_sitemap_with_a_reliable_lastmod(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00', 'UTC'));

        $category = Category::create([
            'name' => 'Categoria Sitemap',
            'slug' => 'categoria-sitemap',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertSee('/categoria/'.$category->slug, false);
        $response->assertSee('<lastmod>'.$category->fresh()->published_at->toAtomString().'</lastmod>', false);

        Carbon::setTestNow();
    }

    public function test_scheduled_future_category_remains_selectable_in_editorial_forms(): void
    {
        $category = $this->scheduledFutureCategory();

        // Category::options() (form editoriali) resta invariato: una
        // categoria bozza/programmata deve restare assegnabile in anticipo.
        $this->assertArrayHasKey($category->slug, Category::options(false));
        $this->assertArrayNotHasKey($category->slug, Category::publicOptions());
    }

    /**
     * Codex review (PR #543): il fallback a config('laboratorio.categories')
     * in publicOptions() copriva SOLO gli slug senza alcuna riga DB — uno
     * slug come 'energia' (anche in config come default legacy) esiste
     * come riga DB creata dalla migration one-time. Un fallback
     * incondizionato quando publiclyVisible() restituisce zero righe
     * l'avrebbe fatto ricomparire come opzione pubblica nello stesso
     * istante in cui viene nascosto in DB, in HomeController/notizie/
     * SearchController.
     */
    public function test_publicoptions_never_resurrects_a_config_legacy_category_hidden_in_db(): void
    {
        Category::where('slug', 'energia')->first()->update(['status' => Category::STATUS_DRAFT]);

        $this->assertArrayNotHasKey('energia', Category::publicOptions());
    }

    /**
     * Contro-prova del test precedente: uno slug realmente assente dal DB
     * (mai migrato) deve invece continuare a comparire dal fallback — il
     * fix non deve diventare più restrittivo del necessario.
     */
    public function test_publicoptions_still_falls_back_to_config_for_a_slug_with_no_db_row_at_all(): void
    {
        Category::query()->delete();

        $publicOptions = Category::publicOptions();

        $this->assertSame(config('laboratorio.categories'), $publicOptions);
    }

    public function test_breadcrumb_and_json_ld_do_not_link_a_scheduled_future_category_on_an_already_published_article(): void
    {
        $category = $this->scheduledFutureCategory(['name' => 'Categoria Non Ancora Pubblica']);
        $author = $this->author();
        $article = $this->publishedArticle($author, $category->slug, ['title' => 'Articolo con categoria non pubblica']);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee($article->title, false);
        // Nessun link (breadcrumb visibile o BreadcrumbList JSON-LD) deve
        // mai puntare a una pagina categoria che risponderebbe 404 — vedi
        // ArticleController::category().
        $response->assertDontSee(route('categoria', $category->slug), false);
    }

    public function test_breadcrumb_and_json_ld_link_a_published_category_on_an_already_published_article(): void
    {
        $category = Category::create([
            'name' => 'Categoria Pubblica Breadcrumb',
            'slug' => 'categoria-pubblica-breadcrumb',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);
        $author = $this->author();
        $article = $this->publishedArticle($author, $category->slug, ['title' => 'Articolo con categoria pubblica']);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee(route('categoria', $category->slug), false);
    }
}
