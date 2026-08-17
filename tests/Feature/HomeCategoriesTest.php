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

    // 2. Categoria senza articoli: non compare (la tile richiede un articolo da rappresentarla)
    public function test_category_without_any_published_article_does_not_appear(): void
    {
        $this->category('ambiente', ['name' => 'Ambiente']);

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringNotContainsString(route('categoria', 'ambiente'), $grid);
    }

    // 3. Articoli in bozza esclusi
    public function test_draft_articles_do_not_make_a_category_appear(): void
    {
        $this->category('salute', ['name' => 'Salute']);
        $this->article('salute', ['status' => 'draft', 'published_at' => null]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringNotContainsString(route('categoria', 'salute'), $grid);
    }

    // 4. Articoli programmati (published_at futuro) esclusi
    public function test_scheduled_future_articles_do_not_make_a_category_appear(): void
    {
        $this->category('energia', ['name' => 'Energia']);
        $this->article('energia', ['published_at' => now()->addDays(3)]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $grid = $this->categoryGridHtml($response->getContent());
        $this->assertStringNotContainsString(route('categoria', 'energia'), $grid);
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
