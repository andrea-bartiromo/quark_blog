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
 * KAIRUS PATH VISUAL LANGUAGE). Copre i due asset SEMANTICI —
 * atmosphereImage() sempre presente, transitionImage() solo quando la
 * sequenza pubblicata attraversa davvero più di una categoria — mai un
 * conteggio derivato dal numero di articoli, e che le cover articolo
 * nella timeline restino intatte (mai una seconda serie di thumbnail al
 * loro posto).
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

    public function test_the_atmosphere_image_is_selected_from_the_dominant_published_article_category(): void
    {
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($this->article('A', 'spazio')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('B', 'spazio')->id, ['position' => 20]);
        $cluster->articles()->attach($this->article('C', 'energia')->id, ['position' => 30]);

        $image = PathVisualLibrary::atmosphereImage($cluster->fresh());

        $this->assertStringStartsWith('kairus-space-', $image);
    }

    public function test_unpublished_articles_never_influence_the_atmosphere_selection(): void
    {
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($this->article('Pubblicato', 'ambiente')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('Bozza', 'energia', Article::STATUS_DRAFT)->id, ['position' => 20]);
        $cluster->articles()->attach($this->article('Programmato', 'salute', Article::STATUS_SCHEDULED)->id, ['position' => 30]);

        $image = PathVisualLibrary::atmosphereImage($cluster->fresh());

        $this->assertTrue(
            str_starts_with($image, 'kairus-nature-')
            || str_starts_with($image, 'kairus-environment-')
            || str_starts_with($image, 'kairus-climate-'),
            "L'unica categoria pubblicata (ambiente) deve determinare la selezione: {$image}"
        );
    }

    public function test_a_future_path_with_no_articles_still_gets_a_deterministic_atmosphere_never_an_exception(): void
    {
        $cluster = ContentCluster::factory()->create([
            'slug' => 'percorso-futuro-senza-articoli-'.bin2hex(random_bytes(4)),
        ]);

        $imageA = PathVisualLibrary::atmosphereImage($cluster);
        $imageB = PathVisualLibrary::atmosphereImage($cluster->fresh());

        $this->assertSame($imageA, $imageB, 'Stesso Percorso, stessa selezione, sempre.');
    }

    public function test_a_path_without_an_obvious_semantic_match_still_resolves_the_atmosphere_to_a_valid_image(): void
    {
        // Nessun articolo, categoria indeterminabile: deve comunque
        // ricadere su una categoria valida della libreria, mai una
        // eccezione o un file inesistente.
        $cluster = ContentCluster::factory()->create();

        $image = PathVisualLibrary::atmosphereImage($cluster);
        $file = public_path('assets/img/percorsi/'.$image);

        $this->assertFileExists($file);
    }

    public function test_a_thematically_homogeneous_path_has_no_transition_image(): void
    {
        // Il caso comune: ogni Percorso reale corrisponde già a una
        // categoria. Nessuno scarto da segnalare, nessuna immagine.
        $cluster = ContentCluster::factory()->create();
        foreach (['A', 'B', 'C'] as $i => $title) {
            $cluster->articles()->attach($this->article($title, 'ambiente')->id, ['position' => ($i + 1) * 10]);
        }

        $this->assertNull(PathVisualLibrary::transitionImage($cluster->fresh()));
    }

    public function test_a_path_that_shifts_category_mid_sequence_gets_a_transition_image_from_the_shift(): void
    {
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($this->article('A', 'spazio')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('B', 'spazio')->id, ['position' => 20]);
        $cluster->articles()->attach($this->article('C', 'energia')->id, ['position' => 30]);

        $transition = PathVisualLibrary::transitionImage($cluster->fresh());

        $this->assertNotNull($transition);
        $this->assertStringStartsWith('kairus-energy-', $transition);
    }

    public function test_the_transition_image_is_never_the_same_role_as_the_atmosphere_image(): void
    {
        $cluster = ContentCluster::factory()->create();
        $cluster->articles()->attach($this->article('A', 'spazio')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('B', 'energia')->id, ['position' => 20]);
        $cluster = $cluster->fresh();

        $atmosphere = PathVisualLibrary::atmosphereImage($cluster);
        $transition = PathVisualLibrary::transitionImage($cluster);

        $this->assertNotSame($atmosphere, $transition);
    }

    public function test_a_path_with_no_published_articles_has_no_transition_image(): void
    {
        $cluster = ContentCluster::factory()->create();

        $this->assertNull(PathVisualLibrary::transitionImage($cluster));
    }

    public function test_the_detail_page_renders_the_atmosphere_without_touching_article_covers_in_the_timeline(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $withCover = $this->article('Con copertina', 'spazio');
        $withCover->update(['cover_image' => 'copertina-articolo.jpg']);
        $cluster->articles()->attach($withCover->id, ['position' => 10, 'is_primary' => true]);
        $cluster->update(['pillar_article_id' => $withCover->id]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        // La cover dell'articolo nella timeline resta quella reale.
        // Cantiere D: la tappa è ora resa da x-kairus.path-step, classe
        // "kairus-path-step__media" invece della legacy "path-step__cover"
        // — stesso elemento, stesso src.
        $response->assertSee('kairus-path-step__media', false);
        $response->assertSee('copertina-articolo.jpg', false);
        // L'ingresso atmosferico è lo sfondo cinematografico dell'hero
        // stesso, mai una seconda composizione più in basso.
        $response->assertSee('class="path-hero"', false);
        $response->assertSee('assets/img/percorsi/kairus-space-', false);
    }

    public function test_the_hero_atmosphere_markup_is_decorative_and_never_leaks_unpublished_titles(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $published = $this->article('Tappa pubblica', 'salute');
        $published->update(['cover_image' => 'copertina-tappa.jpg']);
        $cluster->articles()->attach($published->id, ['position' => 10]);
        $this->article('Titolo non ancora pubblico', 'salute', Article::STATUS_SCHEDULED);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        // Lo sfondo atmosferico dell'hero è puramente decorativo: lo
        // scrim che ne garantisce la leggibilità è marcato aria-hidden.
        $response->assertSee('path-hero__scrim" aria-hidden="true"', false);
        // Le cover reali degli articoli nella timeline restano immagini
        // decorative (alt vuoto), il testo del link porta già il titolo.
        $response->assertSee('alt=""', false);
        $response->assertDontSee('Titolo non ancora pubblico');
    }

    public function test_a_homogeneous_path_shows_no_transition_section_on_the_detail_page(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'complete']);
        foreach (['A', 'B', 'C'] as $i => $title) {
            $cluster->articles()->attach($this->article($title, 'ambiente')->id, ['position' => ($i + 1) * 10]);
        }

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('path-transition', false);
    }

    public function test_a_shifting_path_shows_exactly_one_transition_section_on_the_detail_page(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'updating']);
        $cluster->articles()->attach($this->article('A', 'spazio')->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('B', 'energia')->id, ['position' => 20]);

        $response = $this->get(route('percorsi.show', $cluster->slug));
        $count = substr_count($response->getContent(), 'class="path-transition"');

        $response->assertOk();
        $this->assertSame(1, $count);
    }

    public function test_a_path_with_no_published_articles_shows_no_transition_section(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => 'complete']);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('path-transition', false);
        // L'ingresso atmosferico resta comunque presente nell'hero: è
        // sempre l'apertura della mappa editoriale, anche senza articoli.
        $response->assertSee('class="path-hero"', false);
    }
}
