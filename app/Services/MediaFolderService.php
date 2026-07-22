<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaFolderService
{
    public const MAX_DEPTH = 3;

    public function create(
        ?User $creator,
        string $name,
        ?MediaFolder $parent = null,
        ?string $description = null,
        ?string $icon = null
    ): MediaFolder {
        $name = $this->normalizeName($name);
        $slug = Str::slug($name);

        if ($slug === '') {
            throw new DomainException('Il nome deve produrre uno slug valido.');
        }

        if ($parent && $parent->depth() >= self::MAX_DEPTH) {
            throw new DomainException('La profondità massima è di 3 livelli.');
        }

        $path = $parent ? $parent->path.'/'.$slug : $slug;
        $this->assertSafeFolderPath($path);

        if (MediaFolder::where('path', $path)->exists()) {
            throw new DomainException('Esiste già una categoria con questo nome nella categoria selezionata.');
        }

        $sortOrder = ((int) MediaFolder::where('parent_id', $parent?->id)->max('sort_order')) + 10;

        return $this->createRecordAndDirectory([
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'parent_id' => $parent?->id,
            'created_by' => $creator?->id,
            'is_protected' => false,
            'sort_order' => $sortOrder,
            'description' => $this->nullableTrimmed($description),
            'icon' => $this->nullableTrimmed($icon),
        ]);
    }

    public function delete(MediaFolder $folder): void
    {
        if ($folder->is_protected) {
            throw new DomainException('Questa categoria è protetta e non può essere eliminata.');
        }

        if ($folder->children()->exists() || $this->containsMediaRecursively($folder)) {
            throw new DomainException('La categoria contiene immagini, sottocategorie o file non registrati. Sposta o rimuovi prima tutti i contenuti.');
        }

        $directory = $this->absolutePath($folder->path);
        $directoryExisted = is_dir($directory) && ! is_link($directory);

        if (is_link($directory) || ($directoryExisted && $this->directoryHasContents($directory))) {
            throw new DomainException('La categoria contiene immagini, sottocategorie o file non registrati. Sposta o rimuovi prima tutti i contenuti.');
        }

        DB::beginTransaction();
        $directoryRemoved = false;

        try {
            $folder->delete();

            if ($directoryExisted) {
                if (! @rmdir($directory)) {
                    throw new RuntimeException('La directory della categoria non può essere eliminata.');
                }
                $directoryRemoved = true;
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($directoryRemoved && ! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            throw $exception;
        }
    }

    public function defaultUploadFolder(?User $creator = null): MediaFolder
    {
        return MediaFolder::where('path', '_da-classificare')->first()
            ?? $this->upsertDefinition([
                'name' => 'Da classificare',
                'slug' => '_da-classificare',
                'path' => '_da-classificare',
                'is_protected' => true,
                'sort_order' => 10,
            ], null, $creator);
    }

    /**
     * @param  array{name: string, slug: string, path: string, is_protected?: bool, sort_order?: int, description?: ?string, icon?: ?string}  $definition
     */
    public function upsertDefinition(array $definition, ?MediaFolder $parent = null, ?User $creator = null): MediaFolder
    {
        $this->assertSafeFolderPath($definition['path']);
        $this->ensureDirectory($definition['path']);

        return MediaFolder::updateOrCreate(
            ['path' => $definition['path']],
            [
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'parent_id' => $parent?->id,
                'created_by' => MediaFolder::where('path', $definition['path'])->value('created_by') ?? $creator?->id,
                'is_protected' => $definition['is_protected'] ?? false,
                'sort_order' => $definition['sort_order'] ?? 0,
                'description' => $definition['description'] ?? null,
                'icon' => $definition['icon'] ?? null,
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    public function folderPathsForDiskName(string $diskName): array
    {
        if ($diskName === ''
            || str_contains($diskName, "\0")
            || str_contains($diskName, '\\')
            || str_starts_with($diskName, '/')
            || preg_match('/^[A-Za-z]:/', $diskName)
        ) {
            throw new DomainException('disk_name non valido.');
        }

        $diskName = trim($diskName, '/');
        $segments = explode('/', $diskName);
        array_pop($segments);

        if ($segments === []) {
            return [];
        }

        if (count($segments) > self::MAX_DEPTH) {
            throw new DomainException('Il percorso supera la profondità massima supportata.');
        }

        $paths = [];
        $current = '';

        foreach ($segments as $segment) {
            $this->assertSafeSegment($segment);
            $current = $current === '' ? $segment : $current.'/'.$segment;
            $this->assertSafeFolderPath($current);
            $paths[] = $current;
        }

        return $paths;
    }

    /**
     * @return array{existing: int, created: int, paths: array<int, string>}
     */
    public function ensureHierarchyForDiskName(string $diskName, ?User $creator = null): array
    {
        $paths = $this->folderPathsForDiskName($diskName);
        $parent = null;
        $existing = 0;
        $created = 0;

        foreach ($paths as $path) {
            $folder = MediaFolder::where('path', $path)->first();

            if ($folder) {
                $existing++;
                if ($folder->parent_id !== $parent?->id) {
                    $folder->update(['parent_id' => $parent?->id]);
                }
                $this->ensureDirectory($path);
            } else {
                $segment = basename(str_replace('/', DIRECTORY_SEPARATOR, $path));
                $slug = Str::slug($segment);
                if ($slug === '') {
                    throw new DomainException('Un segmento del percorso non produce uno slug valido.');
                }

                $folder = $this->createRecordAndDirectory([
                    'name' => $this->displayNameForSegment($segment),
                    'slug' => $slug,
                    'path' => $path,
                    'parent_id' => $parent?->id,
                    'created_by' => $creator?->id,
                    'is_protected' => false,
                    'sort_order' => ((int) MediaFolder::where('parent_id', $parent?->id)->max('sort_order')) + 10,
                    'description' => null,
                    'icon' => null,
                ]);
                $created++;
            }

            $parent = $folder;
        }

        return compact('existing', 'created', 'paths');
    }

    public function ensureDirectoryFor(?MediaFolder $folder): string
    {
        if (! $folder) {
            return $this->ensureMediaRoot();
        }

        return $this->ensureDirectory($folder->path);
    }

    public function diskName(?MediaFolder $folder, string $basename): string
    {
        if ($basename === '' || basename($basename) !== $basename || str_contains($basename, "\0")) {
            throw new DomainException('Nome file non valido.');
        }

        return $folder ? $folder->path.'/'.$basename : $basename;
    }

    /**
     * @return Collection<int, MediaFolder>
     */
    public function orderedHierarchy(): Collection
    {
        $folders = MediaFolder::ordered()->get();
        $byParent = $folders->groupBy(fn (MediaFolder $folder) => $folder->parent_id ?? 0);
        $ordered = collect();

        $append = function (int $parentId) use (&$append, $byParent, $ordered): void {
            foreach ($byParent->get($parentId, collect()) as $folder) {
                $ordered->push($folder);
                $append($folder->id);
            }
        };

        $append(0);

        return $ordered;
    }

    /**
     * @param  Collection<int, MediaFolder>  $folders
     * @return array<int, int>
     */
    public function directMediaCounts(Collection $folders): array
    {
        $wanted = $folders->pluck('path')->flip();
        $counts = [];

        foreach (Media::pluck('disk_name') as $diskName) {
            $directory = str_contains($diskName, '/') ? dirname($diskName) : null;
            if ($directory !== null && $wanted->has($directory)) {
                $counts[$directory] = ($counts[$directory] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function scopeDirectMedia(Builder $query, ?MediaFolder $folder): Builder
    {
        if (! $folder) {
            return $query->where('disk_name', 'not like', '%/%');
        }

        $prefix = $this->escapeLike($folder->path.'/');

        return $query
            ->whereRaw("disk_name LIKE ? ESCAPE '!'", [$prefix.'%'])
            ->whereRaw("disk_name NOT LIKE ? ESCAPE '!'", [$prefix.'%/%']);
    }

    public function absolutePath(string $relativePath): string
    {
        $this->assertSafeFolderPath($relativePath);

        return $this->mediaRoot().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function createRecordAndDirectory(array $attributes): MediaFolder
    {
        $directory = $this->absolutePath($attributes['path']);
        $directoryExisted = is_dir($directory);
        $this->ensureDirectory($attributes['path']);

        try {
            return $this->persistFolder($attributes);
        } catch (QueryException $exception) {
            if (! $directoryExisted && is_dir($directory) && ! $this->directoryHasContents($directory)) {
                @rmdir($directory);
            }

            throw new DomainException('La categoria esiste già o non può essere registrata.', previous: $exception);
        } catch (Throwable $exception) {
            if (! $directoryExisted && is_dir($directory) && ! $this->directoryHasContents($directory)) {
                @rmdir($directory);
            }

            throw $exception;
        }
    }

    protected function persistFolder(array $attributes): MediaFolder
    {
        return DB::transaction(fn () => MediaFolder::create($attributes));
    }

    private function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            throw new DomainException('Il nome della categoria non è valido.');
        }

        if (str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\') || in_array($name, ['.', '..'], true)) {
            throw new DomainException('Il nome non può contenere slash, backslash, null byte o segmenti relativi.');
        }

        return $name;
    }

    private function assertSafeFolderPath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new DomainException('Percorso categoria non valido.');
        }

        $segments = explode('/', $path);
        if (count($segments) > self::MAX_DEPTH) {
            throw new DomainException('La profondità massima è di 3 livelli.');
        }

        foreach ($segments as $segment) {
            $this->assertSafeSegment($segment);
        }
    }

    private function assertSafeSegment(string $segment): void
    {
        if ($segment === '' || in_array($segment, ['.', '..'], true) || str_contains($segment, "\0") || str_contains($segment, '/') || str_contains($segment, '\\')) {
            throw new DomainException('Il percorso contiene un segmento non valido.');
        }

        if (Str::slug($segment) === '' && $segment !== '_da-classificare') {
            throw new DomainException('Il percorso contiene un segmento non valido.');
        }
    }

    private function ensureMediaRoot(): string
    {
        $root = public_path('assets/img');

        if (is_link($root)) {
            throw new RuntimeException('La radice media non può essere un collegamento simbolico.');
        }

        if (! is_dir($root) && ! mkdir($root, 0775, true) && ! is_dir($root)) {
            throw new RuntimeException('La radice media non può essere creata.');
        }

        return $root;
    }

    private function mediaRoot(): string
    {
        return public_path('assets/img');
    }

    private function ensureDirectory(string $path): string
    {
        $this->assertSafeFolderPath($path);
        $current = $this->ensureMediaRoot();
        $rootReal = realpath($current);

        if ($rootReal === false) {
            throw new RuntimeException('La radice media non è risolvibile.');
        }

        foreach (explode('/', $path) as $segment) {
            $current .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($current)) {
                throw new RuntimeException('Il percorso della categoria attraversa un collegamento simbolico.');
            }

            if (! is_dir($current) && ! mkdir($current, 0775) && ! is_dir($current)) {
                throw new RuntimeException('La directory della categoria non può essere creata.');
            }

            $real = realpath($current);
            if ($real === false || ! $this->isWithin($real, $rootReal)) {
                throw new RuntimeException('La directory della categoria esce dalla radice media.');
            }
        }

        return $current;
    }

    private function containsMediaRecursively(MediaFolder $folder): bool
    {
        $prefix = $this->escapeLike($folder->path.'/');

        return Media::whereRaw("disk_name LIKE ? ESCAPE '!'", [$prefix.'%'])->exists();
    }

    private function directoryHasContents(string $directory): bool
    {
        $items = scandir($directory);

        return $items === false || array_diff($items, ['.', '..']) !== [];
    }

    private function displayNameForSegment(string $segment): string
    {
        return $segment === '_da-classificare'
            ? 'Da classificare'
            : Str::headline(str_replace(['_', '-'], ' ', $segment));
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
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
