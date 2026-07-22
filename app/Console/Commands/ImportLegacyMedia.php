<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\User;
use App\Services\MediaFolderService;
use App\Services\MediaService;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class ImportLegacyMedia extends Command
{
    protected $signature = 'media:import-legacy
        {--user= : ID o email dell\'utente proprietario}
        {--path=assets/img : Percorso relativo alla directory public}
        {--dry-run : Mostra cosa verrebbe importato senza scrivere nel database}';

    protected $description = 'Registra nella libreria Media le immagini storiche presenti sotto public/assets/img';

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function handle(MediaService $mediaService, MediaFolderService $mediaFolderService): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            return Command::FAILURE;
        }

        $paths = $this->resolvePaths();
        if (! $paths) {
            return Command::FAILURE;
        }

        [$scanRoot, $mediaRoot] = $paths;
        $dryRun = (bool) $this->option('dry-run');
        $counts = [
            'found' => 0,
            'imported' => 0,
            'would_import' => 0,
            'registered' => 0,
            'ignored' => 0,
            'errors' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($iterator as $file) {
            $this->processFile($file, $mediaRoot, $user, $mediaService, $mediaFolderService, $dryRun, $fileInfo, $counts);
        }

        $this->newLine();
        $this->info('Riepilogo importazione media storici');
        $this->line('File immagine trovati: '.$counts['found']);
        $this->line('Importati: '.$counts['imported']);
        if ($dryRun) {
            $this->line('Da importare: '.$counts['would_import']);
        }
        $this->line('Gia registrati: '.$counts['registered']);
        $this->line('Ignorati: '.$counts['ignored']);
        $this->line('Errori: '.$counts['errors']);

        return Command::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $identifier = trim((string) $this->option('user'));

        if ($identifier === '') {
            $this->error('L\'opzione --user e obbligatoria. Specificare un ID o un indirizzo email.');

            return null;
        }

        $user = ctype_digit($identifier)
            ? User::find((int) $identifier)
            : User::where('email', $identifier)->first();

        if (! $user) {
            $this->error('Utente non trovato: '.$identifier);

            return null;
        }

        return $user;
    }

    /**
     * @return array{string, string}|null
     */
    private function resolvePaths(): ?array
    {
        $relativePath = trim((string) $this->option('path'));

        if ($relativePath === '' || $this->isAbsolutePath($relativePath)) {
            $this->error('Il percorso deve essere relativo alla directory public.');

            return null;
        }

        $publicRoot = realpath(public_path());
        $mediaRoot = realpath(public_path('assets/img'));
        $scanRoot = realpath(public_path($relativePath));

        if (! $publicRoot || ! $mediaRoot || ! is_dir($mediaRoot) || ! is_readable($mediaRoot)) {
            $this->error('La directory public/assets/img non esiste o non e leggibile.');

            return null;
        }

        if (! $scanRoot || ! is_dir($scanRoot) || ! is_readable($scanRoot)) {
            $this->error('La directory richiesta non esiste o non e leggibile: '.$relativePath);

            return null;
        }

        if (! $this->isWithin($scanRoot, $publicRoot)) {
            $this->error('Il percorso richiesto esce dalla directory public.');

            return null;
        }

        if (! $this->isWithin($scanRoot, $mediaRoot)) {
            $this->error('Il percorso richiesto deve trovarsi sotto public/assets/img.');

            return null;
        }

        return [$scanRoot, $mediaRoot];
    }

    /**
     * @param  array{found: int, imported: int, would_import: int, registered: int, ignored: int, errors: int}  $counts
     */
    private function processFile(
        SplFileInfo $file,
        string $mediaRoot,
        User $user,
        MediaService $mediaService,
        MediaFolderService $mediaFolderService,
        bool $dryRun,
        \finfo $fileInfo,
        array &$counts
    ): void {
        if (! $file->isFile()) {
            return;
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! $this->isWithin($realPath, $mediaRoot)) {
            $counts['errors']++;
            $this->warn('File fuori dalla radice media o non risolvibile: '.$file->getPathname());

            return;
        }

        $diskName = str_replace('\\', '/', substr($realPath, strlen($mediaRoot) + 1));

        if ($this->isHiddenPath($diskName)) {
            $counts['ignored']++;

            return;
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $counts['ignored']++;

            return;
        }

        $counts['found']++;

        if (! is_readable($realPath)) {
            $counts['errors']++;
            $this->warn('File non leggibile: '.$diskName);

            return;
        }

        $mimeType = $fileInfo->file($realPath);
        if (! is_string($mimeType) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $counts['errors']++;
            $this->warn('MIME type non valido: '.$diskName);

            return;
        }

        $size = filesize($realPath);
        if ($size === false) {
            $counts['errors']++;
            $this->warn('Dimensione non rilevabile: '.$diskName);

            return;
        }

        if (Media::where('disk_name', $diskName)->exists()) {
            $counts['registered']++;
            $this->line('GIA REGISTRATO '.$diskName);

            if (! $dryRun) {
                $this->syncFolders($mediaFolderService, $diskName, $user, $counts);
            }

            return;
        }

        if ($dryRun) {
            $counts['would_import']++;
            $this->line('[DRY-RUN] DA IMPORTARE '.$diskName);

            return;
        }

        try {
            $media = $mediaService->register($user, basename($realPath), $diskName, $mimeType, $size);

            if ($media->wasRecentlyCreated) {
                $counts['imported']++;
                $this->line('IMPORTATO '.$diskName);
            } else {
                $counts['registered']++;
                $this->line('GIA REGISTRATO '.$diskName);
            }

            $this->syncFolders($mediaFolderService, $diskName, $user, $counts);
        } catch (Throwable $exception) {
            $counts['errors']++;
            $this->warn('Errore durante la registrazione di '.$diskName.': '.$exception->getMessage());
        }
    }

    private function syncFolders(MediaFolderService $service, string $diskName, User $user, array &$counts): void
    {
        try {
            $service->ensureHierarchyForDiskName($diskName, $user);
        } catch (Throwable $exception) {
            $counts['errors']++;
            $this->warn('Media importato, categorie non sincronizzate per '.$diskName.': '.$exception->getMessage());
        }
    }

    private function isHiddenPath(string $relativePath): bool
    {
        foreach (explode('/', str_replace('\\', '/', $relativePath)) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
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
