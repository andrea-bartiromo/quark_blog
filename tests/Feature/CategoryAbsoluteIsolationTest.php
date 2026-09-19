<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 55 (programma "100 cantieri Kairus", dipende dai Cantieri 9,
 * 49).
 *
 * Ispezione diretta prima di questo cantiere: Category::scopePubliclyVisible()/
 * isPubliclyVisible() distinguono tre stati "mai pubblici" (is_active=false,
 * is_active=true+status=draft, is_active=true+status=scheduled con
 * published_at futuro). CategoryScheduledPublicationTest.php copre già, in
 * modo esaustivo, ogni superficie pubblica per lo stato "programmata
 * futura" (pagina categoria diretta, sitemap, header/footer/category-bar,
 * sidebar, ricerca, breadcrumb/JSON-LD) e la pagina categoria diretta per
 * "bozza" — ma NON esiste alcun test che esegua una vera richiesta HTTP
 * verso `/categoria/{slug}` o `sitemap.xml` per una categoria disattivata
 * (`is_active=false`): l'unico test esistente per quello stato
 * (`test_deactivated_category_is_never_publicly_visible_even_when_published`)
 * verifica solo i metodi del modello (`isPubliclyVisible()`, lo scope),
 * mai il confine HTTP realmente esposto — lo stesso vale per l'esclusione
 * di "bozza" dalla sitemap, mai verificata direttamente. Questo file
 * chiude quel gap, verificando i due stati meno coperti (disattivata,
 * bozza) sulle due superfici più rilevanti per l'isolamento reale — l'URL
 * diretto della pagina categoria e la sitemap — con lo stesso schema già
 * in uso per lo stato "programmata futura".
 */
class CategoryAbsoluteIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function deactivatedCategory(): Category
    {
        return Category::create([
            'name' => 'Categoria Disattivata Isolamento',
            'slug' => 'categoria-disattivata-isolamento',
            'is_active' => false,
            'status' => Category::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    private function draftCategory(): Category
    {
        return Category::create([
            'name' => 'Categoria Bozza Isolamento',
            'slug' => 'categoria-bozza-isolamento',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);
    }

    public function test_deactivated_category_page_returns_404_via_a_real_http_request(): void
    {
        $category = $this->deactivatedCategory();

        $this->get(route('categoria', $category->slug))->assertNotFound();
    }

    public function test_deactivated_category_is_excluded_from_sitemap(): void
    {
        $category = $this->deactivatedCategory();

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee('/categoria/'.$category->slug, false);
    }

    public function test_draft_category_is_excluded_from_sitemap(): void
    {
        $category = $this->draftCategory();

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee('/categoria/'.$category->slug, false);
    }

    /**
     * Riattivare una categoria disattivata deve renderla immediatamente
     * raggiungibile di nuovo — la visibilità resta calcolata dal vivo
     * (`scopePubliclyVisible()`), mai una tombstone permanente. Stessa
     * proprietà già dimostrata per il caso "programmata" da
     * CategoryTemporalVisibilityIntegrationTest, qui per "disattivata".
     */
    public function test_reactivated_category_page_becomes_reachable_again(): void
    {
        $category = $this->deactivatedCategory();

        $this->get(route('categoria', $category->slug))->assertNotFound();

        $category->update(['is_active' => true]);

        $this->get(route('categoria', $category->slug))->assertOk();
    }
}
