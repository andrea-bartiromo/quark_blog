<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\SpecialPage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MediaMoveService
{
    public function __construct(
        private readonly MediaFolderService $folderService,
        private readonly MediaReferenceService $referenceService,
        private readonly PublicMediaSyncService $publicMediaSync,
    ) {}

    public function move(int $mediaId, ?int $destinationFolderId, ?int $actingUserId = null): MediaMoveResult
    {
        return DB::transaction(function () use ($mediaId, $destinationFolderId, $actingUserId) {
            $media = Media::whereKey($mediaId)->lockForUpdate()->firstOrFail();
            $destination = $destinationFolderId !== null
                ? MediaFolder::whereKey($destinationFolderId)->firstOrFail()
                : null;

            $oldDiskName = $media->disk_name;
            $newDiskName = $this->folderService->diskName($destination, basename($oldDiskName));

            if ($newDiskName === $oldDiskName) {
                Log::info('MediaMoveService: nessuno spostamento necessario', [
                    'media_id' => $media->id,
                    'disk_name' => $oldDiskName,
                    'user_id' => $actingUserId,
                ]);

                return MediaMoveResult::noop($media, 'Il file si trova gia nella destinazione selezionata.');
            }

            $preflight = $this->referenceService->preflight($media, $newDiskName);

            if (! $preflight['can_move']) {
                Log::info('MediaMoveService: spostamento bloccato dal preflight', [
                    'media_id' => $media->id,
                    'old_disk_name' => $oldDiskName,
                    'new_disk_name' => $newDiskName,
                    'blocking_count' => count($preflight['blocking_references']),
                    'user_id' => $actingUserId,
                ]);

                return MediaMoveResult::blocked($media, $preflight);
            }

            $root = public_path('assets/img');
            $oldAbsolute = $this->safeExistingFilePath($root, $oldDiskName);

            $destinationDir = $this->folderService->ensureDirectoryFor($destination);
            $newAbsolute = $destinationDir.DIRECTORY_SEPARATOR.basename($oldDiskName);

            $this->assertNoCollision($newDiskName, $newAbsolute, $media->id);

            if (! @rename($oldAbsolute, $newAbsolute)) {
                throw new RuntimeException('Spostamento fisico del file fallito.');
            }

            $publicSyncMoveSucceeded = false;

            try {
                /*
                 * Replica lo spostamento anche nella document root
                 * pubblica secondaria (public_html), quando configurata.
                 * Eseguito qui, dentro lo stesso try: un fallimento fa
                 * scattare esattamente lo stesso rollback gia' previsto
                 * sotto per il rename applicativo, cosi che le due
                 * directory non restino mai disallineate tra loro.
                 */
                $this->publicMediaSync->move($newAbsolute, $oldDiskName, $newDiskName);
                $publicSyncMoveSucceeded = true;

                $media->update(['disk_name' => $newDiskName]);
                $this->applyReferenceUpdates($preflight['updatable_references']);

                clearstatcache(true, $newAbsolute);
                if (! is_file($newAbsolute) || Media::whereKey($media->id)->value('disk_name') !== $newDiskName) {
                    throw new RuntimeException('Verifica post-aggiornamento fallita.');
                }
            } catch (Throwable $exception) {
                if (! @rename($newAbsolute, $oldAbsolute)) {
                    Log::critical('MediaMoveService: compensazione filesystem fallita', [
                        'media_id' => $media->id,
                        'old_disk_name' => $oldDiskName,
                        'new_disk_name' => $newDiskName,
                        'error' => $exception->getMessage(),
                        'user_id' => $actingUserId,
                    ]);

                    throw new RuntimeException(
                        'Spostamento fallito e compensazione del filesystem non riuscita. Verificare manualmente: '.$newDiskName,
                        previous: $exception
                    );
                }

                /*
                 * Il rollback applicativo sopra riporta il file al vecchio
                 * nome in public/assets/img, ma publicMediaSync->move() puo'
                 * essere gia' stato eseguito con successo prima che questo
                 * blocco catch scattasse (es. per un fallimento successivo
                 * di applyReferenceUpdates()): senza questa compensazione
                 * speculare, la radice pubblica secondaria resterebbe
                 * disallineata sul nuovo nome mentre l'app e il DB sono
                 * gia' tornati al vecchio, ricreando esattamente il bug che
                 * questo servizio deve prevenire.
                 *
                 * Eseguita pero' solo se la prima publicMediaSync->move()
                 * e' davvero riuscita ($publicSyncMoveSucceeded): se invece
                 * e' quella stessa chiamata ad aver fallito (es. per una
                 * collisione preesistente con un file estraneo gia'
                 * presente in $newDiskName nella sola radice pubblica),
                 * non e' mai stato spostato nulla li' e non c'e' nulla da
                 * compensare — la compensazione andrebbe altrimenti a
                 * cancellare o alterare quel file estraneo, che questo
                 * servizio non ha mai toccato.
                 */
                if ($publicSyncMoveSucceeded) {
                    try {
                        $this->publicMediaSync->move($oldAbsolute, $newDiskName, $oldDiskName);
                    } catch (Throwable $publicSyncException) {
                        Log::critical('MediaMoveService: compensazione della radice pubblica secondaria fallita', [
                            'media_id' => $media->id,
                            'old_disk_name' => $oldDiskName,
                            'new_disk_name' => $newDiskName,
                            'error' => $publicSyncException->getMessage(),
                            'user_id' => $actingUserId,
                        ]);

                        throw new RuntimeException(
                            'Spostamento fallito e compensazione della directory pubblica secondaria non riuscita. Verificare manualmente: '.$newDiskName,
                            previous: $exception
                        );
                    }
                }

                Log::warning('MediaMoveService: rollback eseguito dopo errore', [
                    'media_id' => $media->id,
                    'old_disk_name' => $oldDiskName,
                    'new_disk_name' => $newDiskName,
                    'error' => $exception->getMessage(),
                    'user_id' => $actingUserId,
                ]);

                throw $exception;
            }

            Log::info('MediaMoveService: spostamento riuscito', [
                'media_id' => $media->id,
                'old_disk_name' => $oldDiskName,
                'new_disk_name' => $newDiskName,
                'user_id' => $actingUserId,
                'updated_references' => count($preflight['updatable_references']),
            ]);

            return MediaMoveResult::moved($media, $oldDiskName, $newDiskName, $preflight);
        });
    }

    private function safeExistingFilePath(string $root, string $diskName): string
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new RuntimeException('La radice media non e risolvibile.');
        }

        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $diskName);

        if (is_link($path)) {
            throw new RuntimeException('Il file sorgente e un collegamento simbolico.');
        }

        if (! is_file($path)) {
            throw new RuntimeException('Il file sorgente non esiste sul filesystem: '.$diskName);
        }

        $real = realpath($path);
        if ($real === false || ! $this->isWithin($real, $rootReal)) {
            throw new RuntimeException('Il file sorgente esce dalla radice media.');
        }

        return $real;
    }

    private function assertNoCollision(string $newDiskName, string $newAbsolute, int $movingMediaId): void
    {
        if (Media::where('disk_name', $newDiskName)->where('id', '!=', $movingMediaId)->exists()) {
            throw new RuntimeException('Esiste gia un file registrato con questo nome nella destinazione.');
        }

        clearstatcache(true, $newAbsolute);

        if (file_exists($newAbsolute) || is_link($newAbsolute)) {
            throw new RuntimeException('Esiste gia un file sul filesystem nella destinazione.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $updatable
     */
    private function applyReferenceUpdates(array $updatable): void
    {
        $specialPageRefs = [];

        foreach ($updatable as $ref) {
            if ($ref['type'] === 'special_page_content') {
                $specialPageRefs[] = $ref;

                continue;
            }

            $this->applySingleReference($ref);
        }

        $this->applySpecialPageReferences($specialPageRefs);
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    private function applySingleReference(array $ref): void
    {
        match ($ref['type']) {
            'article_cover_image' => Article::whereKey($ref['record_id'])->update(['cover_image' => $ref['new_value']]),
            'ad_banner_image' => Ad::whereKey($ref['record_id'])->update(['banner_image' => $ref['new_value']]),
            'user_photo' => User::whereKey($ref['record_id'])->update(['photo' => $ref['new_value']]),
            'category_image' => Category::whereKey($ref['record_id'])->update(['image' => $ref['new_value']]),
            default => throw new RuntimeException('Tipo di riferimento sconosciuto: '.$ref['type']),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $refs
     */
    private function applySpecialPageReferences(array $refs): void
    {
        $byPage = [];
        foreach ($refs as $ref) {
            $byPage[$ref['record_id']][] = $ref;
        }

        foreach ($byPage as $pageId => $pageRefs) {
            $page = SpecialPage::whereKey($pageId)->lockForUpdate()->firstOrFail();
            $content = $page->content ?? [];

            foreach ($pageRefs as $ref) {
                data_set($content, $ref['json_path'], $ref['new_value']);
            }

            $page->update(['content' => $content]);
        }
    }

    private function isWithin(string $path, string $root): bool
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }
}
