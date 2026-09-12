<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 10 (programma 100-cantieri Kairus): test di integrazione sulla
 * visibilità TEMPORALE delle categorie — dipende dal Cantiere 9.
 *
 * CategoryScheduledPublicationTest.php copre già, in modo esaustivo, ogni
 * superficie pubblica a istanti di tempo fissi e isolati (bozza, programmata
 * futura, programmata all'istante esatto, disattivata, pubblicata — ciascuna
 * con la propria categoria di fixture). Questo file aggiunge ciò che manca
 * lì: UNA sola categoria che attraversa realmente il tempo, verificata PRIMA
 * e DOPO l'istante di apertura sulle stesse superfici, nello stesso test —
 * a differenza degli articoli (vedi
 * ScheduledArticleVisibilityTest::test_full_lifecycle_draft_to_scheduled_to_auto_published_and_visible,
 * che deve eseguire `articles:publish-scheduled` per far avanzare
 * Article::status da scheduled a published), Category::scopePubliclyVisible()
 * calcola la visibilità dal vivo (published_at <= now()) senza mai
 * modificare la colonna status: nessun comando batch esiste o serve per le
 * categorie. Questo test dimostra esplicitamente quella proprietà, per
 * evitare che in futuro qualcuno introduca un comando "category:publish-
 * scheduled" credendo che ne manchi uno.
 */
class CategoryTemporalVisibilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_scheduled_category_becomes_visible_everywhere_the_moment_time_crosses_its_opening_instant(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

        $category = Category::create([
            'name' => 'Categoria Cronologica',
            'slug' => 'categoria-cronologica',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => Carbon::parse('2026-06-01 09:05:00', 'UTC'),
            'sort_order' => -1,
        ]);

        $author = User::factory()->create(['role' => 'author']);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo della categoria cronologica',
            'slug' => 'articolo-categoria-cronologica',
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category->slug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => Carbon::parse('2026-06-01 08:00:00', 'UTC'),
            'read_minutes' => 3,
        ]);

        // ---- PRIMA dell'istante di apertura: invisibile ovunque ----
        $this->assertFalse($category->fresh()->isPubliclyVisible());
        $this->get(route('categoria', $category->slug))->assertNotFound();

        $home = $this->get(route('home'));
        $home->assertOk();
        $home->assertDontSee('href="'.route('categoria', $category->slug).'"', false);

        $notizie = $this->get(route('notizie'));
        $notizie->assertOk();
        $notizie->assertDontSee('href="'.route('categoria', $category->slug).'"', false);

        $sitemap = $this->get(route('sitemap'));
        $sitemap->assertOk();
        $sitemap->assertDontSee('/categoria/'.$category->slug, false);

        // ---- Il tempo passa oltre l'istante di apertura: NESSUN comando
        // batch viene eseguito qui (a differenza di articles:publish-
        // scheduled) — solo l'orologio virtuale avanza. ----
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:05:01', 'UTC'));

        // La riga in DB non è stata toccata: status resta 'scheduled'.
        $category->refresh();
        $this->assertSame(Category::STATUS_SCHEDULED, $category->status);

        // ---- DOPO l'istante di apertura: visibile ovunque, stessa riga ----
        $this->assertTrue($category->fresh()->isPubliclyVisible());
        $this->get(route('categoria', $category->slug))->assertOk();

        $home = $this->get(route('home'));
        $home->assertOk();
        $home->assertSee('href="'.route('categoria', $category->slug).'"', false);

        $notizie = $this->get(route('notizie'));
        $notizie->assertOk();
        $notizie->assertSee('href="'.route('categoria', $category->slug).'"', false);

        $sitemap = $this->get(route('sitemap'));
        $sitemap->assertOk();
        $sitemap->assertSee('/categoria/'.$category->slug, false);

        Carbon::setTestNow();
    }
}
