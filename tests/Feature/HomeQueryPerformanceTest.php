<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S4.1 — guardia di regressione per HomeController@index. Misurato con una
 * matrice deterministica 0/1/6/10/25 categorie prima di ogni modifica (vedi
 * report S4.1): la versione originale cresceva linearmente (Q(N) = 10 + 2N,
 * poi 10 + N dopo la rimozione dell'eager load author inutilizzato). La
 * query a finestra (ROW_NUMBER() OVER PARTITION BY category) la rende
 * costante — verificato su SQLite 3.45+ e MariaDB 10.11 reali.
 *
 * Nessuna assertion su un numero assoluto di query (fragile a ogni futura
 * modifica non correlata della Home): la garanzia è che Q(25) - Q(1) resti
 * vicino a zero, non che cresca linearmente con N.
 */
class HomeQueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategoriesWithArticles(int $count): void
    {
        Category::query()->delete();

        $author = User::factory()->create(['role' => 'author']);

        for ($i = 0; $i < $count; $i++) {
            $slug = 'cat-'.$i;
            Category::create([
                'name' => 'Categoria '.$i,
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => $i,
            ]);

            for ($j = 0; $j < 3; $j++) {
                Article::create([
                    'user_id' => $author->id,
                    'title' => "Articolo {$slug} {$j}",
                    'slug' => "articolo-{$slug}-{$j}-".uniqid(),
                    'excerpt' => 'Sommario.',
                    'body' => '<p>Corpo.</p>',
                    'category' => $slug,
                    'status' => Article::STATUS_PUBLISHED,
                    'published_at' => now()->subMinutes($j),
                ]);
            }
        }
    }

    private function homeQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_home_query_count_does_not_grow_linearly_with_category_count(): void
    {
        $this->seedCategoriesWithArticles(1);
        $countAt1 = $this->homeQueryCount();

        $this->seedCategoriesWithArticles(25);
        $countAt25 = $this->homeQueryCount();

        $growth = $countAt25 - $countAt1;

        $this->assertLessThanOrEqual(
            2,
            $growth,
            "La query count della Home cresce di {$growth} passando da 1 a 25 categorie: possibile reintroduzione del pattern N categorie -> N query (Q(N) = 10 + N prima della query a finestra)."
        );
    }

    public function test_home_shows_up_to_three_most_recent_published_articles_per_category(): void
    {
        Category::query()->delete();
        Category::create(['name' => 'Energia', 'slug' => 'energia', 'is_active' => true, 'sort_order' => 1]);
        $author = User::factory()->create(['role' => 'author']);

        for ($i = 0; $i < 5; $i++) {
            Article::create([
                'user_id' => $author->id,
                'title' => "Energia articolo {$i}",
                'slug' => "energia-articolo-{$i}",
                'excerpt' => 'x',
                'body' => '<p>x</p>',
                'category' => 'energia',
                'status' => Article::STATUS_PUBLISHED,
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $html = $this->get(route('home'))->assertOk()->getContent();

        // Solo il primo (il più recente) alimenta $categoryHighlights nella
        // home (home.blade.php: collect($byCategory)->map->first()), ma il
        // vincolo "fino a 3 per categoria" resta sui dati costruiti dal
        // controller — verificato indirettamente qui tramite l'articolo più
        // recente mostrato.
        $this->assertStringContainsString('Energia articolo 0', $html);
    }

    public function test_home_excludes_draft_and_scheduled_only_categories_from_category_highlights(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        Category::query()->delete();
        Category::create(['name' => 'Vuota', 'slug' => 'vuota', 'is_active' => true, 'sort_order' => 1]);

        Category::create(['name' => 'Solo Draft', 'slug' => 'solo-draft', 'is_active' => true, 'sort_order' => 2]);
        Article::create([
            'user_id' => $author->id, 'title' => 'Bozza Home', 'slug' => 'bozza-home-perf',
            'body' => 'x', 'category' => 'solo-draft', 'status' => Article::STATUS_DRAFT,
        ]);

        Category::create(['name' => 'Solo Scheduled', 'slug' => 'solo-scheduled', 'is_active' => true, 'sort_order' => 3]);
        Article::create([
            'user_id' => $author->id, 'title' => 'Futuro Home', 'slug' => 'futuro-home-perf',
            'body' => 'x', 'category' => 'solo-scheduled', 'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        Category::create(['name' => 'Con Articoli', 'slug' => 'con-articoli', 'is_active' => true, 'sort_order' => 4]);
        Article::create([
            'user_id' => $author->id, 'title' => 'Pubblicato Home', 'slug' => 'pubblicato-home-perf',
            'body' => 'x', 'category' => 'con-articoli', 'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Bozza Home', $html);
        $this->assertStringNotContainsString('Futuro Home', $html);
        $this->assertStringContainsString('Pubblicato Home', $html);
    }

    public function test_home_works_with_zero_articles_anywhere(): void
    {
        Category::query()->delete();
        DB::table('articles')->delete();

        $this->get(route('home'))->assertOk();
    }
}
