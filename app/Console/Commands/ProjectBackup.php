<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

#[Signature('project:backup')]
#[Description('Crea un backup completo del database e dei file multimediali')]
class ProjectBackup extends Command
{
    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d-His');

        $userProfile = getenv('USERPROFILE');

        if (! $userProfile) {
            $this->error('Impossibile individuare la cartella personale di Windows.');

            return Command::FAILURE;
        }

        $backupRoot = $userProfile
            .DIRECTORY_SEPARATOR.'Documents'
            .DIRECTORY_SEPARATOR.'QuarkBlog-Backups';

        $backupDirectory = $backupRoot
            .DIRECTORY_SEPARATOR.'quark-blog-'.$timestamp;

        $databaseSource = database_path('database.sqlite');
        $databaseDestination = $backupDirectory
            .DIRECTORY_SEPARATOR.'database.sqlite';

        $mediaSource = storage_path('app/public');
        $mediaDestination = $backupDirectory
            .DIRECTORY_SEPARATOR.'public';

        try {
            $this->info('Avvio backup completo di Quark Blog...');

            // Crea anche il backup locale gestito dal comando esistente.
            $exitCode = Artisan::call('backup:database');

            if ($exitCode !== Command::SUCCESS) {
                throw new RuntimeException(
                    'Il backup locale del database non è riuscito.'
                );
            }

            $this->line(Artisan::output());

            if (! is_dir($backupDirectory)
                && ! mkdir($backupDirectory, 0755, true)
                && ! is_dir($backupDirectory)) {
                throw new RuntimeException(
                    'Impossibile creare la cartella: '.$backupDirectory
                );
            }

            if (! file_exists($databaseSource)) {
                throw new RuntimeException(
                    'Database non trovato: '.$databaseSource
                );
            }

            if (! copy($databaseSource, $databaseDestination)) {
                throw new RuntimeException(
                    'Impossibile copiare il database.'
                );
            }

            if (is_dir($mediaSource)) {
                $this->copyDirectory($mediaSource, $mediaDestination);
            }

            $this->newLine();
            $this->info('Backup completo creato correttamente.');
            $this->line('Cartella: '.$backupDirectory);

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('Backup non riuscito: '.$exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($destination)
            && ! mkdir($destination, 0755, true)
            && ! is_dir($destination)) {
            throw new RuntimeException(
                'Impossibile creare la cartella: '.$destination
            );
        }

        $items = scandir($source);

        if ($items === false) {
            throw new RuntimeException(
                'Impossibile leggere la cartella: '.$source
            );
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source.DIRECTORY_SEPARATOR.$item;
            $destinationPath = $destination.DIRECTORY_SEPARATOR.$item;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);

                continue;
            }

            if (! copy($sourcePath, $destinationPath)) {
                throw new RuntimeException(
                    'Impossibile copiare il file: '.$sourcePath
                );
            }
        }
    }
}