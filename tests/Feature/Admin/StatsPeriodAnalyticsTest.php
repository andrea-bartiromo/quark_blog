<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleDailyView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatsPeriodAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 20, 12, 0, 0, Article::EDITORIAL_TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // 1. Default: 30 giorni quando nessun periodo è specificato
    public function test_default_period_is_30_days(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.stats'));

        $response->assertOk();
        $response->assertSeeText('Ultimi 30 giorni');
    }

    // 2. Selezione esplicita 7 / 30 / 90 giorni
    public function test_each_allowed_period_is_selectable(): void
    {
        foreach ([7, 30, 90] as $days) {
            $response = $this->actingAs($this->editor())->get(route('admin.stats', ['period' => $days]));

            $response->assertOk();
            $response->assertSeeText("Ultimi {$days} giorni");
        }
    }

    // 3. Un valore di periodo non ammesso ricade sul default (30), senza errori
    public function test_an_invalid_period_falls_back_to_the_default(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.stats', ['period' => 999]));

        $response->assertOk();
        $response->assertSeeText('Ultimi 30 giorni');
    }

    // 4. I KPI del periodo riflettono i totali giornalieri registrati
    public function test_period_kpis_reflect_the_daily_totals(): void
    {
        $article = $this->article();
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-20', 'views' => 12]); // oggi
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-19', 'views' => 7]);  // ieri

        $response = $this->actingAs($this->editor())->get(route('admin.stats', ['period' => 7]));

        $response->assertOk();
        $response->assertSeeText('Views oggi');
        $response->assertSeeText('12');
        $response->assertSeeText('Views ieri');
        $response->assertSeeText('7');
    }

    // 5. La tabella "Top articoli" è ordinata per views del PERIODO, non lifetime
    public function test_top_articles_table_ranks_by_period_views_not_lifetime(): void
    {
        $lowPeriodHighLifetime = $this->article(['title' => 'Storico ma fermo ora', 'views' => 9000]);
        $highPeriodLowLifetime = $this->article(['title' => 'In crescita reale', 'views' => 5]);

        ArticleDailyView::create(['article_id' => $lowPeriodHighLifetime->id, 'date' => '2026-08-20', 'views' => 1]);
        ArticleDailyView::create(['article_id' => $highPeriodLowLifetime->id, 'date' => '2026-08-20', 'views' => 80]);

        $response = $this->actingAs($this->editor())->get(route('admin.stats', ['period' => 7]));

        $response->assertOk();

        // Isola la tabella "Top articoli" (ancorata all'intestazione di
        // colonna "Views periodo"): il titolo dell'articolo a più alto
        // lifetime compare comunque prima nella pagina, nella card KPI
        // "Top article" storica — cercare nell'intera pagina darebbe un
        // falso negativo non legato all'ordinamento della tabella.
        $content = $response->getContent();
        $tableContent = strstr($content, 'Views periodo');
        $this->assertNotFalse($tableContent);

        $posHigh = strpos($tableContent, 'In crescita reale');
        $posLow = strpos($tableContent, 'Storico ma fermo ora');

        $this->assertNotFalse($posHigh);
        $this->assertNotFalse($posLow);
        $this->assertTrue($posHigh < $posLow, 'L\'articolo con più views nel periodo deve comparire prima in tabella.');
    }

    // 6. Solo utenti con accesso admin vedono la pagina (permessi invariati)
    public function test_guest_cannot_reach_the_stats_page(): void
    {
        $this->get(route('admin.stats'))->assertRedirect(route('login'));
    }

    // 7. L'endpoint charts() restituisce una serie zero-filled per il periodo richiesto
    public function test_charts_endpoint_returns_a_zero_filled_series_for_the_period(): void
    {
        $article = $this->article();
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-20', 'views' => 5]);

        $response = $this->actingAs($this->editor())->getJson(route('admin.stats.charts', ['period' => 7]));

        $response->assertOk();
        $views = collect($response->json('views'));

        $this->assertCount(7, $views);
        $this->assertSame(5, $views->firstWhere('day', '2026-08-20')['views']);
        $this->assertSame(0, $views->firstWhere('day', '2026-08-14')['views']);
    }

    // 8. charts() rispetta il parametro period (7 vs 30 restituiscono lunghezze diverse)
    public function test_charts_endpoint_respects_the_period_parameter(): void
    {
        $response7 = $this->actingAs($this->editor())->getJson(route('admin.stats.charts', ['period' => 7]));
        $response30 = $this->actingAs($this->editor())->getJson(route('admin.stats.charts', ['period' => 30]));

        $this->assertCount(7, $response7->json('views'));
        $this->assertCount(30, $response30->json('views'));
    }

    // 9. Le categorie nel grafico riflettono le views del periodo, non il conteggio articoli
    public function test_charts_endpoint_category_totals_reflect_period_views(): void
    {
        $energia = $this->article(['category' => 'energia']);
        $spazio = $this->article(['category' => 'spazio']);

        // Molti più articoli 'spazio' ma nessuna view: energia deve comunque
        // comparire per prima se ha ricevuto più traffico nel periodo.
        $this->article(['category' => 'spazio']);
        $this->article(['category' => 'spazio']);
        ArticleDailyView::create(['article_id' => $energia->id, 'date' => '2026-08-20', 'views' => 50]);
        ArticleDailyView::create(['article_id' => $spazio->id, 'date' => '2026-08-20', 'views' => 1]);

        $response = $this->actingAs($this->editor())->getJson(route('admin.stats.charts', ['period' => 7]));

        $categories = collect($response->json('categories'));
        $this->assertSame(config('laboratorio.categories.energia'), $categories->first()['label']);
    }
}
