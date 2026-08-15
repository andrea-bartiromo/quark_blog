<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contratto visivo minimo della sequenza Percorso (Missione PERCORSI WOW,
 * Pass 1): cover articolo riusata quando presente, fallback elegante
 * quando assente, nodo timeline aperto/tratteggiato per un Percorso
 * updating, nodo chiuso/pieno per uno concluso — mai un leak di
 * informazioni non pubbliche nella metafora grafica.
 */
class PathVisualTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $title, ?string $cover = null): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subHour(),
            'cover_image' => $cover,
        ]);
    }

    public function test_a_step_with_a_cover_renders_the_cover_image(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $article = $this->article('Con copertina', 'copertina-di-prova.jpg');
        $cluster->articles()->attach($article->id, ['position' => 10]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('path-step__cover', false);
        $response->assertSee('copertina-di-prova.jpg', false);
    }

    public function test_a_step_without_a_cover_degrades_gracefully_without_a_broken_image(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $article = $this->article('Senza copertina', null);
        $cluster->articles()->attach($article->id, ['position' => 10]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('path-step__cover', false);
    }

    public function test_an_updating_path_with_published_articles_shows_an_open_dashed_terminal_node(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $cluster->articles()->attach($this->article('Tappa pubblicata')->id, ['position' => 10]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('path-step--next', false);
        $response->assertSee('In arrivo');
        $response->assertDontSee('path-step--close', false);
    }

    public function test_a_complete_path_with_published_articles_shows_a_closed_terminal_node(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'complete']);
        $cluster->articles()->attach($this->article('Tappa pubblicata')->id, ['position' => 10]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('path-step--close', false);
        $response->assertSee('Percorso concluso');
        $response->assertDontSee('path-step--next', false);
    }

    public function test_a_path_with_no_published_articles_shows_neither_terminal_node(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'complete']);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('path-step--next', false);
        $response->assertDontSee('path-step--close', false);
    }

    public function test_the_terminal_node_never_leaks_scheduled_article_information(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $cluster->articles()->attach($this->article('Tappa pubblicata')->id, ['position' => 10]);

        $author = User::factory()->create();
        $scheduled = Article::create([
            'user_id' => $author->id,
            'title' => 'Titolo segreto non ancora pubblico',
            'slug' => 'titolo-segreto-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_SCHEDULED,
            'read_minutes' => 1,
            'published_at' => now()->addWeek(),
        ]);
        $cluster->articles()->attach($scheduled->id, ['position' => 20]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('Titolo segreto non ancora pubblico');
    }
}
