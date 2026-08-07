<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleDailyView;
use App\Models\ArticleView;
use App\Models\User;
use App\Services\ArticleViewTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ArticleViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(array $overrides = []): Article
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

    // 1. Una view pubblica valida incrementa sia il contatore lifetime sia il bucket giornaliero
    public function test_a_guest_view_increments_the_lifetime_counter_and_the_daily_bucket(): void
    {
        $article = $this->publishedArticle(['views' => 0]);

        $this->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(1, $article->fresh()->views);
        $this->assertSame(1, ArticleDailyView::where('article_id', $article->id)->value('views'));
        $this->assertSame(1, ArticleView::where('article_id', $article->id)->count());
    }

    // 2. Il dedup per sessione preesistente resta invariato: una seconda richiesta
    //    nella stessa sessione non incrementa di nuovo
    public function test_a_second_request_in_the_same_session_does_not_increment_again(): void
    {
        $article = $this->publishedArticle(['views' => 0]);

        $this->withSession([])->get(route('articolo', $article->slug))->assertOk();
        $this->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(1, $article->fresh()->views);
        $this->assertSame(1, ArticleDailyView::where('article_id', $article->id)->value('views'));
    }

    // 3. Traffico interno: un utente autenticato con accesso all'area redazionale
    //    (editor, admin o author — l'unico ruolo di default per un User) non
    //    incrementa né il contatore lifetime né il bucket giornaliero
    public function test_an_authenticated_editor_does_not_increment_views(): void
    {
        $article = $this->publishedArticle(['views' => 0]);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(0, $article->fresh()->views);
        $this->assertSame(0, ArticleDailyView::where('article_id', $article->id)->count());
        $this->assertSame(0, ArticleView::where('article_id', $article->id)->count());
    }

    public function test_an_authenticated_admin_does_not_increment_views(): void
    {
        $article = $this->publishedArticle(['views' => 0]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(0, $article->fresh()->views);
        $this->assertSame(0, ArticleDailyView::where('article_id', $article->id)->count());
    }

    public function test_an_authenticated_author_does_not_increment_views(): void
    {
        // 'author' è il ruolo di default di un User: non esiste nel sistema
        // un concetto di "lettore autenticato non redazionale" — chiunque
        // ha un account User è un collaboratore editoriale a qualche titolo.
        $article = $this->publishedArticle(['views' => 0]);
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(0, $article->fresh()->views);
        $this->assertSame(0, ArticleDailyView::where('article_id', $article->id)->count());
    }

    // 4. Data editoriale Europe/Rome, non UTC: una view poco dopo mezzanotte
    //    a Roma deve finire nel bucket del NUOVO giorno anche se l'istante
    //    UTC è ancora nel giorno precedente
    public function test_the_daily_bucket_uses_the_editorial_rome_date_not_utc(): void
    {
        // 2026-08-08 00:30 Europe/Rome (CEST, +2) = 2026-08-07 22:30 UTC
        Carbon::setTestNow(Carbon::create(2026, 8, 7, 22, 30, 0, 'UTC'));

        $article = $this->publishedArticle(['views' => 0]);

        $this->get(route('articolo', $article->slug))->assertOk();

        $bucket = ArticleDailyView::where('article_id', $article->id)->first();
        $this->assertNotNull($bucket);
        $this->assertSame('2026-08-08', $bucket->date->toDateString());

        Carbon::setTestNow();
    }

    // 5. Più view "reali" distinte nello stesso giorno si sommano correttamente
    //    nel bucket (incremento atomico via upsert, non lettura+scrittura) —
    //    a livello di service: la sessione HTTP nei test persiste tra
    //    richieste dello stesso TestCase, quindi la deduplicazione per
    //    sessione (invariata, non oggetto di questo test) andrebbe
    //    a mascherare il comportamento realmente sotto verifica qui.
    public function test_multiple_distinct_views_accumulate_in_the_same_daily_bucket(): void
    {
        $article = $this->publishedArticle(['views' => 0]);
        $service = app(ArticleViewTrackingService::class);

        $service->recordView($article->fresh());
        $service->recordView($article->fresh());
        $service->recordView($article->fresh());

        $this->assertSame(3, $article->fresh()->views);
        $this->assertSame(3, ArticleDailyView::where('article_id', $article->id)->value('views'));
    }

    // 6. Nessuna regressione: la pagina pubblica articolo continua a
    //    funzionare normalmente per un ospite (comportamento legacy)
    public function test_the_public_article_page_still_renders_normally_for_a_guest(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSeeText($article->title);
    }
}
