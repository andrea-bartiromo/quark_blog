<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\MediaFolderService;
use Illuminate\Console\Command;
use Throwable;

class SyncMediaFolders extends Command
{
    protected $signature = 'media:sync-folders {--dry-run : Mostra le categorie mancanti senza scrivere}';

    protected $description = 'Sincronizza le categorie immagini implicite nei Media.disk_name';

    public function handle(MediaFolderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = [
            'media' => 0,
            'with_directories' => 0,
            'existing' => 0,
            'would_create' => 0,
            'created' => 0,
            'invalid' => 0,
            'errors' => 0,
        ];
        $seenPaths = [];

        Media::query()->orderBy('id')->each(function (Media $media) use ($service, $dryRun, &$counts, &$seenPaths): void {
            $counts['media']++;

            try {
                $paths = $service->folderPathsForDiskName($media->disk_name);
                if ($paths === []) {
                    return;
                }

                $counts['with_directories']++;

                foreach ($paths as $path) {
                    if (isset($seenPaths[$path])) {
                        continue;
                    }
                    $seenPaths[$path] = true;

                    if (MediaFolder::where('path', $path)->exists()) {
                        $counts['existing']++;
                    } elseif ($dryRun) {
                        $counts['would_create']++;
                        $this->line('[DRY-RUN] DA CREARE '.$path);
                    }
                }

                if (! $dryRun) {
                    $before = MediaFolder::count();
                    $service->ensureHierarchyForDiskName($media->disk_name);
                    $counts['created'] += MediaFolder::count() - $before;
                }
            } catch (\DomainException $exception) {
                $counts['invalid']++;
                $this->warn('PERCORSO NON VALIDO '.$media->disk_name.': '.$exception->getMessage());
            } catch (Throwable $exception) {
                $counts['errors']++;
                $this->warn('ERRORE '.$media->disk_name.': '.$exception->getMessage());
            }
        });

        $this->newLine();
        $this->info('Riepilogo sincronizzazione categorie immagini');
        $this->line('Media analizzati: '.$counts['media']);
        $this->line('Percorsi con directory: '.$counts['with_directories']);
        $this->line('Categorie già esistenti: '.$counts['existing']);
        if ($dryRun) {
            $this->line('Categorie da creare: '.$counts['would_create']);
        }
        $this->line('Categorie create: '.$counts['created']);
        $this->line('Percorsi non validi: '.$counts['invalid']);
        $this->line('Errori: '.$counts['errors']);

        return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
