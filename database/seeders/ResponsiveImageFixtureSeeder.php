<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use App\Services\ImageService;
use App\Services\MediaService;
use App\Services\ResponsiveImageVariantService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture deterministica per la missione S2 (responsive images): genera al
 * volo (rumore fotografico sintetico via GD, MAI un'immagine scaricata da
 * produzione — nessun binario versionato nel repository) UNA immagine
 * realistica e la fa transitare dalla PIPELINE DI UPLOAD REALE
 * (ImageService::upload + resizeAndCompress/autoConvertToWebpIfEligible,
 * esattamente come Admin\MediaController::store()), cosi' che le misure di
 * baseline/dopo e i test Playwright osservino lo stesso asset che la
 * produzione genererebbe davvero da un upload.
 *
 * Usata sia dalla misura manuale (script one-off scripts/baseline-measure.mjs),
 * sia dai test browser permanenti (tests/browser/responsive-images.spec.js):
 * un solo posto che genera il fixture, mai due pipeline di generazione
 * diverse.
 */
class ResponsiveImageFixtureSeeder extends Seeder
{
    public const ARTICLE_SLUG = 'responsive-images-fixture-article';

    public const CATEGORY_SLUG = 'intelligenza-artificiale';

    public const CLUSTER_SLUG = 'responsive-images-fixture-percorso';

