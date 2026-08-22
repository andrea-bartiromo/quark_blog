<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeCategoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La migrazione delle categorie ne seed già 6 di default (dallo stesso
     * config('laboratorio.categories') usato come fallback altrove): usa
     * updateOrCreate cosi' i test possono regolarne lo stato senza collidere
     * con lo slug unique già popolato da RefreshDatabase.
     */
    private function category(string $slug, array $overrides = []): Category
    {
        return Category::updateOrCreate(
            ['slug' => $slug],
            array_merge([
                'name' => ucfirst($slug),
                'is_active' => true,
                'sort_order' => 0,
            ], $overrides)
        );
    }

    private function article(string $category, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di '.$category,
            'slug' => 'articolo-'.$category.'-'.uniqid(),
            'body' => 'Testo di prova.',
            'category' => $category,
            'status' => 'published',
            'featured' => false,
            'published_at' => now(),
            'read_minutes' => 3,
        ], $overrides));
    }

    /**
     * La home include anche una barra di navigazione categorie sempre
     * presente (component category-bar), che elenca incondizionatamente
     * tutte le categorie di config('laboratorio.categories'). Per non
     * confonderla con la sezione "Esplora le categorie" (che mostra solo le
     * categorie con almeno un articolo pubblicato), le assertion isolano il
     * contenuto del solo carosello home-category-carousel.
     *
     * L'ancora usata per individuare l'inizio della sezione è il tag di
     * apertura <section class="home-category-section" ...> (non la stringa
     * "home-category-carousel", che compare anche prima, nell'head, nel link
     * al foglio di stile home-category-carousel.css — usarla come ancora
     * farebbe iniziare l'estrazione dall'head invece che dalla sezione).
     */
    private function categoryGridHtml(string $fullHtml): string
    {
        $start = strpos($fullHtml, 'home-category-section');
        $end = strpos($fullHtml, '</section>', $start);

        $this->assertNotFalse($start, 'Sezione "Esplora le categorie" non trovata nella home.');

        return substr($fullHtml, $start, $end - $start);
    }

    // 1. Categoria con articoli pubblicati visibile nella home
    public function test_category_with_a_published_article_is_visible_on_home(): void
    {
        $this->category('spazio', ['name' => 'Spazio']);
        $this->article('spazio');

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringContainsString(route('categoria', 'spazio'), $grid);
        $this->assertStringContainsString('Spazio', $grid);
    }

    // 2. PR #237 ("fix: show active categories without articles on home"):
    // le categorie attive compaiono nel carosello anche senza articoli
    // pubblicati, cosi' una categoria appena creata in admin e' visibile
    // subito invece di restare invisibile finche' non riceve un articolo.
    public function test_active_category_without_any_published_article_still_appears(): void
    {
        $this->category('ambiente', ['name' => 'Ambiente']);

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringContainsString(route('categoria', 'ambiente'), $grid);
        $this->assertStringContainsString('Ambiente', $grid);
    }

    // 3. Stessa logica del punto 2: da PR #237 la tile non e' costruita a
    // partire da un articolo (vedi home.blade.php: $categoryHighlights e'
    // ora derivato da $categoryOptions, non da $byCategory), quindi lo stato
    // dell'unico articolo della categoria — qui una bozza — non ne decide
    // piu' la presenza.
    public function test_a_category_whose_only_article_is_a_draft_still_appears(): void
    {
        $this->category('salute', ['name' => 'Salute']);
        $this->article('salute', ['status' => 'draft', 'published_at' => null]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringContainsString(route('categoria', 'salute'), $grid);
    }

    // 4. Stessa logica del punto 3, per un articolo programmato nel futuro.
    public function test_a_category_whose_only_article_is_scheduled_still_appears(): void
    {
        $this->category('energia', ['name' => 'Energia']);
        $this->article('energia', ['published_at' => now()->addDays(3)]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringContainsString(route('categoria', 'energia'), $grid);
    }

    // La sola condizione che ancora esclude una categoria dal carosello e'
    // is_active=false (Category::options() applica lo scope active()): a
    // differenza dell'assenza di articoli pubblicati, questo confine non e'
    // cambiato dalla PR #237. Verificato qui con un articolo pubblicato
    // presente, cosi' l'assenza e' attribuibile solo a is_active=false.
    public function test_inactive_category_does_not_appear(): void
    {
        $this->category('salute', ['name' => 'Salute', 'is_active' => false]);
        $this->article('salute');

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringNotContainsString(route('categoria', 'salute'), $grid);
    }

    // Regressione: una categoria il cui unico articolo e quello in evidenza
    // deve restare visibile (era la causa della sparizione dalla home).
    public function test_category_whose_only_article_is_the_featured_one_is_still_visible(): void
    {
        $this->category('intelligenza-artificiale', ['name' => 'Intelligenza Artificiale']);
        $this->article('intelligenza-artificiale', ['featured' => true]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringContainsString(route('categoria', 'intelligenza-artificiale'), $grid);
        $this->assertStringContainsString('Intelligenza Artificiale', $grid);
    }

    // 5. Nessuna duplicazione delle categorie
    public function test_categories_are_not_duplicated_on_home(): void
    {
        $this->category('spazio', ['name' => 'Spazio']);
        $this->article('spazio');
        $this->article('spazio');
        $this->article('spazio');

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $matches = substr_count($grid, route('categoria', 'spazio'));
        $this->assertSame(1, $matches);
    }

    // 6. Ordine corretto delle categorie (sort_order, poi nome)
    public function test_categories_appear_in_sort_order(): void
    {
        $this->category('societa', ['name' => 'Società', 'sort_order' => 2]);
        $this->category('spazio', ['name' => 'Spazio', 'sort_order' => 1]);
        $this->article('societa');
        $this->article('spazio');

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertLessThan(
            strpos($grid, route('categoria', 'societa')),
            strpos($grid, route('categoria', 'spazio'))
        );
    }
}
