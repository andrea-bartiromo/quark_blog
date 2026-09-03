<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tempo di lettura del percorso ed etichetta di categoria per step:
 * entrambi derivati automaticamente dai dati già esistenti (read_minutes
 * e category degli articoli pubblicati), mai un campo editoriale da
 * gestire a mano — coerente con la logica già usata da PathVisualLibrary
 * per il "cambio di registro".
 */
class PathEditorialAdditionsTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $title, string $category, int $readMinutes, string $status = Article::STATUS_PUBLISHED): Article
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
            'read_minutes' => $readMinutes,
            'published_at' => $status === Article::STATUS_PUBLISHED ? now()->subHour() : now()->addWeek(),
        ]);
    }

    public function test_total_reading_time_sums_only_published_articles(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($this->article('Uno', 'spazio', 3)->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('Due', 'spazio', 5)->id, ['position' => 20]);
        $cluster->articles()->attach($this->article('Bozza', 'spazio', 99, Article::STATUS_DRAFT)->id, ['position' => 30]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('8 min di lettura');
        $response->assertDontSee('99 min di lettura');
        $response->assertDontSee('107 min di lettura');
    }

    public function test_reading_time_is_hidden_when_the_path_has_no_published_articles(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('min di lettura');
    }

    public function test_category_tag_is_absent_on_a_thematically_homogeneous_path(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        foreach (['A', 'B', 'C'] as $i => $title) {
            $cluster->articles()->attach($this->article($title, 'spazio', 3)->id, ['position' => ($i + 1) * 10]);
        }

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        // Cantiere D: la tappa è ora resa da x-kairus.path-step, classe
        // "kairus-path-step__category" invece della legacy
        // "path-step__category" — stesso dato/condizione, solo il
        // namespace del componente.
        $response->assertDontSee('class="kairus-path-step__category"', false);
    }

    public function test_category_tag_appears_on_every_step_once_the_path_shifts_category(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($this->article('Uno', 'spazio', 3)->id, ['position' => 10]);
        $cluster->articles()->attach($this->article('Due', 'energia', 4)->id, ['position' => 20]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('<span class="kairus-path-step__category">Spazio</span>', false);
        $response->assertSee('<span class="kairus-path-step__category">Energia &amp; Clima</span>', false);
    }
}
