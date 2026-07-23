<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\SpecialPage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Determina, in sola lettura, la cartella di destinazione corretta per un
 * Media esistente in base ai riferimenti strutturati reali gia individuati
 * da MediaReferenceService — mai in base al solo nome del file. Non sposta
 * nulla: produce solo un MediaClassificationResult che il comando Artisan
 * media:classify-existing usera per decidere se, e come, applicare lo
 * spostamento tramite MediaMoveService.
 */
class MediaClassificationService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Mappa tipo di riferimento (da MediaReferenceService) -> dominio logico
     * di classificazione. Un dominio e "presente" per un Media quando almeno
     * un riferimento aggiornabile di quel tipo lo riguarda.
     */
    private const DOMAIN_REFERENCE_TYPES = [
        'article_cover_image' => 'article',
        'ad_banner_image' => 'ad',
        'category_image' => 'category',
        'user_photo' => 'user',
        'special_page_content' => 'special_page',
    ];

    public function __construct(
        private readonly MediaReferenceService $referenceService,
        private readonly MediaFolderService $folderService,
    ) {}

    public function planFor(Media $media): MediaClassificationResult
    {
        $old = $media->disk_name;
        $currentFolder = str_contains($old, '/') ? dirname($old) : null;

        if (! $this->sourceExists($old)) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: null,
                proposedDiskName: null,
                domain: null,
                status: 'missing_source',
                reason: 'Il file non e presente sul filesystem.',
                referencesFound: [],
                updatableCount: 0,
                blockingCount: 0,
                blockingDetails: [],
                confidence: 'none',
            );
        }

        try {
            $detection = $this->referenceService->preflight($media, $old);
        } catch (Throwable $exception) {
            return $this->errorResult($media, $currentFolder, null, null, 'Errore durante la rilevazione dei riferimenti: '.$exception->getMessage());
        }

        $updatable = $detection['updatable_references'];
        $blocking = $detection['blocking_references'];
        $allReferences = array_merge($updatable, $blocking);

        if ($blocking !== []) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: null,
                proposedDiskName: null,
                domain: null,
                status: 'blocked',
                reason: 'Sono presenti '.count($blocking).' riferimento/i non aggiornabile/i in sicurezza.',
                referencesFound: $allReferences,
                updatableCount: count($updatable),
                blockingCount: count($blocking),
                blockingDetails: $blocking,
                confidence: 'high',
            );
        }

        $domains = $this->domainsPresent($updatable);

        if ($domains === []) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: 'unclassified',
                proposedDiskName: null,
                domain: null,
                status: 'unclassified',
                reason: 'Nessun riferimento strutturato trovato: nessuna base per una classificazione automatica.',
                referencesFound: $allReferences,
                updatableCount: count($updatable),
                blockingCount: 0,
                blockingDetails: [],
                confidence: 'none',
            );
        }

        if (count($domains) > 1) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: null,
                proposedDiskName: null,
                domain: null,
                status: 'ambiguous',
                reason: 'Il media e riferito da domini incompatibili: '.implode(', ', array_keys($domains)).'.',
                referencesFound: $allReferences,
                updatableCount: count($updatable),
                blockingCount: 0,
                blockingDetails: [],
                confidence: 'low',
            );
        }

        $domain = array_key_first($domains);
        [$targetFolder, $ambiguityReason] = $this->resolveTargetFolder($domain, $updatable);

        if ($targetFolder === null) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: null,
                proposedDiskName: null,
                domain: $domain,
                status: 'ambiguous',
                reason: $ambiguityReason,
                referencesFound: $allReferences,
                updatableCount: count($updatable),
                blockingCount: 0,
                blockingDetails: [],
                confidence: 'low',
            );
        }

        $proposedDiskName = $targetFolder.'/'.basename($old);

        if ($proposedDiskName === $old) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: $targetFolder,
                proposedDiskName: $old,
                domain: $domain,
                status: 'noop',
                reason: 'Il file si trova gia nella cartella corretta per il dominio "'.$domain.'".',
                referencesFound: $allReferences,
                updatableCount: count($updatable),
                blockingCount: 0,
                blockingDetails: [],
                confidence: 'high',
            );
        }

        try {
            $final = $this->referenceService->preflight($media, $proposedDiskName);
        } catch (Throwable $exception) {
            return $this->errorResult($media, $currentFolder, $targetFolder, $proposedDiskName, 'Errore durante la verifica finale: '.$exception->getMessage(), $domain);
        }

        if ($final['blocking_references'] !== []) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: $targetFolder,
                proposedDiskName: $proposedDiskName,
                domain: $domain,
                status: 'blocked',
                reason: 'La verifica finale ha rilevato riferimenti bloccanti non emersi nella rilevazione iniziale.',
                referencesFound: $final['updatable_references'],
                updatableCount: count($final['updatable_references']),
                blockingCount: count($final['blocking_references']),
                blockingDetails: $final['blocking_references'],
                confidence: 'high',
            );
        }

        if (Media::where('disk_name', $proposedDiskName)->where('id', '!=', $media->id)->exists()) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: $targetFolder,
                proposedDiskName: $proposedDiskName,
                domain: $domain,
                status: 'collision',
                reason: 'Esiste gia un altro Media registrato con questo disk_name nella destinazione.',
                referencesFound: $final['updatable_references'],
                updatableCount: count($final['updatable_references']),
                blockingCount: 0,
                blockingDetails: [],
                confidence: 'high',
            );
        }

        if (is_file(public_path('assets/img/'.$proposedDiskName))) {
            return new MediaClassificationResult(
                mediaId: $media->id,
                currentDiskName: $old,
                currentFolder: $currentFolder,
                proposedFolder: $targetFolder,
                proposedDiskName: $proposedDiskName,
                domain: $domain,
                status: 'collision',
                reason: 'Esiste gia un file sul filesystem nella destinazione.',
                referencesFound: $final['updatable_references'],
                updatableCount: count($final['updatable_references']),
                blockingCount: 0,
                blockingDetails: [],
                confidence: 'high',
            );
        }

        return new MediaClassificationResult(
            mediaId: $media->id,
            currentDiskName: $old,
            currentFolder: $currentFolder,
            proposedFolder: $targetFolder,
            proposedDiskName: $proposedDiskName,
            domain: $domain,
            status: 'movable',
            reason: 'Classificato nel dominio "'.$domain.'" in base a '.count($final['updatable_references']).' riferimento/i strutturato/i aggiornabile/i.',
            referencesFound: $final['updatable_references'],
            updatableCount: count($final['updatable_references']),
            blockingCount: 0,
            blockingDetails: [],
            confidence: 'high',
        );
    }

    /**
     * Crea (se necessario, in modo idempotente) la MediaFolder proposta,
     * riusando MediaFolderService::upsertDefinition(). Va chiamato solo in
     * fase di applicazione (--apply), mai durante la pianificazione.
     */
    public function ensureTargetFolder(MediaClassificationResult $result): ?MediaFolder
    {
        if ($result->proposedFolder === null || $result->status !== 'movable') {
            return null;
        }

        $existing = MediaFolder::where('path', $result->proposedFolder)->first();
        if ($existing) {
            return $existing;
        }

        $definition = collect(config('media.classification_folders'))
            ->first(fn (array $def) => $def['path'] === $result->proposedFolder);

        if (! $definition) {
            throw new RuntimeException('Impossibile determinare la definizione della cartella per: '.$result->proposedFolder);
        }

        $parent = $definition['parent_path']
            ? MediaFolder::where('path', $definition['parent_path'])->first()
            : null;

        return $this->folderService->upsertDefinition([
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'path' => $definition['path'],
            'is_protected' => true,
            'sort_order' => 0,
        ], $parent);
    }

    /**
     * File presenti sotto public/assets/img senza un record Media
     * corrispondente. Sola lettura: nessuna registrazione, spostamento o
     * eliminazione automatica.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findUnregisteredFiles(): array
    {
        $root = public_path('assets/img');
        if (! is_dir($root)) {
            return [];
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            return [];
        }

        $registered = Media::pluck('disk_name')->flip();
        $knownBasenames = Media::pluck('filename')->filter()->map(fn ($f) => strtolower((string) $f))->flip();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $orphans = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $real = $file->getRealPath();
            if (! $real || ! str_starts_with($real, $rootReal.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $diskName = str_replace('\\', '/', substr($real, strlen($rootReal) + 1));

            if ($this->isHiddenPath($diskName) || $registered->has($diskName)) {
                continue;
            }

            $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $orphans[] = [
                'path' => $diskName,
                'size' => filesize($real) ?: 0,
                'extension' => $extension,
                'possible_duplicate' => $knownBasenames->has(strtolower(basename($diskName))),
            ];
        }

        return $orphans;
    }

    /**
     * @param  list<array<string, mixed>>  $updatable
     * @return array<string, bool> chiavi = domini presenti
     */
    private function domainsPresent(array $updatable): array
    {
        $domains = [];

        foreach ($updatable as $ref) {
            if (isset(self::DOMAIN_REFERENCE_TYPES[$ref['type']])) {
                $domains[self::DOMAIN_REFERENCE_TYPES[$ref['type']]] = true;
            }
        }

        return $domains;
    }

    /**
     * @param  list<array<string, mixed>>  $updatable
     * @return array{0: ?string, 1: string}
     */
    private function resolveTargetFolder(string $domain, array $updatable): array
    {
        if ($domain === 'special_page') {
            return $this->resolveSpecialPageFolder($updatable);
        }

        $definition = config('media.classification_folders.'.$domain);

        if (! $definition) {
            return [null, 'Nessuna cartella di classificazione configurata per il dominio "'.$domain.'".'];
        }

        return [$definition['path'], ''];
    }

    /**
     * @param  list<array<string, mixed>>  $updatable
     * @return array{0: ?string, 1: string}
     */
    private function resolveSpecialPageFolder(array $updatable): array
    {
        $pageIds = collect($updatable)
            ->where('type', 'special_page_content')
            ->pluck('record_id')
            ->unique();

        $fallback = config('media.classification_folders.special_page.path');

        $paths = $pageIds->map(function (int $id) use ($fallback) {
            $slug = SpecialPage::whereKey($id)->value('slug');

            return ($slug && MediaFolder::where('path', $slug)->exists()) ? $slug : $fallback;
        })->unique();

        if ($paths->count() > 1) {
            return [null, 'Il media e riferito da piu pagine speciali con destinazioni diverse.'];
        }

        return [$paths->first(), ''];
    }

    private function sourceExists(string $diskName): bool
    {
        $path = public_path('assets/img/'.$diskName);

        return is_file($path) && ! is_link($path);
    }

    private function isHiddenPath(string $relativePath): bool
    {
        foreach (explode('/', $relativePath) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    private function errorResult(
        Media $media,
        ?string $currentFolder,
        ?string $proposedFolder,
        ?string $proposedDiskName,
        string $reason,
        ?string $domain = null,
    ): MediaClassificationResult {
        return new MediaClassificationResult(
            mediaId: $media->id,
            currentDiskName: $media->disk_name,
            currentFolder: $currentFolder,
            proposedFolder: $proposedFolder,
            proposedDiskName: $proposedDiskName,
            domain: $domain,
            status: 'error',
            reason: $reason,
            referencesFound: [],
            updatableCount: 0,
            blockingCount: 0,
            blockingDetails: [],
            confidence: 'none',
        );
    }
}
