<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 11 (programma 100-cantieri Kairus): anteprima admin di sola
 * lettura per una categoria bozza/programmata/disattivata —
 * Admin\CategoryController::preview(). Riusa la stessa vista pubblica
 * categoria.blade.php con gli stessi dati di ArticleController::category()
 * (via CategoryDiscoveryPageData), saltando deliberatamente il controllo
 * isPubliclyVisible().
 */
class CategoryAdminPreviewTest extends TestCase
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

    public function test_editor_can_preview_a_draft_category_that_404s_publicly(): void
    {
        $category = Category::create([
            'name' => 'Bozza Da Rivedere',
            'slug' => 'bozza-da-rivedere',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo della bozza',
            'slug' => 'articolo-della-bozza',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);

        // La pagina pubblica reale continua a rispondere 404.
        $this->get(route('categoria', $category->slug))->assertNotFound();

        // L'anteprima admin invece la mostra, con il banner esplicito.
        $response = $this->actingAs($this->editor())->get(route('admin.categories.preview', $category));

        $response->assertOk();
        $response->assertSee('Anteprima amministrativa', false);
        $response->assertSee('Articolo della bozza');
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_editor_can_preview_a_future_scheduled_category(): void
    {
        $category = Category::create([
            'name' => 'Programmata Da Rivedere',
            'slug' => 'programmata-da-rivedere',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.categories.preview', $category));

        $response->assertOk();
        $response->assertSee('Programmata Da Rivedere');
    }

    public function test_guest_cannot_reach_the_preview_route(): void
    {
        $category = Category::create([
            'name' => 'Bozza Protetta',
            'slug' => 'bozza-protetta',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $this->get(route('admin.categories.preview', $category))->assertRedirect(route('login'));
    }

    /**
     * Finding Codex (P2, PR #558): 'category' non veniva rimosso dalla
     * query string carried tra le pagine, solo 'page' — un
     * ?category=<altro-id> sopravviveva all'array_merge e vinceva su
     * $category->id, facendo puntare i link di paginazione/prev/next
     * all'anteprima di UN'ALTRA categoria. Serve una categoria con più di
     * 6 articoli (una pagina non basta) per generare un link "successiva".
     */
    public function test_pagination_links_ignore_a_category_query_parameter_spoofing_another_category(): void
    {
        $category = Category::create([
            'name' => 'Bozza Con Paginazione',
            'slug' => 'bozza-con-paginazione',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);
        $other = Category::create([
            'name' => 'Altra Categoria',
            'slug' => 'altra-categoria-bozza',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $author = $this->author();
        for ($i = 0; $i < 7; $i++) {
            Article::create([
                'user_id' => $author->id,
                'title' => 'Articolo paginazione '.$i,
                'slug' => 'articolo-paginazione-'.$i,
                'excerpt' => 'Sommario di prova.',
                'body' => '<p>Corpo articolo di prova.</p>',
                'category' => $category->slug,
                'status' => Article::STATUS_PUBLISHED,
                'published_at' => now()->subMinutes($i),
                'read_minutes' => 3,
            ]);
        }

        $response = $this->actingAs($this->editor())
            ->get(route('admin.categories.preview', $category).'?category='.$other->id);
        $content = $response->getContent();

        $response->assertOk();
        // Il rischio reale è nel PATH, non nella query string: il link
        // "successiva" generato dal nostro $pageUrl deve puntare al PATH
        // della categoria richiesta (8), mai a quello dell'altra (9) — un
        // eventuale ?category=9 residuo aggiunto dal componente di
        // paginazione standard di Laravel (withQueryString(), condiviso da
        // ogni pagina paginata del sito) resta innocuo perché il binding
        // della rotta legge sempre il PATH, non la query string.
        $this->assertStringContainsString('admin/categorie/'.$category->id.'/anteprima?page=2', $content);
        $this->assertStringNotContainsString('admin/categorie/'.$other->id.'/anteprima', $content);
    }

    public function test_a_second_page_beyond_the_last_still_404s_in_preview(): void
    {
        $category = Category::create([
            'name' => 'Bozza Senza Seconda Pagina',
            'slug' => 'bozza-senza-seconda-pagina',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.categories.preview', $category).'?page=2');

        $response->assertNotFound();
    }

    public function test_public_category_page_is_unaffected_by_the_preview_route_existing(): void
    {
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblico invariato',
            'slug' => 'articolo-pubblico-invariato',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);

        $response = $this->get(route('categoria', 'intelligenza-artificiale'));

        $response->assertOk();
        $response->assertDontSee('Anteprima amministrativa', false);
        $response->assertDontSee('name="robots" content="noindex,nofollow"', false);
    }
}
