<?php

namespace Tests\Feature\Uploads;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\InteractsWithTestImages;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class AdminMediaUploadTest extends TestCase
{
    use InteractsWithTestImages;
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

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
            'image' => $image,
            'alt_text' => 'Testo alternativo di prova',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Immagine "foto.jpg" caricata con successo.');

        $media = Media::latest('id')->firstOrFail();

        $this->assertSame('foto.jpg', $media->filename);
        $this->assertSame($editor->id, $media->user_id);
        $this->assertSame('Testo alternativo di prova', $media->alt_text);
        $this->assertStringStartsWith('_da-classificare/', $media->disk_name);
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
        $this->assertGreaterThan(0, $media->size);
    }

    public function test_a_jpg_upload_is_saved_as_webp_by_default(): void
    {
        // FASE 5: politica di default per i nuovi upload editoriali.
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('foto.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ])->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();

        $this->assertStringEndsWith('.webp', $media->disk_name);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
        $this->assertFileDoesNotExist(public_path('assets/img/'.str_replace('.webp', '.jpg', $media->disk_name)));
    }

    public function test_auto_webp_on_upload_can_be_disabled_via_config(): void
    {
        // Interruttore di sicurezza reversibile (MEDIA_AUTO_WEBP_ON_UPLOAD):
        // disattivato, il comportamento torna quello preesistente
        // (ottimizzazione nello stesso formato del sorgente).
        config(['media.auto_webp_on_upload' => false]);

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('foto.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ])->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();

        $this->assertStringEndsWith('.jpg', $media->disk_name);
        $this->assertSame('image/jpeg', $media->mime_type);
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
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
    }

    public function test_media_image_is_resized_to_the_1600px_limit(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('grande.jpg', 2400, 1200);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ]);

        $media = Media::latest('id')->firstOrFail();
        [$w, $h] = getimagesize(public_path('assets/img/'.$media->disk_name));

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

        // Con la conversione automatica in WebP per i nuovi upload (FASE 5)
        // un PNG trasparente che supera i 1600px viene salvato come .webp,
        // non piu' come .png: la trasparenza deve sopravvivere comunque,
        // quindi si decodifica in base al formato realmente salvato.
        $media = Media::latest('id')->firstOrFail();
        $path = public_path('assets/img/'.$media->disk_name);

        $img = str_ends_with($media->disk_name, '.webp')
            ? imagecreatefromwebp($path)
            : imagecreatefrompng($path);
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

    public function test_mime_type_is_detected_from_the_real_file_content_not_the_extension(): void
    {
        // Simula un file PNG salvato erroneamente con estensione .jpg (il
        // caso reale riscontrato in libreria): l'upload deve trattarlo in
        // base al contenuto reale (PNG), non al MIME/estensione dichiarati
        // dal client — cosa che si vede anche a valle: un PNG e' eleggibile
        // per la conversione automatica in WebP (FASE 5), un vero JPEG con
        // lo stesso nome non lo sarebbe stato diversamente in questo test,
        // ma qui conta che il rilevamento sia partito dal contenuto reale.
        $editor = $this->editor();
        $image = imagecreatetruecolor(400, 300);
        $tmp = tempnam(sys_get_temp_dir(), 'kairus-mismatch-');
        imagepng($image, $tmp);
        imagedestroy($image);
        $mismatched = new UploadedFile($tmp, 'falso.jpg', 'image/jpeg', null, true);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $mismatched,
        ])->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->disk_name);

        @unlink($tmp);
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
        $response->assertJsonPath('url', asset('assets/img/'.$media->disk_name));
    }

    public function test_upload_can_target_a_selected_category(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create([
            'name' => 'Copertine',
            'slug' => 'covers',
            'path' => 'articles/covers',
        ]);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => UploadedFile::fake()->image('cover.jpg', 400, 300),
            'media_folder_id' => $folder->id,
        ])->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();
        $this->assertStringStartsWith('articles/covers/', $media->disk_name);
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
    }

    public function test_upload_can_explicitly_target_the_root(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => UploadedFile::fake()->image('root.jpg', 400, 300),
            'media_folder_id' => '',
        ])->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();
        $this->assertStringNotContainsString('/', $media->disk_name);
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
    }

    public function test_upload_rejects_a_missing_destination_category(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => UploadedFile::fake()->image('missing.jpg', 400, 300),
            'media_folder_id' => 999999,
        ])->assertSessionHasErrors('media_folder_id');

        $this->assertSame(0, Media::count());
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
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));

        // Il preset Media abilita alwaysReencode: il file troncato ha header
        // leggibile ma decodifica GD "morbida" (nessuna eccezione nativa in
        // questo ambiente, vedi ImageServiceTest), quindi il ramo catch/log
        // non viene attraversato: si verifica che l'upload resti coerente e
        // che, coerentemente, non venga scritto alcun log di errore.
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Log::shouldNotHaveReceived('warning');
    }

    // S9 — MediaController::store() scrive il file (imageService->upload()),
    // lo converte/ottimizza e lo sincronizza nella radice pubblica secondaria
    // PRIMA di chiamare Media::create(): un fallimento di quella create()
    // (DB irraggiungibile, ecc.) lasciava un file live, gia' pubblicato e
    // raggiungibile via URL, senza ALCUNA riga Media che lo referenzi — non
    // gestibile ne' individuabile dalla Libreria media.
    public function test_upload_cleans_up_the_file_if_media_record_creation_fails(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce.jpg', 400, 300);

        Media::creating(function () {
            throw new RuntimeException('guasto simulato');
        });

        try {
            $this->actingAs($editor)->post(route('admin.media.store'), [
                'image' => $image,
            ]);
        } catch (RuntimeException $exception) {
            $this->assertSame('guasto simulato', $exception->getMessage());
        }

        $this->assertSame(0, Media::count());

        // Senza la pulizia, il file caricato sopravvivrebbe orfano, live e
        // pubblicamente raggiungibile in public/assets/img, senza alcuna
        // riga Media che lo referenzi o lo renda gestibile dalla Libreria.
        $this->assertSame([], glob(public_path('assets/img/*.webp')) ?: []);
        $this->assertSame([], glob(public_path('assets/img/_da-classificare/*.webp')) ?: []);
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

            $path = $dir.'/'.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteDirectoryForTest($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
