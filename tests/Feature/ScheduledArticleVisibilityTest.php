<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica end-to-end che un articolo 'scheduled' non sia mai visibile
 * pubblicamente prima del momento programmato, in nessuno dei punti
 * dell'app che espongono contenuti pubblicati (pagine pubbliche, pagina
 * autore, sitemap, news sitemap, feed RSS, home).
 */
class ScheduledArticleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    public function test_scheduled_article_is_not_visible_on_public_article_page(): void
    {
        $author = $this->author();
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo futuro',
            'slug' => 'articolo-futuro',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertNotFound();
    }

    public function test_scheduled_article_does_not_appear_on_homepage(): void
    {
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo futuro homepage',
            'slug' => 'articolo-futuro-homepage',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Articolo futuro homepage');
    }

    public function test_scheduled_article_does_not_appear_in_category_or_index_listing(): void
    {
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo futuro categoria',
            'slug' => 'articolo-futuro-categoria',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('categoria', 'energia'));

        $response->assertOk();
        $response->assertDontSee('Articolo futuro categoria');
    }

    public function test_scheduled_article_excluded_from_author_page(): void
    {
        $author = $this->author();
        $published = Article::create([
            'user_id' => $author->id,
            'title' => 'Pubblicato autore',
            'slug' => 'pubblicato-autore',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Programmato autore',
            'slug' => 'programmato-autore',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('Pubblicato autore');
        $response->assertDontSee('Programmato autore');
    }

    public function test_author_page_excludes_published_articles_with_a_future_date(): void
    {
        // Difesa in profondità: anche se un articolo avesse status=published
        // con una data futura (stato oggi non raggiungibile dall'interfaccia,
        // dato l'invariante del model), la pagina autore non deve mostrarlo.
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Pubblicato nel futuro',
            'slug' => 'pubblicato-nel-futuro',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertDontSee('Pubblicato nel futuro');
    }

    public function test_scheduled_article_excluded_from_sitemap(): void
    {
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Programmato sitemap',
            'slug' => 'programmato-sitemap',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee('programmato-sitemap');
    }

    public function test_scheduled_article_excluded_from_news_sitemap_even_if_within_two_days(): void
    {
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Programmato news sitemap',
            'slug' => 'programmato-news-sitemap',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addHours(6),
        ]);

        $response = $this->get(route('news-sitemap'));

        $response->assertOk();
        $response->assertDontSee('programmato-news-sitemap');
    }

    public function test_scheduled_article_excluded_from_rss_feed(): void
    {
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Programmato feed rss',
            'slug' => 'programmato-feed-rss',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('feed'));

        $response->assertOk();
        $response->assertDontSee('Programmato feed rss');
    }

    public function test_full_lifecycle_draft_to_scheduled_to_auto_published_and_visible(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        // 1. Creazione come bozza dall'Admin.
        $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Ciclo di vita completo',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
        ])->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Ciclo di vita completo')->firstOrFail();
        $this->assertNull($article->published_at);

        // 2. Passa a "Programmato" con data/ora futura (inserita come orario
        // locale Europe/Rome, come farebbe la redazione dal form).
        $romeTarget = now()->timezone(Article::EDITORIAL_TIMEZONE)->addMinutes(5);

        $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'body' => $article->body,
            'category' => $article->category,
            'status' => Article::STATUS_SCHEDULED,
            'published_date' => $romeTarget->format('Y-m-d'),
            'published_time' => $romeTarget->format('H:i'),
        ])->assertRedirect(route('admin.articles'));

        $article->refresh();
        $this->assertSame(Article::STATUS_SCHEDULED, $article->status);

        // 3. Non ancora visibile pubblicamente.
        $this->get(route('articolo', $article->slug))->assertNotFound();

        // 4. Il tempo passa: la data programmata è ora nel passato.
        $article->forceFill(['published_at' => now()->subMinute()])->save();

        // 5. Lo scheduler pubblica automaticamente.
        $this->artisan('articles:publish-scheduled')->assertExitCode(0);

        $article->refresh();
        $this->assertSame(Article::STATUS_PUBLISHED, $article->status);

        // 6. Ora è visibile pubblicamente.
        $this->get(route('articolo', $article->slug))->assertOk();
    }
}
