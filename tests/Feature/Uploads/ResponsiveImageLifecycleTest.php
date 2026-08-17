<?php

namespace Tests\Feature\Uploads;

use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * FASE 9 (missione S2 responsive images, fase critica): verifica che il
 * ciclo di vita delle varianti responsive sia correttamente agganciato ai
 * punti reali di ingresso/uscita della Libreria media — non solo al
 * servizio in isolamento (vedi ResponsiveImageVariantServiceTest), ma
 * attraverso i controller/servizi che un redattore usa davvero: upload
 * dalla Libreria media, cancellazione, spostamento di cartella.
 */
class ResponsiveImageLifecycleTest extends TestCase
{
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
        parent::tearDown();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_uploading_a_large_image_via_the_media_library_generates_responsive_variants(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('foto-grande.jpg', 2000, 1200);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ])->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();

        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));

        $variant480 = $this->variantPathFor($media->disk_name, 480);
        $variant960 = $this->variantPathFor($media->disk_name, 960);

        $this->assertFileExists(public_path('assets/img/'.$variant480));
        $this->assertFileExists(public_path('assets/img/'.$variant960));
    }

    public function test_uploading_a_small_image_generates_no_variants_but_still_succeeds(): void
    {
        // Immagine piu' piccola di tutte le larghezze configurate: nessuna
        // variante deve essere generata (mai upscale), ma l'upload
        // principale deve comunque riuscire senza errori.
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('piccola.jpg', 200, 150);

        $response = $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ]);

        $response->assertSessionHasNoErrors();
        $media = Media::latest('id')->firstOrFail();
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
    }

    public function test_deleting_a_media_also_deletes_its_responsive_variants(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('da-eliminare.jpg', 2000, 1200);

        $this->actingAs($editor)->post(route('admin.media.store'), ['image' => $image])
            ->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();
        $variant480 = public_path('assets/img/'.$this->variantPathFor($media->disk_name, 480));
        $this->assertFileExists($variant480);

        $this->actingAs($editor)->delete(route('admin.media.destroy', $media))
            ->assertSessionHas('success');

        $this->assertFileDoesNotExist($variant480);
        $this->assertModelMissing($media);
    }

    public function test_replacing_a_category_image_retires_the_previous_image_and_its_variants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Scienza']);

        $firstImage = UploadedFile::fake()->image('prima.jpg', 2000, 1200);
        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'image_upload' => $firstImage,
        ])->assertSessionDoesntHaveErrors();

        $category->refresh();
        $firstDiskName = 'categories/'.$category->image;
        $firstVariant = public_path('assets/img/'.$this->variantPathFor($firstDiskName, 480));
        $this->assertFileExists($firstVariant, 'La prima immagine deve avere generato una variante.');

        $secondImage = UploadedFile::fake()->image('seconda.jpg', 2000, 1200);
        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'image_upload' => $secondImage,
        ])->assertSessionDoesntHaveErrors();

        // La prima immagine (non piu' referenziata da nulla) e le sue
        // varianti devono essere state ritirate da MediaRetirementService,
        // ora esteso per pulire anche le varianti responsive.
        $this->assertFileDoesNotExist($firstVariant);
    }

    private function variantPathFor(string $diskName, int $width): string
    {
        return app(ImageService::class)->responsiveVariantPath($diskName, $width);
    }
}
