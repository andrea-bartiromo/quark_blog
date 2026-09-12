<?php

namespace Tests\Feature\PublicPages;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\PublicPages\PublicPageInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 21 (programma 100-cantieri Kairus). PublicPageInventory è il
 * catalogo di sola lettura, unica fonte di verità, di ogni tipo di
 * pagina pubblica di Kairus — da cui i Cantieri 22-29 (audit
 * HTTP/SEO/JSON-LD, 404/redirect, link rotti, media, performance,
 * tastiera, WCAG) devono iterare, invece di duplicare ciascuno il
 * proprio elenco.
 */
class PublicPageInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_static_page_has_a_resolvable_sample_url(): void
    {
        $pages = app(PublicPageInventory::class)->pages();
        $static = collect($pages)->where('kind', 'static');

        $this->assertGreaterThanOrEqual(14, $static->count());

        foreach ($static as $page) {
            $this->assertIsString($page['sample_url']);
            $this->assertNotSame('', $page['sample_url']);
        }
    }

    public function test_turing_chapters_are_excluded_by_default(): void
    {
        config(['turing.chapters_public' => false]);

        $pages = app(PublicPageInventory::class)->pages();
        $keys = array_column($pages, 'key');

        $this->assertNotContains('turing_enigma', $keys);
        $this->assertContains('turing', $keys);
    }

    public function test_turing_chapters_are_included_when_publicly_enabled(): void
    {
        config(['turing.chapters_public' => true]);

        $pages = app(PublicPageInventory::class)->pages();
        $keys = array_column($pages, 'key');

        $this->assertContains('turing_enigma', $keys);
        $this->assertContains('turing_intelligence', $keys);
    }

    /**
     * `articolo`, `autore` e `percorso` non hanno alcun dato di base
     * (nessun Articolo o Percorso è mai seminato dalle migration), quindi
     * restano senza esempio subito dopo `migrate:fresh`. `categoria`
     * invece HA sempre un esempio: la migration `create_categories_table`
     * semina 7 categorie di base (`config('laboratorio.categories')`),
     * tutte pubbliche di default — comportamento intenzionale, verificato
     * a parte in test_categoria_sample_resolves_from_the_baseline_seeded_categories.
     */
    public function test_articolo_autore_and_percorso_report_no_sample_when_the_database_has_no_public_record(): void
    {
        $pages = collect(app(PublicPageInventory::class)->pages())->keyBy('key');

        foreach (['articolo', 'autore', 'percorso'] as $key) {
            $this->assertNull($pages[$key]['sample_url'], "expected no sample for {$key}");
        }
    }

    public function test_categoria_sample_resolves_from_the_baseline_seeded_categories_by_default(): void
    {
        $pages = collect(app(PublicPageInventory::class)->pages())->keyBy('key');

        $this->assertIsString($pages['categoria']['sample_url']);
        $this->assertStringContainsString('/categoria/', $pages['categoria']['sample_url']);
    }

    /**
     * Finding Codex (P2, PR #569): una categoria legacy presente SOLO in
     * config('laboratorio.categories'), senza alcuna riga nella tabella
     * categories (es. dopo la cancellazione di una categoria di base mai
     * usata), resta comunque raggiungibile su /categoria/{slug} —
     * ArticleController::category() non fa mai abort(404) quando la
     * categoria non esiste in DB. Category::publicOptions() è la stessa
     * fonte di verità già usata dalla superficie pubblica: deve restare
     * l'unica interrogata anche qui, mai una query DB-only che
     * ignorerebbe questo fallback legacy.
     */
    public function test_a_config_only_legacy_category_without_any_database_row_is_still_used_as_a_sample(): void
    {
        Category::query()->delete();

        $pages = collect(app(PublicPageInventory::class)->pages())->keyBy('key');

        $this->assertIsString($pages['categoria']['sample_url']);
        $this->assertStringContainsString('/categoria/', $pages['categoria']['sample_url']);
    }

    public function test_a_draft_category_is_never_used_as_the_sample(): void
    {
        Category::create([
            'name' => 'Bozza Nascosta Esclusiva',
            'slug' => 'bozza-nascosta-esclusiva-inventory-test',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $pages = collect(app(PublicPageInventory::class)->pages())->keyBy('key');

        $this->assertStringNotContainsString('bozza-nascosta-esclusiva-inventory-test', (string) $pages['categoria']['sample_url']);
    }

    public function test_articolo_and_autore_samples_resolve_to_a_published_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Un articolo pubblicato',
            'slug' => 'un-articolo-pubblicato',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);

        $pages = collect(app(PublicPageInventory::class)->pages())->keyBy('key');

        $this->assertSame(route('articolo', ['slug' => $article->slug]), $pages['articolo']['sample_url']);
        $this->assertSame(route('autore', ['user' => $author->id]), $pages['autore']['sample_url']);
    }

    public function test_a_draft_or_scheduled_article_is_never_used_as_a_sample(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Bozza non pubblicata',
            'slug' => 'bozza-non-pubblicata',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);

        $pages = collect(app(PublicPageInventory::class)->pages())->keyBy('key');

        $this->assertNull($pages['articolo']['sample_url']);
        $this->assertNull($pages['autore']['sample_url']);
    }

    public function test_percorso_sample_resolves_to_a_publicly_visible_content_cluster(): void
    {
        ContentCluster::factory()->create(['is_active' => false]);
        $public = ContentCluster::factory()->create(['is_active' => true, 'publish_at' => null]);

        $pages = collect(app(PublicPageInventory::class)->pages())->keyBy('key');

        $this->assertSame(route('percorsi.show', ['slug' => $public->slug]), $pages['percorso']['sample_url']);
    }
}
