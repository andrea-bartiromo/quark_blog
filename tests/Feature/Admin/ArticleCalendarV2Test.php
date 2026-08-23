<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Calendario articoli V2: filtri stato/categoria/autore, riepilogo
 * pubblicati/programmati per periodo, vista "Prossime 4 settimane". Resta
 * sola visualizzazione — nessuna scrittura introdotta da questa evoluzione.
 */
class ArticleCalendarV2Test extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(string $title, string $status, $publishedAt, ?User $author = null, string $category = 'fisica'): Article
    {
        return Article::create([
            'user_id' => ($author ?? $this->editor())->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => 'Corpo articolo.',
            'category' => $category,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }

    // ── Filtri ───────────────────────────────────────────────────────

    public function test_status_filter_shows_only_published_articles(): void
    {
        $editor = $this->editor();
        $this->article('Pubblicato visibile', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2));
        $this->article('Programmato nascosto', Article::STATUS_SCHEDULED, now()->startOfMonth()->addDays(3));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['stato' => Article::STATUS_PUBLISHED]));

        $response->assertOk();
        $response->assertSee('Pubblicato visibile');
        $response->assertDontSee('Programmato nascosto');
    }

    public function test_status_filter_shows_only_scheduled_articles(): void
    {
        $editor = $this->editor();
        $this->article('Pubblicato nascosto', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2));
        $this->article('Programmato visibile', Article::STATUS_SCHEDULED, now()->startOfMonth()->addDays(3));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['stato' => Article::STATUS_SCHEDULED]));

        $response->assertOk();
        $response->assertDontSee('Pubblicato nascosto');
        $response->assertSee('Programmato visibile');
    }

    public function test_an_invalid_status_value_is_ignored_silently(): void
    {
        $editor = $this->editor();
        $this->article('Sempre visibile', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['stato' => 'draft']));

        $response->assertOk();
        $response->assertSee('Sempre visibile');
    }

    public function test_category_filter_narrows_to_a_single_category(): void
    {
        $editor = $this->editor();
        $this->article('Articolo fisica', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2), category: 'fisica');
        $this->article('Articolo spazio', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2), category: 'spazio');

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['categoria' => 'spazio']));

        $response->assertOk();
        $response->assertDontSee('Articolo fisica');
        $response->assertSee('Articolo spazio');
    }

    public function test_author_filter_narrows_to_a_single_author(): void
    {
        $editor = $this->editor();
        $elena = User::factory()->create(['role' => 'author', 'name' => 'Elena Rossi']);
        $marco = User::factory()->create(['role' => 'author', 'name' => 'Marco Bianchi']);
        $this->article('Articolo di Elena', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2), $elena);
        $this->article('Articolo di Marco', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2), $marco);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['autore' => $elena->id]));

        $response->assertOk();
        $response->assertSee('Articolo di Elena');
        $response->assertDontSee('Articolo di Marco');
    }

    public function test_filters_combine_with_and_semantics(): void
    {
        $editor = $this->editor();
        $elena = User::factory()->create(['role' => 'author', 'name' => 'Elena Rossi']);
        $this->article('Match esatto', Article::STATUS_SCHEDULED, now()->startOfMonth()->addDays(2), $elena, 'fisica');
        $this->article('Autore giusto stato sbagliato', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2), $elena, 'fisica');

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', [
            'stato' => Article::STATUS_SCHEDULED,
            'categoria' => 'fisica',
            'autore' => $elena->id,
        ]));

        $response->assertOk();
        $response->assertSee('Match esatto');
        $response->assertDontSee('Autore giusto stato sbagliato');
    }

    // ── Conteggi per periodo ─────────────────────────────────────────

    public function test_period_counts_reflect_published_and_scheduled_totals(): void
    {
        $editor = $this->editor();
        $this->article('Pub 1', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(1));
        $this->article('Pub 2', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2));
        $this->article('Sched 1', Article::STATUS_SCHEDULED, now()->startOfMonth()->addDays(3));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar'));

        $response->assertOk();
        $response->assertSeeText('2 pubblicati');
        $response->assertSeeText('1 programmato');
    }

    public function test_period_counts_are_zero_with_no_articles(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar'));

        $response->assertOk();
        $response->assertSeeText('0 pubblicati');
        $response->assertSeeText('0 programmati');
    }

    // ── Vista "Prossime 4 settimane" ─────────────────────────────────

    public function test_next4_view_shows_an_article_two_weeks_from_now(): void
    {
        $editor = $this->editor();
        $this->article('Tra due settimane', Article::STATUS_SCHEDULED, now()->addWeeks(2)->setTime(10, 0));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'next4']));

        $response->assertOk();
        $response->assertSee('Tra due settimane');
    }

    public function test_next4_view_excludes_an_article_five_weeks_from_now(): void
    {
        $editor = $this->editor();
        $this->article('Tra cinque settimane', Article::STATUS_SCHEDULED, now()->addWeeks(5)->setTime(10, 0));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'next4']));

        $response->assertOk();
        $response->assertDontSee('Tra cinque settimane');
    }

    public function test_next4_view_excludes_an_article_from_the_past(): void
    {
        $editor = $this->editor();
        $this->article('Settimana scorsa', Article::STATUS_PUBLISHED, now()->subWeek());

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'next4']));

        $response->assertOk();
        $response->assertDontSee('Settimana scorsa');
    }

    public function test_next4_view_shows_an_honest_empty_state_with_no_upcoming_articles(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'next4']));

        $response->assertOk();
        $response->assertSee('Nessun articolo pubblicato o programmato nelle prossime 4 settimane.');
    }

    // ── Nessuna leak / regressione ────────────────────────────────────

    public function test_filters_never_leak_draft_or_review_articles(): void
    {
        $editor = $this->editor();
        $this->article('Bozza mai visibile', Article::STATUS_DRAFT, null);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['categoria' => 'fisica']));

        $response->assertOk();
        $response->assertDontSee('Bozza mai visibile');
    }

    public function test_calendar_query_count_stays_bounded_with_filters_applied(): void
    {
        $editor = $this->editor();
        for ($i = 0; $i < 6; $i++) {
            $this->article("Articolo {$i}", Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays($i));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($editor)->get(route('admin.articles.calendar', ['stato' => Article::STATUS_PUBLISHED, 'categoria' => 'fisica']))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Articoli (1) + author eager load (1) + categoryFilterOptions (1)
        // + authorFilterOptions (2: distinct user_id + users) + auth/sessione
        // — sempre un piccolo numero fisso, mai una query per articolo.
        $this->assertLessThanOrEqual(15, $queryCount);
    }
}
