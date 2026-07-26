<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;

class RealignMediaMimeTypes extends Command
{
    protected $signature = 'media:realign-mime
        {--dry-run : Mostra le incoerenze senza scrivere nel database}';

    protected $description = 'Riallinea il campo mime_type dei media registrati al contenuto reale dei file su disco';

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        $counts = [
            'scanned' => 0,
            'missing' => 0,
            'unreadable_mime' => 0,
            'already_correct' => 0,
            'mismatched' => 0,
            'updated' => 0,
        ];

        Media::orderBy('id')->chunkById(100, function ($chunk) use ($fileInfo, $dryRun, &$counts) {
            foreach ($chunk as $media) {
                $counts['scanned']++;
                $this->processMedia($media, $fileInfo, $dryRun, $counts);
            }
        });

        $this->newLine();
        $this->info('Riepilogo riallineamento MIME');
        $this->line('Record analizzati: '.$counts['scanned']);
        $this->line('File mancanti su disco: '.$counts['missing']);
        $this->line('MIME reale non rilevabile/non consentito: '.$counts['unreadable_mime']);
        $this->line('Gia coerenti: '.$counts['already_correct']);
        $this->line('Incoerenti: '.$counts['mismatched']);
        $this->line(($dryRun ? 'Da aggiornare (dry-run): ' : 'Aggiornati: ').$counts['updated']);

        return Command::SUCCESS;
    }

    /**
     * @param  array{scanned: int, missing: int, unreadable_mime: int, already_correct: int, mismatched: int, updated: int}  $counts
     */
    private function processMedia(Media $media, \finfo $fileInfo, bool $dryRun, array &$counts): void
    {
        $realPath = public_path('assets/img/'.$media->disk_name);

        if (! is_file($realPath)) {
            $counts['missing']++;
            $this->warn('FILE MANCANTE: '.$media->disk_name.' (id '.$media->id.')');

            return;
        }

        $detectedMime = $fileInfo->file($realPath);

        if (! is_string($detectedMime) || ! in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            $counts['unreadable_mime']++;
            $this->warn('MIME reale non rilevabile o non consentito: '.$media->disk_name.' (id '.$media->id.')');

            return;
        }

        if ($detectedMime === $media->mime_type) {
            $counts['already_correct']++;

            return;
        }

        $counts['mismatched']++;
        $counts['updated']++;

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->line($prefix.'INCOERENTE '.$media->disk_name.' (id '.$media->id.'): '.$media->mime_type.' -> '.$detectedMime);

        if (! $dryRun) {
            $media->update(['mime_type' => $detectedMime]);
        }
    }
}
