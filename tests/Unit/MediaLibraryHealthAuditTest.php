<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Models\User;
use App\Services\ImageService;
use App\Services\MediaLibraryHealthAudit;
use App\Services\MediaReferenceService;
use App\Services\MediaWebpAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * Cantiere 26 (programma 100-cantieri Kairus). MediaLibraryHealthAudit
 * riusa MediaWebpAuditService per "file mancante" e "formato non
 * ottimale" (mai duplicati) e aggiunge testo alternativo, credito/fonte
 * e peso — nessuno dei quali era coperto da alcun audit esistente.
 */
class MediaLibraryHealthAuditTest extends TestCase
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

    private function service(): MediaLibraryHealthAudit
    {
        return new MediaLibraryHealthAudit(new MediaWebpAuditService(new MediaReferenceService, new ImageService));
    }

    private function mediaDir(): string
    {
        return public_path('assets/img');
    }

    private function putFile(string $relativePath, string $ext = 'png'): string
    {
        $path = $this->mediaDir().'/'.$relativePath;
        @mkdir(dirname($path), 0775, true);

        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 80, 200));

        match ($ext) {
            'png' => imagepng($image, $path),
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'webp' => imagewebp($image, $path, 90),
        };
        imagedestroy($image);

        return $path;
    }

    private function media(string $diskName, array $overrides = []): Media
    {
        return Media::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/png',
            'size' => 1000,
        ], $overrides));
    }

    public function test_no_media_produces_an_empty_report(): void
    {
        $result = $this->service()->audit();

        $this->assertSame(0, $result['analyzed']);
        $this->assertSame([], $result['rows']);
    }

    public function test_a_fully_documented_image_has_no_findings(): void
    {
        $this->putFile('foto.webp', 'webp');
        $this->media('foto.webp', [
            'mime_type' => 'image/webp',
            'alt_text' => 'Descrizione della foto',
            'credit' => 'Mario Rossi',
            'source' => 'Archivio personale',
            'size' => 1000,
        ]);

        $result = $this->service()->audit();

        $this->assertSame([], $result['rows'][0]['findings']);
    }

    public function test_missing_alt_text_is_flagged(): void
    {
        $this->putFile('foto.webp', 'webp');
        $this->media('foto.webp', [
            'mime_type' => 'image/webp',
            'credit' => 'Mario Rossi',
            'source' => 'Archivio personale',
        ]);

        $result = $this->service()->audit();

        $this->assertSame(1, $result['missing_alt']);
        $this->assertStringContainsString('alternativo', $result['rows'][0]['findings'][0]);
    }

    public function test_a_credit_without_any_source_is_flagged(): void
    {
        $this->putFile('foto.webp', 'webp');
        $this->media('foto.webp', [
            'mime_type' => 'image/webp',
            'alt_text' => 'Descrizione',
            'credit' => 'Mario Rossi',
        ]);

        $result = $this->service()->audit();

        $this->assertSame(1, $result['missing_credit']);
    }

    public function test_a_source_without_any_credit_is_still_flagged(): void
    {
        $this->putFile('foto.webp', 'webp');
        $this->media('foto.webp', [
            'mime_type' => 'image/webp',
            'alt_text' => 'Descrizione',
            'source' => 'Archivio personale',
        ]);

        $result = $this->service()->audit();

        $this->assertSame(1, $result['missing_credit']);
    }

    public function test_a_media_record_without_a_file_on_disk_is_flagged_as_missing(): void
    {
        $this->media('fantasma.png', ['alt_text' => 'x', 'credit' => 'x', 'source' => 'x']);

        $result = $this->service()->audit();

        $this->assertSame(1, $result['missing_file']);
    }

    public function test_an_oversized_image_is_flagged(): void
    {
        $this->putFile('pesante.webp', 'webp');
        $this->media('pesante.webp', [
            'mime_type' => 'image/webp',
            'alt_text' => 'x',
            'credit' => 'x',
            'source' => 'x',
            'size' => 5_000_000,
        ]);

        $result = $this->service()->audit();

        $this->assertSame(1, $result['oversized']);
    }

    public function test_the_max_size_option_is_respected(): void
    {
        $this->putFile('media.webp', 'webp');
        $this->media('media.webp', [
            'mime_type' => 'image/webp',
            'alt_text' => 'x',
            'credit' => 'x',
            'source' => 'x',
            'size' => 2000,
        ]);

        $this->assertSame(0, $this->service()->audit()['oversized']);
        $this->assertSame(1, $this->service()->audit(maxRecommendedSizeBytes: 1000)['oversized']);
    }

    public function test_a_png_eligible_for_webp_conversion_is_flagged_as_non_optimal_format(): void
    {
        $this->putFile('foto.png', 'png');
        $this->media('foto.png', ['alt_text' => 'x', 'credit' => 'x', 'source' => 'x']);

        $result = $this->service()->audit();

        $this->assertSame(1, $result['non_optimal_format']);
    }

    public function test_only_images_are_analyzed_not_documents(): void
    {
        Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'documento.pdf',
            'disk_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
        ]);

        $result = $this->service()->audit();

        $this->assertSame(0, $result['analyzed']);
    }
}
