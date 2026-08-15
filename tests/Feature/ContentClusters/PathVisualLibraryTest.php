<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Support\PathVisualLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integrazione reale della Kairus Editorial Visual Library (Missione
 * PERCORSI WOW, Pass 2/3). Copre: quante pause (Parte 4), selezione
 * semantica senza hardcode (Parte 9), i casi di stress della Parte 15, e
 * che le cover articolo nella timeline restino intatte (Parte 1 — mai una
 * seconda serie di thumbnail al loro posto).
 */
class PathVisualLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $title, string $category, string $status = Article::STATUS_PUBLISHED): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => $category,
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subHour() : now()->addWeek(),
        ]);
    }

    public function test_break_count_follows_the_article_count_philosophy(): void
    {
        $this->assertSame(0, PathVisualLibrary::breakCountFor(0));
        $this->assertSame(1, PathVisualLibrary::breakCountFor(1));
        $this->assertSame(1, PathVisualLibrary::breakCountFor(2));
        $this->assertSame(1, PathVisualLibrary::breakCountFor(3));
        $this->assertSame(2, PathVisualLibrary::breakCountFor(4));
        $this->assertSame(2, PathVisualLibrary::breakCountFor(11));
    }

    public function test_images_are_selected_from_the_dominant_published_article_category(): void
    {
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($this->article('A', 'spazio')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('B', 'spazio')->id, ['position' => 20]);
        $cluster->articles()->attach($this->article('C', 'energia')->id, ['position' => 30]);

        $images = PathVisualLibrary::imagesFor($cluster->fresh(), 1);

        $this->assertCount(1, $images);
        $this->assertStringStartsWith('kairus-space-', $images[0]);
    }

    public function test_unpublished_articles_never_influence_the_selection(): void
    {
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($this->article('Pubblicato', 'ambiente')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('Bozza', 'energia', Article::STATUS_DRAFT)->id, ['position' => 20]);
        $cluster->articles()->attach($this->article('Programmato', 'salute', Article::STATUS_SCHEDULED)->id, ['position' => 30]);

        $images = PathVisualLibrary::imagesFor($cluster->fresh(), 1);

        $this->assertTrue(
            str_starts_with($images[0], 'kairus-nature-')
            || str_starts_with($images[0], 'kairus-environment-')
            || str_starts_with($images[0], 'kairus-climate-'),
            "L'unica categoria pubblicata (ambiente) deve determinare la selezione: {$images[0]}"
        );
    }

    public function test_two_images_for_the_same_path_are_always_distinct(): void
    {
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($this->article('A', 'intelligenza-artificiale')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('B', 'intelligenza-artificiale')->id, ['position' => 20]);
        $cluster->articles()->attach($this->article('C', 'intelligenza-artificiale')->id, ['position' => 30]);
        $cluster->articles()->attach($this->article('D', 'intelligenza-artificiale')->id, ['position' => 40]);

        $images = PathVisualLibrary::imagesFor($cluster->fresh(), 2);

        $this->assertCount(2, $images);
        $this->assertNotSame($images[0], $images[1]);
    }

    public function test_a_future_path_with_no_articles_still_gets_a_deterministic_image_never_an_exception(): void
    {
        $cluster = ContentCluster::factory()->create([
            'slug' => 'percorso-futuro-senza-articoli-'.bin2hex(random_bytes(4)),
        ]);

        $imagesA = PathVisualLibrary::imagesFor($cluster, 1);
        $imagesB = PathVisualLibrary::imagesFor($cluster->fresh(), 1);

        $this->assertCount(1, $imagesA);
        $this->assertSame($imagesA, $imagesB, 'Stesso Percorso, stessa selezione, sempre.');
    }

    public function test_a_path_without_an_obvious_semantic_match_still_resolves_to_a_valid_image(): void
    {
        // Nessun articolo, categoria indeterminabile: deve comunque
        // ricadere su una categoria valida della libreria, mai una
        // eccezione o un file inesistente.
        $cluster = ContentCluster::factory()->create();

        $images = PathVisualLibrary::imagesFor($cluster, 1);
        $file = public_path('assets/img/percorsi/'.$images[0]);

        $this->assertFileExists($file);
    }

    public function test_the_detail_page_renders_visual_breaks_without_touching_article_covers_in_the_timeline(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $withCover = $this->article('Con copertina', 'spazio');
        $withCover->update(['cover_image' => 'copertina-articolo.jpg']);
        $cluster->articles()->attach($withCover->id, ['position' => 10, 'is_primary' => true]);
        $cluster->update(['pillar_article_id' => $withCover->id]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        // La cover dell'articolo nella timeline resta quella reale.
        $response->assertSee('path-step__cover', false);
        $response->assertSee('copertina-articolo.jpg', false);
        // La Visual Library appare come pausa narrativa distinta.
        $response->assertSee('path-visual-break', false);
        $response->assertSee('assets/img/percorsi/kairus-space-', false);
    }

    public function test_visual_break_markup_is_decorative_and_never_leaks_unpublished_titles(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $cluster->articles()->attach($this->article('Tappa pubblica', 'salute')->id, ['position' => 10]);
        $this->article('Titolo non ancora pubblico', 'salute', Article::STATUS_SCHEDULED);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('alt=""', false);
        $response->assertDontSee('Titolo non ancora pubblico');
    }

    public function test_a_complete_path_gets_exactly_one_break_for_three_articles(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'complete']);
        foreach (['A', 'B', 'C'] as $i => $title) {
            $cluster->articles()->attach($this->article($title, 'ambiente')->id, ['position' => ($i + 1) * 10]);
        }

        $response = $this->get(route('percorsi.show', $cluster->slug));
        $count = substr_count($response->getContent(), 'path-visual-break');

        $response->assertOk();
        $this->assertSame(1, $count);
    }

    public function test_a_path_with_no_published_articles_shows_no_visual_break(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'complete']);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('path-visual-break', false);
    }
}
