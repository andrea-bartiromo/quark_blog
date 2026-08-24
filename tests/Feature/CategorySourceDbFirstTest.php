<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * MISSIONE CATEGORY ARCHITECTURE — FASE 1 (category source debt audit):
 * header/category-bar/sidebar/footer/ricerca/autore/content-clusters.show/
 * SendNewsletterJob/Admin StatsController leggevano tutti
 * config('laboratorio.categories') — lo snapshot statico congelato al
 * deploy — invece della fonte DB-first Category::options() già usata da
 * HomeController e ArticleController. Una categoria creata dall'admin
 * DOPO il deploy (già selezionabile per pubblicare, vedi Admin\Category
 * Controller::store()) restava quindi invisibile — o, per
 * Admin\StatsController, letteralmente esclusa dal breakdown — in ognuna
 * di queste superfici, nonostante fosse già valida ovunque altro.
 *
 * Ogni test qui crea una categoria SOLO via Category::create() (mai
 * toccando config()) per dimostrare che il riconoscimento è davvero
 * DB-first e non dipende dal fatto che "fisica" sia nel frattempo stata
 * aggiunta al config in #253.
 */
class CategorySourceDbFirstTest extends TestCase
{
    use RefreshDatabase;

    private function newCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Nuova Sezione',
            'slug' => 'nuova-sezione',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

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

    public function test_header_nav_recognizes_a_category_created_only_in_the_database(): void
    {
        // La nav mostra solo le prime 3 categorie
        // (@if($loop->index < 3) in components/header.blade.php), ordinate
        // per sort_order poi nome (Category::scopeOrdered()) — le 6
        // categorie originali sono tutte a sort_order 0 (seed one-time),
        // quindi serve un sort_order negativo per garantire la posizione
        // in cima a prescindere dall'ordine alfabetico.
        $this->newCategory(['sort_order' => -1]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Nuova Sezione', false);
    }

    public function test_category_bar_recognizes_a_category_created_only_in_the_database(): void
    {
        $this->newCategory();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('href="'.route('categoria', 'nuova-sezione').'"', false);
        $response->assertSee('Nuova Sezione', false);
    }

    public function test_footer_recognizes_a_category_created_only_in_the_database(): void
    {
        $this->newCategory();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.url('/categoria/nuova-sezione').'"', false);
    }

    public function test_sidebar_topic_cloud_recognizes_a_category_created_only_in_the_database(): void
    {
        $this->newCategory();

        // La sidebar è inclusa dalla pagina autore, tra le pagine
        // pubbliche più semplici che la renderizzano.
        $author = $this->author();

        $this->get(route('autore', $author))
            ->assertOk()
            ->assertSee('Nuova Sezione', false);
    }

    public function test_sidebar_most_read_badge_shows_the_real_label_not_the_raw_slug(): void
    {
        $this->newCategory();
        $author = $this->author();
        $this->publishedArticle($author, 'nuova-sezione', ['views' => 999]);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('badge--nuova-sezione', false);
        $response->assertSee('Nuova Sezione', false);
    }

    public function test_search_filter_dropdown_recognizes_a_category_created_only_in_the_database(): void
    {
        $this->newCategory();

        $this->get(route('ricerca'))
            ->assertOk()
            ->assertSee('<option value="nuova-sezione"', false)
            ->assertSee('Nuova Sezione', false);
    }

    public function test_search_result_badge_shows_the_real_label_not_the_raw_slug(): void
    {
        $this->newCategory();
        $author = $this->author();
        $this->publishedArticle($author, 'nuova-sezione', ['title' => 'Un titolo cercabile']);

        $response = $this->get(route('ricerca', ['q' => 'cercabile']));

        $response->assertOk();
        $response->assertSee('badge--nuova-sezione', false);
        $response->assertSee('Nuova Sezione', false);
    }

    public function test_author_page_badge_shows_the_real_label_not_the_raw_slug(): void
    {
        $this->newCategory();
        $author = $this->author();
        $this->publishedArticle($author, 'nuova-sezione');

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('badge--nuova-sezione', false);
        $response->assertSee('Nuova Sezione', false);
    }

    public function test_content_cluster_show_recognizes_a_category_created_only_in_the_database(): void
    {
        $this->newCategory();
        $author = $this->author();

        $inNewCategory = $this->publishedArticle($author, 'nuova-sezione', ['title' => 'Tappa in nuova sezione']);
        $inOtherCategory = $this->publishedArticle($author, 'energia', ['title' => 'Tappa in energia']);

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($inNewCategory->id, ['position' => 10]);
        $cluster->articles()->attach($inOtherCategory->id, ['position' => 20]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('Nuova Sezione', false);
    }

    public function test_admin_stats_per_category_breakdown_no_longer_silently_excludes_a_db_only_category(): void
    {
        // admin/stats.blade.php non renderizza affatto $byCategory (nessun
        // riferimento nel template): la verifica è quindi sui dati passati
        // alla view, non sull'HTML — altrimenti il test non potrebbe MAI
        // distinguere "categoria esclusa dal breakdown" da "sezione non
        // visualizzata", che sono due problemi diversi (solo il primo è
        // quello corretto qui).
        $this->newCategory();
        $editor = User::factory()->create(['role' => 'editor']);
        $this->publishedArticle($editor, 'nuova-sezione', ['views' => 42]);

        $response = $this->actingAs($editor)->get(route('admin.stats'));

        $response->assertOk();
        $byCategory = $response->viewData('byCategory');
        $this->assertArrayHasKey('nuova-sezione', $byCategory);
        $this->assertSame('Nuova Sezione', $byCategory['nuova-sezione']['label']);
    }

    public function test_newsletter_html_shows_the_real_label_not_the_raw_slug(): void
    {
        $this->newCategory();
        $author = $this->author();
        $article = $this->publishedArticle($author, 'nuova-sezione', ['title' => 'Articolo per la newsletter']);

        $subscriber = Newsletter::create([
            'email' => 'lettore@example.com',
            'confirmed' => true,
        ]);

        $job = new SendNewsletterJob($subscriber);
        $method = new ReflectionMethod(SendNewsletterJob::class, 'buildHtml');
        $method->setAccessible(true);

        $html = $method->invoke($job, collect([$article]), '', $subscriber);

        $this->assertStringContainsString('Nuova Sezione', $html);
        $this->assertStringNotContainsString('>nuova-sezione<', $html);
    }

    public function test_fisica_badge_color_is_defined_alongside_every_other_category(): void
    {
        $css = file_get_contents(public_path('css/style.css'));
        $adminCss = file_get_contents(public_path('css/admin.css'));

        $this->assertStringContainsString('.badge--fisica', $css);
        $this->assertStringContainsString('.badge--fisica', $adminCss);
    }

    public function test_all_six_original_categories_remain_visible_everywhere_after_the_db_first_switch(): void
    {
        // Non regressione: le 6 categorie originali (già presenti nel DB
        // via la migration one-time, seedata da config al momento della
        // creazione) devono continuare a comparire ovunque esattamente
        // come prima, ora che la fonte è Category::options() e non più
        // config() direttamente.
        $response = $this->get(route('home'));

        $response->assertOk();
        foreach (config('laboratorio.categories') as $slug => $label) {
            // Senza `false`: Blade esegue l'escape HTML di default (es.
            // "Energia & Clima" diventa "Energia &amp; Clima" nel markup
            // renderizzato) — assertSee() con l'escape di default confronta
            // correttamente contro il testo visibile, non contro i byte
            // grezzi dell'etichetta.
            $response->assertSee($label);
        }
    }
}
