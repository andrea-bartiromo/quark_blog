<?php

namespace Tests\Feature\Uploads;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * S10 — test di INTEGRAZIONE per Admin\MediaController::store(): copre il
 * comportamento COMBINATO introdotto separatamente da #215 (generazione
 * varianti responsive, generateForUpload()) e #224 (pulizia del file
 * originale se Media::create() fallisce, S9). Nessuno dei due test originali
 * copriva la combinazione: il test di #224 usa immagini troppo piccole per
 * generare varianti (< 480px, sotto la soglia minima), quindi non avrebbe
 * mai potuto rilevare che la pulizia esistente non conosceva affatto i file
 * variante introdotti da #215 — un fallimento di Media::create() dopo che le
 * varianti erano gia' state scritte le lasciava orfane sul filesystem.
 */
class AdminMediaResponsiveVariantsAndFailureCleanupTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
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

    private function variantPaths(string $diskName): array
    {
        $dir = dirname($diskName);
        $base = pathinfo($diskName, PATHINFO_FILENAME);
        $ext = pathinfo($diskName, PATHINFO_EXTENSION);
        $prefix = ($dir === '.' || $dir === '') ? '' : $dir.'/';

        return [
            public_path('assets/img/'.$prefix.$base.'-480w.'.$ext),
            public_path('assets/img/'.$prefix.$base.'-960w.'.$ext),
        ];
    }

    // Comportamento base combinato: un upload valido, abbastanza grande da
    // superare entrambe le soglie configurate (480/960), deve produrre sia il
    // Media sia entrambe le varianti responsive.
    public function test_a_valid_large_upload_produces_the_media_record_and_both_responsive_variants(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('grande.jpg', 2000, 1200);

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
        ])->assertSessionHasNoErrors();

        $media = Media::latest('id')->firstOrFail();
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));

        foreach ($this->variantPaths($media->disk_name) as $variantPath) {
            $this->assertFileExists($variantPath, 'La variante responsive attesa non e\' stata generata per un upload valido.');
        }
    }

    // Invariante centrale della combinazione #215+#224: se Media::create()
    // fallisce DOPO che il file originale e le varianti responsive sono gia'
    // stati scritti e pubblicati, nessuno dei due deve sopravvivere come
    // orfano — non solo il file originale (gia' garantito da #224 da solo),
    // ma anche ciascuna variante (comportamento che serviva la reconciliation
    // S10, assente in entrambe le PR isolatamente).
    public function test_media_creation_failure_removes_the_original_and_every_generated_variant(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce-grande.jpg', 2000, 1200);

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

        // Nessun file immagine di alcun tipo (originale o variante) deve
        // sopravvivere nella directory di upload: senza la pulizia delle
        // varianti, foto-480w.webp e foto-960w.webp resterebbero live e
        // pubblicamente raggiungibili senza alcuna riga Media a governarle.
        $this->assertSame([], glob(public_path('assets/img/*.webp')) ?: []);
        $this->assertSame([], glob(public_path('assets/img/_da-classificare/*.webp')) ?: []);
    }

    // Invariante di non-distruttivita': la pulizia innescata da un upload
    // fallito non deve mai toccare un asset precedente e valido — incluse le
    // sue varianti responsive gia' generate da un upload riuscito in
    // precedenza.
    public function test_media_creation_failure_never_touches_a_pre_existing_media_or_its_variants(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => UploadedFile::fake()->image('esistente-grande.jpg', 2000, 1200),
        ])->assertSessionHasNoErrors();

        $existingMedia = Media::latest('id')->firstOrFail();
        $existingVariantPaths = $this->variantPaths($existingMedia->disk_name);
        foreach ($existingVariantPaths as $variantPath) {
            $this->assertFileExists($variantPath);
        }

        Media::creating(function () {
            throw new RuntimeException('guasto simulato');
        });

        try {
            $this->actingAs($editor)->post(route('admin.media.store'), [
                'image' => UploadedFile::fake()->image('nuovo-fallito-grande.jpg', 2000, 1200),
            ]);
        } catch (RuntimeException $exception) {
            $this->assertSame('guasto simulato', $exception->getMessage());
        }

        $this->assertSame(1, Media::count(), 'Il Media preesistente deve essere l\'unico rimasto: il secondo upload fallito non deve aver lasciato alcuna riga.');
        $this->assertNotNull(Media::find($existingMedia->id));
        $this->assertFileExists(public_path('assets/img/'.$existingMedia->disk_name));

        foreach ($existingVariantPaths as $variantPath) {
            $this->assertFileExists($variantPath, 'La pulizia del secondo upload fallito ha erroneamente cancellato una variante di un Media preesistente e valido.');
        }
    }
}
