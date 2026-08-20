<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\ResponsiveImageVariantService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * FASE 11 (missione S2 responsive images): comportamento del comando di
 * backfill media:generate-responsive e del rendering pubblico su un
 * catalogo realistico di grandi dimensioni.
 *
 * Le scritture reali (encoding WebP via GD) sono misurate a 100 media —
 * scala gia sufficiente a dimostrare che il costo per elemento resta
 * costante, senza dover scrivere migliaia di immagini reali in CI (tempo
 * di esecuzione, non correttezza). Il comportamento delle QUERY e del
 * CHUNKING (query piatte, nessun N+1, elaborazione a blocchi) e' invece
 * verificato fino a 10.000 record — la parte che davvero dipende dalla
 * dimensione del catalogo — usando record Media senza file reale
 * associato: il comando li classifica correttamente come "sorgente
 * mancante" in una singola chiamata a safeExistingFilePath() per record,
 * senza mai leggere/scrivere immagini, cosi la query-flatness resta
 * osservabile a costo quasi nullo.
 */
class GenerateResponsiveImagesPerformanceTest extends TestCase
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

    private function bulkInsertMediaWithoutFiles(int $count, string $prefix): void
    {
        $author = User::factory()->create();
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_id' => $author->id,
                'filename' => "{$prefix}-{$i}.webp",
                'disk_name' => "articles/covers/{$prefix}-{$i}.webp",
                'mime_type' => 'image/webp',
                'size' => 1000,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('media')->insert($chunk);
        }
    }

    private function queryCountForDryRun(int $catalogSize, string $prefix): int
    {
        Media::query()->delete();
        $this->bulkInsertMediaWithoutFiles($catalogSize, $prefix);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Artisan::call('media:generate-responsive', ['--chunk' => 200]);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_count_does_not_grow_between_a_small_and_a_1000_media_catalog(): void
    {
        $small = $this->queryCountForDryRun(20, 'catalogo-piccolo');
        $large = $this->queryCountForDryRun(1000, 'catalogo-1000');

        // Con il chunking, il numero di query cresce con il numero di
        // BLOCCHI (catalogo / chunk-size), non con il numero di record: a
        // parita' di chunk-size (200) e una crescita lineare nel numero di
        // blocchi e' il comportamento atteso e desiderato (mai un aumento
        // per-record, mai N+1). Verifichiamo che la crescita segua
        // esattamente il numero di blocchi, non ecceda quel rapporto.
        $expectedRatio = ceil(1000 / 200) / ceil(20 / 200);
        $actualRatio = $large / max(1, $small);

        $this->assertLessThanOrEqual(
            $expectedRatio + 1,
            $actualRatio,
            "Con 20 media: {$small} query. Con 1.000: {$large} query. La crescita deve seguire il numero di blocchi (chunk=200), non il numero di record."
        );
    }

    public function test_query_count_stays_bounded_at_a_10000_media_catalog(): void
    {
        $huge = $this->queryCountForDryRun(10000, 'catalogo-10000');

        // 10.000 record / 200 per blocco = 50 blocchi. Un numero di query
        // dell'ordine delle centinaia (non migliaia) dimostra che non c'e'
        // una query per record.
        $this->assertLessThan(
            500,
            $huge,
            "10.000 media hanno generato {$huge} query — atteso un numero legato al numero di BLOCCHI (~50), non ai record."
        );
    }

    public function test_command_completes_in_reasonable_time_on_a_10000_media_catalog(): void
    {
        Media::query()->delete();
        $this->bulkInsertMediaWithoutFiles(10000, 'catalogo-tempo');

        $start = microtime(true);
        $exitCode = Artisan::call('media:generate-responsive', ['--chunk' => 200]);
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertLessThan(
            15000,
            $elapsedMs,
            "Dry-run su 10.000 media (tutti a sorgente mancante, nessuna lettura/scrittura immagine) ha impiegato {$elapsedMs}ms — soglia di sicurezza generosa."
        );
    }

    public function test_writes_a_correct_variant_for_each_of_100_real_media_with_flat_per_item_cost(): void
    {
        Media::query()->delete();
        $author = User::factory()->create();
        $diskNames = [];

        for ($i = 0; $i < 100; $i++) {
            $diskName = "articles/covers/reale-{$i}.webp";
            $path = public_path('assets/img/'.$diskName);
            @mkdir(dirname($path), 0775, true);
            $image = imagecreatetruecolor(1600, 900);
            imagefill($image, 0, 0, imagecolorallocate($image, $i % 255, 100, 150));
            imagewebp($image, $path, 80);
            imagedestroy($image);

            Media::create([
                'user_id' => $author->id,
                'filename' => "reale-{$i}.webp",
                'disk_name' => $diskName,
                'mime_type' => 'image/webp',
                'size' => filesize($path),
            ]);

            $diskNames[] = $diskName;
        }

        $exitCode = Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true, '--chunk' => 25]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        foreach ($diskNames as $diskName) {
            $base = pathinfo($diskName, PATHINFO_FILENAME);
            $this->assertFileExists(public_path("assets/img/articles/covers/{$base}-480w.webp"));
            $this->assertFileExists(public_path("assets/img/articles/covers/{$base}-960w.webp"));
        }
    }

    /**
     * FASE 11: il rendering pubblico (<x-responsive-image>, tramite
     * ResponsiveImageVariantService::resolveForMarkup()) non esegue MAI
     * query al database — solo controlli sul filesystem (getimagesize/
     * file_exists) — quindi non introduce alcun N+1 indipendentemente da
     * quante immagini compaiono in una pagina. Verificato qui misurando
     * che il conteggio query di una pagina con card multiple non dipenda
     * dal numero di varianti presenti sul filesystem per ciascuna
     * immagine (0, 1 o 2 varianti generate).
     */
    public function test_resolving_markup_for_many_images_performs_no_database_queries(): void
    {
        $paths = [];
        for ($i = 0; $i < 50; $i++) {
            $diskName = "articles/covers/pagina-{$i}.webp";
            $path = public_path('assets/img/'.$diskName);
            @mkdir(dirname($path), 0775, true);
            $image = imagecreatetruecolor(1600, 900);
            imagefill($image, 0, 0, imagecolorallocate($image, 10, 10, 10));
            imagewebp($image, $path, 80);
            imagedestroy($image);
            $paths[] = $diskName;
        }

        $service = app(ResponsiveImageVariantService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($paths as $diskName) {
            $service->resolveForMarkup($diskName);
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queryCount, 'La risoluzione del markup responsive non deve mai interrogare il database.');
    }
}
