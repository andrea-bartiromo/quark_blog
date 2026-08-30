<?php

namespace Tests\Feature\Measurement;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\EditorialContinuityEvent;
use App\Models\Newsletter;
use App\Models\User;
use App\Services\Telemetry\EditorialEventContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Measurement Closeout (Missione 1-2) — copertura end-to-end dei producer
 * reali: le pagine pubbliche devono emettere gli eventi del contratto
 * canonico, restare fail-safe se la scrittura fallisce, e non contare mai il
 * traffico redazionale interno.
 */
class EditorialContinuityRecorderTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ], $overrides));
    }

    public function test_visiting_a_published_article_records_an_article_viewed_event(): void
    {
        $article = $this->publishedArticle();

        $this->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(1, EditorialContinuityEvent::where('event_name', EditorialEventContract::ARTICLE_VIEWED)
            ->where('article_id', $article->id)
            ->count());
    }

    public function test_reloading_the_same_article_in_the_same_session_does_not_double_count(): void
    {
        $article = $this->publishedArticle();

        $this->withSession([])->get(route('articolo', $article->slug))->assertOk();
        $this->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(1, EditorialContinuityEvent::where('event_name', EditorialEventContract::ARTICLE_VIEWED)
            ->where('article_id', $article->id)
            ->count());
    }

    public function test_editorial_staff_traffic_is_never_recorded(): void
    {
        $article = $this->publishedArticle();
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame(0, EditorialContinuityEvent::count());
    }

    public function test_event_rows_never_persist_a_raw_session_id(): void
    {
        $article = $this->publishedArticle();

        $this->get(route('articolo', $article->slug))->assertOk();

        $event = EditorialContinuityEvent::firstOrFail();

        // Lo pseudonimo è un digest esadecimale a 64 caratteri — mai l'id di
        // sessione Laravel grezzo (che ha una forma diversa e non è mai
        // esadecimale puro a lunghezza fissa 64).
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $event->session_key);
    }

    public function test_a_percorso_page_view_records_a_path_viewed_event_and_link_available_events(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $pillar = $this->publishedArticle(['user_id' => $editor->id]);
        $second = $this->publishedArticle(['user_id' => $editor->id]);

        $cluster = ContentCluster::factory()->create(['is_active' => true, 'pillar_article_id' => $pillar->id]);
        $cluster->articles()->attach([
            $pillar->id => ['position' => 10, 'is_primary' => true],
            $second->id => ['position' => 20, 'is_primary' => true],
        ]);

        $this->get(route('percorsi.show', $cluster->slug))->assertOk();

        $this->assertSame(1, EditorialContinuityEvent::where('event_name', EditorialEventContract::PATH_VIEWED)
            ->where('content_cluster_id', $cluster->id)
            ->count());

        $this->assertSame(2, EditorialContinuityEvent::where('event_name', EditorialEventContract::PATH_LINK_AVAILABLE)
            ->where('content_cluster_id', $cluster->id)
            ->count());

        $this->assertSame(1, EditorialContinuityEvent::where('event_name', EditorialEventContract::PATH_LINK_AVAILABLE)
            ->where('target_article_id', $pillar->id)
            ->where('transition_type', EditorialEventContract::TRANSITION_PILLAR)
            ->count());
    }

    public function test_a_newsletter_subscription_records_an_event_without_any_subscriber_identity(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com'])->assertRedirect();

        $event = EditorialContinuityEvent::where('event_name', EditorialEventContract::NEWSLETTER_SUBSCRIBED)->first();

        $this->assertNotNull($event);

        // Nessuna colonna di editorial_continuity_events fa riferimento
        // all'iscritto: l'unico posto in cui l'email vive è la tabella
        // newsletter, mai qui.
        $this->assertSame(1, Newsletter::where('email', 'reader@example.com')->count());
        $columns = array_keys($event->getAttributes());
        foreach ($columns as $column) {
            $this->assertStringNotContainsString('email', strtolower($column));
        }
    }

    public function test_a_previous_and_next_transition_available_event_is_recorded_when_both_exist(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $previous = $this->publishedArticle(['user_id' => $editor->id, 'title' => 'Prima']);
        $current = $this->publishedArticle(['user_id' => $editor->id, 'title' => 'Centro']);
        $next = $this->publishedArticle(['user_id' => $editor->id, 'title' => 'Ultima']);

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $previous->id => ['position' => 10, 'is_primary' => true],
            $current->id => ['position' => 20, 'is_primary' => true],
            $next->id => ['position' => 30, 'is_primary' => true],
        ]);

        $this->get(route('articolo', $current->slug))->assertOk();

        $this->assertSame(1, EditorialContinuityEvent::where('event_name', EditorialEventContract::TRANSITION_AVAILABLE)
            ->where('transition_type', EditorialEventContract::TRANSITION_PREVIOUS)
            ->where('target_article_id', $previous->id)
            ->count());

        $this->assertSame(1, EditorialContinuityEvent::where('event_name', EditorialEventContract::TRANSITION_AVAILABLE)
            ->where('transition_type', EditorialEventContract::TRANSITION_NEXT)
            ->where('target_article_id', $next->id)
            ->count());
    }

    public function test_the_last_article_of_a_path_records_no_next_transition_available_event(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $previous = $this->publishedArticle(['user_id' => $editor->id]);
        $last = $this->publishedArticle(['user_id' => $editor->id]);

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $previous->id => ['position' => 10, 'is_primary' => true],
            $last->id => ['position' => 20, 'is_primary' => true],
        ]);

        $this->get(route('articolo', $last->slug))->assertOk();

        $this->assertSame(0, EditorialContinuityEvent::where('event_name', EditorialEventContract::TRANSITION_AVAILABLE)
            ->where('transition_type', EditorialEventContract::TRANSITION_NEXT)
            ->count());
    }
}
