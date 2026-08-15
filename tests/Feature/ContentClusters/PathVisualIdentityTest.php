<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Support\PathVisualSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Firma visiva automatica (Missione PERCORSI WOW, Pass 2). Il test
 * obbligatorio della Parte 8: un Percorso completamente nuovo, mai visto
 * da questo codice, con dati minimi — deve ottenere automaticamente una
 * firma coerente, senza alcuno slug hardcoded nel sistema.
 */
class PathVisualIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_path_with_minimal_data_gets_an_automatic_signature_on_index_and_detail(): void
    {
        // Slug generato a runtime, mai scritto da nessuna parte nel
        // codice applicativo: se il sistema richiedesse una
        // configurazione per singolo Percorso, questo test fallirebbe.
        $slug = 'percorso-futuro-'.bin2hex(random_bytes(4));

        $cluster = ContentCluster::factory()->create([
            'slug' => $slug,
            'name' => 'Percorso Futuro Di Prova',
            'is_active' => true,
            'lifecycle_status' => 'complete',
            'cover_image' => null,
            'short_description' => null,
            'description' => null,
        ]);

        $expectedClass = PathVisualSignature::cssClass($cluster);
        $this->assertMatchesRegularExpression('/^path-signature-[0-5]$/', $expectedClass);

        $indexResponse = $this->get(route('percorsi.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee($expectedClass, false);

        $detailResponse = $this->get(route('percorsi.show', $slug));
        $detailResponse->assertOk();
        $detailResponse->assertSee($expectedClass, false);
        // Fallback elegante: nessuna cover, ma nessuna immagine rotta.
        $detailResponse->assertDontSee('<img src="" ', false);
    }

    public function test_the_signature_is_stable_across_requests_for_the_same_path(): void
    {
        $cluster = ContentCluster::factory()->create(['slug' => 'percorso-stabile-nel-tempo']);

        $first = PathVisualSignature::cssClass($cluster);
        $second = PathVisualSignature::cssClass($cluster->fresh());

        $this->assertSame($first, $second);
    }

    public function test_different_paths_can_get_different_signatures(): void
    {
        $signatures = collect(range(1, 20))
            ->map(fn (int $i) => ContentCluster::factory()->make(['slug' => 'percorso-varieta-'.$i]))
            ->map(fn (ContentCluster $cluster) => PathVisualSignature::presetIndex($cluster))
            ->unique();

        // Con 20 slug diversi e 6 preset, ci si aspetta più di un preset
        // rappresentato — dimostra che la firma non collassa sempre sullo
        // stesso valore (non è una costante travestita da hash).
        $this->assertGreaterThan(1, $signatures->count());
    }

    public function test_homepage_discovery_cards_carry_the_same_signature_as_the_path(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $author = User::factory()->create();
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato',
            'slug' => 'articolo-pubblicato-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subHour(),
        ]);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true]);
        $cluster->update(['pillar_article_id' => $article->id]);

        $expectedClass = PathVisualSignature::cssClass($cluster);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee($expectedClass, false);
    }
}
