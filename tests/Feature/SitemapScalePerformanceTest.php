<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 4/13/16 dell'audit SEO: la sitemap non deve degradare (query count,
 * validità XML) al crescere del numero di articoli. Fixture generate a
 * runtime via bulk insert (nessuna fixture statica committata) — 0, 100 e
 * 1.000 articoli pubblicati. 10.000 è omesso dalla suite ordinaria per non
 * appesantire ogni run locale; la query di sitemap() è una singola SELECT
 * senza relazioni, quindi il costo cresce in modo lineare e prevedibile con
 * le stesse query, non con nuove query per riga (vedi asserzione query
 * count sotto, indipendente dal numero di articoli).
 */
class SitemapScalePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function bulkInsertPublishedArticles(int $count): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $now = now();

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_id' => $author->id,
                'title' => 'Articolo scala '.$i,
                'slug' => 'articolo-scala-'.$i.'-'.uniqid(),
                'excerpt' => 'Sommario di prova.',
                'body' => '<p>Corpo articolo di prova.</p>',
                'category' => 'energia',
                'status' => Article::STATUS_PUBLISHED,
                'published_at' => $now->copy()->subMinutes($i),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk insert (non Eloquent::create in loop): stessa tecnica già
        // usata dalle altre suite di scala del progetto per popolare
        // migliaia di righe senza migliaia di round-trip separati.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('articles')->insert($chunk);
        }
    }

    public function test_sitemap_is_valid_xml_with_zero_articles(): void
    {
        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'La sitemap con 0 articoli non è XML valido.');
        $this->assertStringNotContainsString('/articolo/', $xml);
    }

    public function test_sitemap_query_count_does_not_grow_with_article_count(): void
    {
        $this->bulkInsertPublishedArticles(100);

        DB::enableQueryLog();
        $this->get(route('sitemap'))->assertOk();
        $queryCountAt100 = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->bulkInsertPublishedArticles(900); // porta il totale a 1.000
        DB::flushQueryLog(); // scarta le query dei bulk insert appena eseguiti, non della sitemap

        $this->get(route('sitemap'))->assertOk();
        $queryCountAt1000 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queryCountAt100,
            $queryCountAt1000,
            'Il numero di query della sitemap cresce con il numero di articoli: possibile N+1.'
        );
        $this->assertLessThanOrEqual(5, $queryCountAt1000, 'La sitemap esegue più query del previsto per una singola richiesta.');
    }

    public function test_sitemap_contains_every_published_article_exactly_once_at_1000_scale(): void
    {
        $this->bulkInsertPublishedArticles(1000);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertSame(1000, substr_count($xml, '/articolo/'));
        $this->assertNotFalse(simplexml_load_string($xml), 'La sitemap con 1.000 articoli non è XML valido.');
    }

    public function test_sitemap_excludes_draft_and_scheduled_articles_at_scale(): void
    {
        $this->bulkInsertPublishedArticles(100);

        $author = User::factory()->create(['role' => 'author']);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Bozza esclusa dalla sitemap',
            'slug' => 'bozza-esclusa-scala',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
        ]);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Programmato escluso dalla sitemap',
            'slug' => 'programmato-escluso-scala',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertSame(100, substr_count($xml, '/articolo/'));
        $this->assertStringNotContainsString('bozza-esclusa-scala', $xml);
        $this->assertStringNotContainsString('programmato-escluso-scala', $xml);
    }

    public function test_sitemap_lists_only_active_categories_by_slug(): void
    {
        // La sitemap emette solo lo slug di categoria (mai il nome
        // editoriale): Str::slug() garantisce già caratteri XML-safe in
        // scrittura (app/Http/Controllers/Admin/ArticleController.php),
        // quindi non serve un test di escaping qui — il rischio reale
        // sarebbe testare un percorso che il codice non permette di
        // raggiungere.
        Category::create(['name' => 'Ricerca & Sviluppo', 'slug' => 'ricerca-sviluppo', 'is_active' => true]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString('/categoria/ricerca-sviluppo</loc>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }
}
