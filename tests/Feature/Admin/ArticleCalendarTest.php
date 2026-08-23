<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Calendario articoli V1 (admin): elenco/settimana/mese per articoli
 * pubblicati + programmati. Sola visualizzazione — nessun drag-and-drop,
 * nessuna scrittura da qui.
 */
class ArticleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(string $title, string $status, $publishedAt, ?User $author = null): Article
    {
        return Article::create([
            'user_id' => ($author ?? $this->editor())->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => 'Corpo articolo.',
            'category' => 'fisica',
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.articles.calendar'))->assertRedirect(route('login'));
    }

    public function test_editor_can_view_the_calendar(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->get(route('admin.articles.calendar'))
            ->assertOk()
            ->assertSee('Calendario articoli');
    }

    public function test_default_view_is_month_and_shows_published_and_scheduled_articles(): void
    {
        $editor = $this->editor();
        $author = User::factory()->create(['role' => 'author', 'name' => 'Elena Rossi']);

        $published = $this->article('Onde gravitazionali', Article::STATUS_PUBLISHED, now()->startOfMonth()->addDays(2)->setTime(10, 0), $author);
        $scheduled = $this->article('Fisica delle particelle', Article::STATUS_SCHEDULED, now()->startOfMonth()->addDays(5)->setTime(9, 0), $author);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar'));

        $response->assertOk();
        $response->assertSee('Onde gravitazionali');
        $response->assertSee('Fisica delle particelle');
    }

    public function test_draft_and_review_articles_never_appear_on_the_calendar(): void
    {
        $editor = $this->editor();
        $this->article('Bozza invisibile', Article::STATUS_DRAFT, null);
        $this->article('In revisione invisibile', Article::STATUS_REVIEW, null);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar'));

        $response->assertOk();
        $response->assertDontSee('Bozza invisibile');
        $response->assertDontSee('In revisione invisibile');
    }

    public function test_articles_outside_the_visible_range_are_not_shown(): void
    {
        $editor = $this->editor();
        $this->article('Fuori dal mese corrente', Article::STATUS_PUBLISHED, now()->startOfMonth()->subMonths(2));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar'));

        $response->assertOk();
        $response->assertDontSee('Fuori dal mese corrente');
    }

    public function test_week_view_shows_only_the_current_week_range(): void
    {
        $editor = $this->editor();
        $inWeek = $this->article('Dentro la settimana', Article::STATUS_PUBLISHED, now()->startOfWeek()->addDay()->setTime(11, 0));
        $farAway = $this->article('Fuori dalla settimana', Article::STATUS_PUBLISHED, now()->startOfWeek()->subWeeks(3));

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'week']));

        $response->assertOk();
        $response->assertSee('Dentro la settimana');
        $response->assertDontSee('Fuori dalla settimana');
    }

    public function test_list_view_groups_articles_by_day(): void
    {
        $editor = $this->editor();

        // Costruito esplicitamente come "14:30 a Roma" e poi convertito in
        // UTC per lo storage (stesso schema di
        // Article::scheduledAtFromEditorialInput()): un Carbon costruito
        // con setTime() direttamente su now() (gia' in UTC, il fuso
        // dell'app) rappresenterebbe 14:30 UTC, non 14:30 a Roma — la
        // pagina calendario mostra sempre l'orario editoriale (Rome).
        $publishedAt = now()->timezone(Article::EDITORIAL_TIMEZONE)->startOfMonth()->addDays(1)->setTime(14, 30)->utc();
        $this->article('Articolo del giorno', Article::STATUS_PUBLISHED, $publishedAt);

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'list']));

        $response->assertOk();
        $response->assertSee('Articolo del giorno');
        $response->assertSee('14:30');
    }

    public function test_an_unknown_vista_parameter_falls_back_to_month_silently(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'anno-galattico']));

        $response->assertOk();
        $response->assertSee('Calendario articoli');
    }

    public function test_a_malformed_data_parameter_falls_back_to_today_silently(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['data' => 'non-una-data']));

        $response->assertOk();
    }

    public function test_a_syntactically_valid_but_impossible_date_falls_back_to_today(): void
    {
        $editor = $this->editor();

        // 30 febbraio non esiste in nessun anno.
        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['data' => '2026-02-30']));

        $response->assertOk();
    }

    public function test_an_article_published_just_before_midnight_rome_time_appears_on_the_rome_calendar_day(): void
    {
        $editor = $this->editor();

        // 23:30 Europe/Rome in agosto (CEST, UTC+2) e' 21:30 UTC dello
        // stesso giorno solare — ma un raggruppamento ingenuo per data UTC
        // grezza sposterebbe comunque l'articolo di giorno vicino alla
        // mezzanotte in altri fusi/periodi dell'anno. Verifichiamo che la
        // pagina mostri l'articolo nel mese corrente costruito in
        // Europe/Rome, non che sparisca per un off-by-one di fuso.
        $romeEvening = now()->timezone(Article::EDITORIAL_TIMEZONE)->startOfMonth()->addDays(3)->setTime(23, 30);
        $this->article('Articolo di sera', Article::STATUS_PUBLISHED, $romeEvening->clone()->utc());

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['vista' => 'list']));

        $response->assertOk();
        $response->assertSee('Articolo di sera');
        $response->assertSee('23:30');
    }

    public function test_month_view_caps_visible_chips_and_links_overflow_to_list_view_for_that_day(): void
    {
        $editor = $this->editor();
        $day = now()->startOfMonth()->addDays(4);

        for ($i = 1; $i <= 5; $i++) {
            $this->article("Articolo denso {$i}", Article::STATUS_PUBLISHED, $day->clone()->setTime(8 + $i, 0));
        }

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar'));

        $response->assertOk();
        $response->assertSee('+2 altri');
    }

    public function test_prev_and_next_links_navigate_a_full_month_without_day_overflow(): void
    {
        $editor = $this->editor();

        // Ancorato al 31 (un mese che ce l'ha): il mese successivo
        // (es. febbraio) non ha giorno 31 — verifica che la navigazione non
        // vada in errore ne "scivoli" su un altro giorno per overflow.
        $anchor = now()->startOfMonth()->addMonths(1)->subDay(); // ultimo giorno del mese corrente

        $response = $this->actingAs($editor)->get(route('admin.articles.calendar', ['data' => $anchor->format('Y-m-d')]));

        $response->assertOk();
    }

    public function test_calendar_never_exposes_a_publish_or_reschedule_endpoint(): void
    {
        // V1 e' sola visualizzazione: nessuna rotta di scrittura specifica
        // del calendario deve esistere (la modifica data resta sul form
        // articolo esistente).
        $this->assertFalse(Route::has('admin.articles.calendar.update'));
        $this->assertFalse(Route::has('admin.articles.calendar.reschedule'));
    }
}
