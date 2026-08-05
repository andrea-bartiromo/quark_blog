<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSchedulingUiTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_create_form_shows_the_four_status_options_and_hidden_schedule_fields(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles.create'));

        $response->assertOk();
        $response->assertSee('Bozza');
        $response->assertSee('In revisione');
        $response->assertSee('Programmato');
        $response->assertSee('Pubblicato');
        $response->assertSee('id="published_date"', false);
        $response->assertSee('id="published_time"', false);
        $this->assertMatchesRegularExpression(
            '/id="schedule-fields"\s+hidden\s*>/',
            $response->getContent()
        );
    }

    public function test_edit_page_shows_schedule_banner_for_a_scheduled_article(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo programmato',
            'slug' => 'articolo-programmato-ui',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(4),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('schedule-note', false);
        $response->assertSee('Pubblicazione programmata per', false);
        $this->assertDoesNotMatchRegularExpression(
            '/id="schedule-fields"\s+hidden\s*>/',
            $response->getContent()
        );
    }

    public function test_edit_page_does_not_show_schedule_banner_for_a_draft(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Bozza semplice',
            'slug' => 'bozza-semplice-ui',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        // Verifica il contenuto testuale del banner, non la classe CSS: quest'ultima
        // compare comunque nello script di toggle lato client anche a banner assente.
        $response->assertDontSee('Pubblicazione programmata per', false);
        $this->assertStringNotContainsString(
            'class="schedule-note"',
            $response->getContent()
        );
    }

    public function test_articles_index_shows_scheduled_badge_with_date_and_time(): void
    {
        $editor = $this->editor();
        Article::create([
            'user_id' => $editor->id,
            'title' => 'Nella lista programmato',
            'slug' => 'nella-lista-programmato',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('status--scheduled', false);
        $response->assertSee('Programmato');
    }
}
