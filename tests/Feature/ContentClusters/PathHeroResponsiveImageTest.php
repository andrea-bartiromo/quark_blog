<?php

namespace Tests\Feature\ContentClusters;

use App\Models\ContentCluster;
use App\Services\ResponsiveImageVariantService;
use App\Support\PathVisualLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTestImages;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * S2-C (responsive images, missione notturna): copre la conversione
 * dell'hero /percorsi/{slug} da CSS background-image a
 * <x-responsive-image>, stessa grammatica gia' in uso da
 * articles/partials/hero.blade.php (loading="eager", fetchpriority="high",
 * assolutamente posizionato sotto scrim/copy — vedi
 * content-clusters-detail.css). Copre sia il percorso "cover reale con
 * varianti generate" sia il fallback "nessuna cover -> atmosfera della
 * Visual Library" (sempre risolvibile, mai un errore).
 */
class PathHeroResponsiveImageTest extends TestCase
{
    use InteractsWithTestImages;
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        config(['media.responsive_widths' => [480, 960]]);
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        $this->tearDownTestImages();
        parent::tearDown();
    }

    private function placeCoverWithVariantsAt(string $diskName, int $width, int $height): void
    {
        $file = $this->makeSolidImageUpload(basename($diskName), $width, $height);
        $target = public_path('assets/img/'.$diskName);
        @mkdir(dirname($target), 0775, true);
        rename($file->getPathname(), $target);

        app(ResponsiveImageVariantService::class)->generateForUpload($target, $diskName);
    }

    public function test_hero_has_srcset_and_coherent_sizes_when_the_cluster_has_a_cover_with_variants(): void
    {
        $this->placeCoverWithVariantsAt('percorsi-covers/cluster-hero.jpg', 2000, 1200);

        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso con copertina',
            'cover_image' => 'percorsi-covers/cluster-hero.jpg',
            'is_active' => true,
        ]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/percorsi-covers/cluster-hero.jpg').'"', false);
        $response->assertSee('srcset="'.asset('assets/img/percorsi-covers/cluster-hero-480w.jpg').' 480w, '
            .asset('assets/img/percorsi-covers/cluster-hero-960w.jpg').' 960w, '
            .asset('assets/img/percorsi-covers/cluster-hero.jpg').' 2000w"', false);
        $response->assertSee('sizes="(max-width: 900px) 100vw, 1200px"', false);
        $response->assertSee('alt="Cover del percorso Percorso con copertina"', false);
        $response->assertSee('loading="eager"', false);
        $response->assertSee('fetchpriority="high"', false);
        $response->assertDontSee('background-image:', false);
    }

    public function test_hero_falls_back_to_the_visual_library_atmosphere_when_the_cluster_has_no_cover(): void
    {
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso senza copertina',
            'cover_image' => null,
            'is_active' => true,
        ]);

        $atmosphere = PathVisualLibrary::atmosphereImage($cluster->fresh());
        $expectedUrl = PathVisualLibrary::url($atmosphere);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('src="'.$expectedUrl.'"', false);
        $response->assertSee('alt="Cover del percorso Percorso senza copertina"', false);
        $response->assertSee('loading="eager"', false);
        $response->assertSee('fetchpriority="high"', false);
        $response->assertDontSee('background-image:', false);
    }

    public function test_hero_falls_back_gracefully_when_the_cover_disk_name_has_no_file_on_disk(): void
    {
        // Stesso principio del fallback legacy gia' testato altrove
        // (ResponsiveImageVariantServiceTest): un cover_image salvato ma il
        // cui file non esiste (ancora) sul filesystem isolato di questo
        // test non deve mai produrre un errore, solo l'URL nudo senza
        // srcset.
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso con copertina mancante',
            'cover_image' => 'percorsi-covers/non-esiste.jpg',
            'is_active' => true,
        ]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/percorsi-covers/non-esiste.jpg').'"', false);
        $response->assertDontSee('srcset=', false);
    }

    public function test_hero_og_image_and_lightbox_are_unaffected_by_the_hero_markup_change(): void
    {
        $this->placeCoverWithVariantsAt('percorsi-covers/og-check.jpg', 1600, 900);

        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso OG',
            'cover_image' => 'percorsi-covers/og-check.jpg',
            'is_active' => true,
        ]);
        $expected = asset('assets/img/percorsi-covers/og-check.jpg');

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('property="og:image" content="'.$expected.'"', false);
        $response->assertSee('name="twitter:image" content="'.$expected.'"', false);
        $response->assertSee('data-media-viewer-target="path-cover-viewer-'.$cluster->id.'"', false);
        $response->assertSee('data-media-viewer-image', false);
    }
}
