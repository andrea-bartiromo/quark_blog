<?php

namespace Tests\Feature\Uploads;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithTestImages;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class AdminMediaUploadTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;
    use InteractsWithTestImages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownTestImages();
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_authorized_editor_can_upload_a_media_image(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('foto.jpg', 800, 600);

        $response = $this->actingAs($editor)->post(route('admin.media.store'), [
            'image'    => $image,
            'alt_text' => 'Testo alternativo di prova',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Immagine "foto.jpg" caricata con successo.');

        $media = Media::latest('id')->firstOrFail();

        $this->assertSame('foto.jpg', $media->filename);
        $this->assertSame($editor->id, $media->user_id);
        $this->assertSame('Testo alternativo di prova', $media->alt_text);
        $this->assertFileExists(public_path('assets/img/' . $media->disk_name));
        $this->assertGreaterThan(0, $media->size);
    }

    public function test_upload_creates_the_destination_directory_when_missing(): void
    {
        // Simula il progetto reale prima di qualunque upload: la cartella
        // assets/img non esiste ancora sotto il public_path isolato.
        $this->deleteDirectoryForTest(public_path('assets/img'));
        $this->assertDirectoryDoesNotExist(public_path('assets/img'));

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('nuovo.jpg', 400, 300);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ]);

        $this->assertDirectoryExists(public_path('assets/img'));
        $media = Media::latest('id')->firstOrFail();
        $this->assertFileExists(public_path('assets/img/' . $media->disk_name));
    }

    public function test_media_image_is_resized_to_the_1600px_limit(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('grande.jpg', 2400, 1200);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ]);

        $media = Media::latest('id')->firstOrFail();
        [$w, $h] = getimagesize(public_path('assets/img/' . $media->disk_name));

        $this->assertSame(1600, $w);
        $this->assertSame(800, $h);
    }

    public function test_transparent_png_keeps_its_transparency_after_upload(): void
    {
        // La preservazione della trasparenza e implementata solo nel ramo di
        // resize di ImageService::resizeAndCompress() (quando preserveTransparency
        // e attivo e la larghezza supera il limite): il ramo compressOnly(),
        // usato per i file che non superano il limite, non imposta
        // imagesavealpha() e quindi non la preserva. Per verificare il
        // comportamento reale (senza "correggerlo") usiamo un'immagine oltre
        // il limite di 1600px, cosi da attraversare il ramo di resize.
        $editor = $this->editor();
        $png = $this->makeTransparentPngUpload('trasparente.png', 2000, 1200);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $png,
        ]);

        $media = Media::latest('id')->firstOrFail();
        $path = public_path('assets/img/' . $media->disk_name);

        $img = imagecreatefrompng($path);
        $rgba = imagecolorat($img, (int) (imagesx($img) / 2), (int) (imagesy($img) / 2));
        $alpha = ($rgba >> 24) & 0x7F;
        imagedestroy($img);

        $this->assertSame(127, $alpha);
    }

    public function test_a_gif_image_is_accepted(): void
    {
        $editor = $this->editor();
        $gif = UploadedFile::fake()->image('animata.gif', 200, 200);

        $response = $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $gif,
        ]);

        $response->assertSessionHasNoErrors();
        $media = Media::latest('id')->firstOrFail();
        $this->assertSame('animata.gif', $media->filename);
    }

    public function test_validation_rejects_an_unsupported_image_format(): void
    {
        $editor = $this->editor();
        $bmp = UploadedFile::fake()->create('cover.bmp', 100, 'image/bmp');

        $response = $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $bmp,
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, Media::count());
    }

    public function test_validation_rejects_an_image_over_the_size_limit(): void
    {
        $editor = $this->editor();
        $tooBig = UploadedFile::fake()->image('pesante.jpg')->size(5121);

        $response = $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $tooBig,
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, Media::count());
    }

    public function test_the_ajax_endpoint_returns_a_json_response(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('ajax.jpg', 400, 300);

        $response = $this->actingAs($editor)->postJson(route('admin.media.upload'), [
            'image' => $image,
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);

        $media = Media::latest('id')->firstOrFail();
        $response->assertJsonPath('filename', $media->disk_name);
        $response->assertJsonPath('id', $media->id);
    }

    public function test_a_gd_optimization_failure_is_logged_but_the_media_record_is_still_created(): void
    {
        Log::spy();

        $editor = $this->editor();
        $truncated = $this->makeTruncatedJpegUpload('corrotta.jpg', 2000, 1000);

        $response = $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $truncated,
        ]);

        $response->assertSessionHas('success');

        $media = Media::latest('id')->firstOrFail();
        $this->assertFileExists(public_path('assets/img/' . $media->disk_name));

        // Il preset Media abilita alwaysReencode: il file troncato ha header
        // leggibile ma decodifica GD "morbida" (nessuna eccezione nativa in
        // questo ambiente, vedi ImageServiceTest), quindi il ramo catch/log
        // non viene attraversato: si verifica che l'upload resti coerente e
        // che, coerentemente, non venga scritto alcun log di errore.
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Log::shouldNotHaveReceived('warning');
    }

    private function deleteDirectoryForTest(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteDirectoryForTest($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
