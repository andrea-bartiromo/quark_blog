<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleDailyView;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleDailyViewTest extends TestCase
{
    use RefreshDatabase;

    private function article(): Article
    {
        return Article::create([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    // 1. Una riga per (article_id, date), mai un log di singole pageview
    public function test_a_daily_view_row_can_be_created_and_belongs_to_its_article(): void
    {
        $article = $this->article();

        $row = ArticleDailyView::create([
            'article_id' => $article->id,
            'date' => '2026-08-07',
            'views' => 5,
        ]);

        $this->assertSame($article->id, $row->article->id);
        $this->assertSame(5, $row->views);
        $this->assertSame('2026-08-07', $row->date->toDateString());
    }

    // 2. Vincolo unique (article_id, date): non due righe per lo stesso giorno
    public function test_unique_constraint_prevents_two_rows_for_the_same_article_and_day(): void
    {
        $article = $this->article();

        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-07', 'views' => 1]);

        $this->expectException(QueryException::class);
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-07', 'views' => 1]);
    }

    // 3. Cancellare l'articolo cancella anche i suoi bucket giornalieri (cascade)
    public function test_deleting_the_article_cascades_to_its_daily_views(): void
    {
        $article = $this->article();
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-07', 'views' => 3]);

        $article->delete();

        $this->assertSame(0, ArticleDailyView::where('article_id', $article->id)->count());
    }
}