    public function run(ImageService $imageService, MediaService $mediaService, ResponsiveImageVariantService $responsiveImageVariants): void
    {
        $now = now();

        $diskName = $this->materializeCover($imageService, $mediaService, $responsiveImageVariants);
        $categoryImageBasename = $this->materializeCategoryImage($imageService, $mediaService, $responsiveImageVariants);

        DB::table('categories')->updateOrInsert(
            ['slug' => self::CATEGORY_SLUG],
            [
                'name' => 'Intelligenza Artificiale',
                'description' => 'Categoria deterministica per la fixture responsive images.',
                'image' => $categoryImageBasename,
                'color' => '#2563eb',
                'sort_order' => 0,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $authorId = DB::table('users')->where('email', 'responsive-images-fixture@example.test')->value('id');
        if (! $authorId) {
            $authorId = DB::table('users')->insertGetId([
                'name' => 'Responsive Images Fixture Author',
                'email' => 'responsive-images-fixture@example.test',
                'password' => Hash::make('responsive-images-fixture'),
                'bio' => 'Autore deterministico usato esclusivamente dalla fixture responsive images.',
                'role' => 'author',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $articleId = DB::table('articles')->where('slug', self::ARTICLE_SLUG)->value('id');
        $articlePayload = [
            'user_id' => $authorId,
            'title' => 'Fixture responsive images: la copertina che misuriamo',
            'excerpt' => 'Articolo deterministico usato per misurare byte immagine, dimensioni e CLS a 390/768/1440.',
            'body' => '<h2>Contenuto stabile</h2><p>'.str_repeat('Testo di riempimento deterministico per la fixture responsive images. ', 20).'</p>',
            'category' => self::CATEGORY_SLUG,
            'cover_image' => $diskName,
            'status' => 'published',
            'featured' => false,
            'read_minutes' => 3,
            'views' => 0,
            'published_at' => $now->copy()->subDay(),
            'verification_status' => 'unverified',
            'updated_at' => $now,
        ];

        if ($articleId) {
            DB::table('articles')->where('id', $articleId)->update($articlePayload);
        } else {
            $articleId = DB::table('articles')->insertGetId($articlePayload + [
                'slug' => self::ARTICLE_SLUG,
                'created_at' => $now,
            ]);
        }

        $clusterId = DB::table('content_clusters')->where('slug', self::CLUSTER_SLUG)->value('id');
        $clusterPayload = [
            'name' => 'Fixture Responsive Images',
            'short_description' => 'Percorso deterministico per la fixture responsive images.',
            'description' => 'Percorso locale usato esclusivamente per misurare le immagini responsive.',
            'cover_image' => $diskName,
            'pillar_article_id' => $articleId,
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'sort_order' => 999,
            'updated_at' => $now,
        ];

        if ($clusterId) {
            DB::table('content_clusters')->where('id', $clusterId)->update($clusterPayload);
        } else {
            $clusterId = DB::table('content_clusters')->insertGetId($clusterPayload + [
                'slug' => self::CLUSTER_SLUG,
                'created_at' => $now,
            ]);
        }

        if (! DB::table('article_content_cluster')->where('article_id', $articleId)->where('content_cluster_id', $clusterId)->exists()) {
            DB::table('article_content_cluster')->insert([
                'article_id' => $articleId,
                'content_cluster_id' => $clusterId,
                'position' => 10,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Riproduce esattamente il blocco di upload di Admin\MediaController::store()
     * (stessa sequenza di chiamate a ImageService), cosi' l'asset di fixture e'
     * indistinguibile da uno caricato davvero da un redattore.
     */
    private function materializeCover(ImageService $imageService, MediaService $mediaService, ResponsiveImageVariantService $responsiveImageVariants): string
    {
        return $this->materializeUpload(
            $imageService,
            $mediaService,
            $responsiveImageVariants,
            'fixture-photo.jpg',
            'articles/covers',
            'responsive-fixture'
        );
    }

    /**
     * Riproduce lo stesso blocco di upload usato dalla copertina articolo,
     * ma con la convenzione di cartella dell'immagine categoria
     * (Admin\CategoryController): un file INDIPENDENTE, mai lo stesso
     * fisico della copertina articolo — riflette la realta' di produzione,
     * dove le due immagini sono sempre upload distinti, e ovviamente
     * risolve correttamente le proprie varianti responsive.
     */
    private function materializeCategoryImage(ImageService $imageService, MediaService $mediaService, ResponsiveImageVariantService $responsiveImageVariants): string
    {
        $diskName = $this->materializeUpload(
            $imageService,
            $mediaService,
            $responsiveImageVariants,
            'fixture-category-photo.jpg',
            'categories',
            'responsive-fixture-category'
        );

        return basename($diskName);
    }

    private function materializeUpload(
        ImageService $imageService,
        MediaService $mediaService,
        ResponsiveImageVariantService $responsiveImageVariants,
        string $originalName,
        string $folder,
        string $suffix,
    ): string {
        $existing = Media::where('filename', $originalName)->first();
        if ($existing && is_file(public_path('assets/img/'.$existing->disk_name))) {
            $responsiveImageVariants->generateForUpload(public_path('assets/img/'.$existing->disk_name), $existing->disk_name);

            return $existing->disk_name;
        }

        $file = new UploadedFile($this->generateSyntheticPhoto(), $originalName, 'image/jpeg', null, true);

        $ext = $imageService->safeExtension($file, allowGif: true);
        $diskName = $imageService->buildFileName($file, $ext, $suffix);
        $uploadPath = public_path('assets/img/'.$folder);

        $fullPath = $imageService->upload($file, $uploadPath, $diskName);
        $diskName = $folder.'/'.$diskName;

        $webpApplied = false;
        if (config('media.auto_webp_on_upload', true)) {
            $conversion = $imageService->autoConvertToWebpIfEligible(
                $fullPath,
                $ext,
                (int) config('media.webp_quality', 82),
                (int) config('media.webp_max_width', 1600)
            );

            $webpApplied = $conversion['webp_applied'];

            if ($webpApplied) {
                $fullPath = $conversion['full_path'];
                $ext = $conversion['ext'];
                $diskName = $imageService->changeExtension($diskName, 'webp');
            }
        }

        if (! $webpApplied) {
            $imageService->resizeAndCompress($fullPath, $ext, 1600, [
                'jpg' => 82, 'png' => 7, 'webp' => 82,
            ], preserveTransparency: true, alwaysReencode: true, logErrors: true);
        }

        $user = User::where('email', 'responsive-images-fixture@example.test')->first()
            ?? User::first();

        $mediaService->register($user, $originalName, $diskName, 'image/webp', filesize($fullPath) ?: 0);

        // FASE 5 (missione S2): stesso passo eseguito dai controller reali
        // dopo l'upload — genera le varianti responsive per questo asset,
        // cosi' la fixture osservata da Playwright e' identica a quella
        // che un vero upload produrrebbe oggi (con la wiring attuale),
        // non a quella pre-missione.
        $responsiveImageVariants->generateForUpload($fullPath, $diskName);

        return $diskName;
    }

    /**
     * Genera un JPEG con rumore fotografico sintetico (gradiente + rumore
     * casuale deterministico + macchie sfumate), MAI un'immagine scaricata
     * da produzione ne' un binario versionato: un JPEG a tinta piatta
     * comprimerebbe a pochi KB, un peso non rappresentativo di una vera
     * foto editoriale — questo genera invece un'entropia realistica cosi'
     * che le misure di byte (FASE 3/13 della missione) riflettano un caso
     * reale.
     */
    private function generateSyntheticPhoto(): string
    {
        $width = 2400;
        $height = 1350;
        $path = sys_get_temp_dir().'/responsive-fixture-'.bin2hex(random_bytes(4)).'.jpg';

        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $r = (int) min(255, 30 + 140 * ($y / $height));
            $g = (int) min(255, 60 + 100 * (1 - $y / $height));
            $b = (int) min(255, 90 + 120 * (0.5 + 0.5 * sin($y / 40)));
            imageline($image, 0, $y, $width, $y, imagecolorallocate($image, $r, $g, $b));
        }

        mt_srand(42);
        for ($i = 0; $i < 60000; $i++) {
            $shade = mt_rand(0, 255);
            imagesetpixel($image, mt_rand(0, $width - 1), mt_rand(0, $height - 1), imagecolorallocate($image, $shade, $shade, $shade));
        }

        for ($i = 0; $i < 40; $i++) {
            $shade = mt_rand(0, 255);
            imagefilledellipse(
                $image,
                mt_rand(0, $width),
                mt_rand(0, $height),
                mt_rand(40, 220),
                mt_rand(40, 220),
                imagecolorallocatealpha($image, $shade, $shade, $shade, 90)
            );
        }

        imagejpeg($image, $path, 85);
        imagedestroy($image);

        return $path;
    }
}
