<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Concerns\ResolvesMediaFileSafely;
use App\Services\ImageService;
use App\Services\ResponsiveImageVariantService;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateResponsiveImages extends Command
{
    use ResolvesMediaFileSafely;

    protected $signature = 'media:generate-responsive
        {--execute : Genera davvero le varianti (default: sola analisi/dry-run, nessuna scrittura)}
        {--media-id=* : Limita l\'operazione a uno o piu ID Media specifici}
        {--limit= : Numero massimo di Media da processare in questa esecuzione}
        {--chunk=200 : Dimensione del blocco per l\'elaborazione (query e memoria limitate)}
        {--force : Salta la conferma interattiva prima di eseguire}';

    protected $description = 'Genera le varianti responsive (missione S2) per i media editoriali gia esistenti, senza toccare gli originali (default: sola analisi)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        FASE 6 della missione S2 (responsive images): un'immagine gia'
        presente in produzione, caricata PRIMA di questa missione, non ha
        ancora le varianti responsive generate da ResponsiveImageVariantService
        (quelle si generano automaticamente solo per i NUOVI upload — vedi
        Admin\MediaController::store(), le copertine articolo, l'immagine
        categoria). Senza questo comando, il catalogo esistente non
        beneficerebbe mai della missione finche' ogni singolo file non
        venisse ricaricato a mano.

        Non tocca MAI l'originale: crea soltanto file WebP aggiuntivi
        accanto ad esso (stessa convenzione di naming deterministica usata
        ovunque nella missione — "{base}-{N}w.webp"). Riusa integralmente
        ImageService::generateResponsiveVariants() e
        ResponsiveImageVariantService::generateForUpload() — nessuna nuova
        logica di generazione qui, solo l'orchestrazione batch.

        Modalita dry-run (default)
        ---------------------------
        Senza --execute il comando e' in sola lettura: per ogni Media
        determina quali larghezze target (config('media.responsive_widths'))
        mancano ancora sul filesystem, senza scrivere nulla.

        Modalita --execute
        -------------------
        Genera davvero le varianti mancanti, un Media alla volta. Un
        fallimento su un singolo Media (file sorgente mancante o
        corrotto, encoder assente) viene registrato e NON interrompe gli
        altri: non esiste una transazione unica su tutto il batch, ne'
        alcuna scrittura sul database (le varianti non hanno un proprio
        record Media — vedi la classdoc di ResponsiveImageVariantService).

        Idempotente: una seconda esecuzione (dry-run o --execute) su media
        gia' processati non rigenera nulla (isValidExistingWebp() nel
        motore sottostante) e non fallisce.

        Elaborazione a blocchi (--chunk, default 200): query e uso di
        memoria restano piatti anche su un catalogo di decine di migliaia
        di Media (vedi i test di performance dedicati). Mai eseguito su
        produzione da questo agente.

        Opzioni
        -------
        --execute     Genera davvero le varianti (default: sola analisi)
        --media-id=   Limita a uno o piu ID Media (ripetibile)
        --limit=      Numero massimo di Media processati in questa esecuzione
        --chunk=      Dimensione del blocco di elaborazione (default 200)
        --force       Salta la conferma interattiva

        Exit code
        ---------
        0: esecuzione completata senza errori tecnici (un file sorgente
           mancante/corrotto e' registrato ma non e' un errore tecnico
           bloccante). Diverso da 0: almeno un errore tecnico inatteso.
        HELP;

    public function handle(ImageService $imageService, ResponsiveImageVariantService $responsiveImageVariants): int
    {
        $execute = (bool) $this->option('execute');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $chunkSize = max(1, (int) $this->option('chunk'));
        $widths = config('media.responsive_widths', []);

        if ($widths === []) {
            $this->warn('config(\'media.responsive_widths\') e\' vuota: nessuna larghezza da generare. Niente da fare.');

            return self::SUCCESS;
        }

        $this->info($execute ? 'Modalita: GENERA varianti responsive' : 'Modalita: sola analisi (dry-run)');
        $this->line('(gli originali non vengono mai modificati da questo comando)');
        $this->newLine();

        $query = Media::query()->images()->orderBy('id');

        $mediaIds = array_map('intval', (array) $this->option('media-id'));
        if ($mediaIds !== []) {
            $query->whereIn('id', $mediaIds);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nessun media immagine da valutare con i filtri indicati.');

            return self::SUCCESS;
        }

        if ($execute && ! $this->option('force') && $this->input->isInteractive()) {
            if (! $this->confirm("Procedere con la generazione delle varianti responsive per fino a {$total} media?", false)) {
                $this->warn('Esecuzione annullata dall\'utente. Nessuna variante generata.');

                return self::SUCCESS;
            }
        }

        $processed = 0;
        $plannedCount = 0;
        $generatedVariantCount = 0;
        $upToDateCount = 0;
        $skippedGifCount = 0;
        $missingSourceCount = 0;
        $technicalErrors = 0;
        $processedInThisRun = 0;

        $root = public_path('assets/img');

        $query->chunkById($chunkSize, function ($mediaChunk) use (
            $imageService,
            $responsiveImageVariants,
            $widths,
            $execute,
            $root,
            $limit,
            &$processed,
            &$plannedCount,
            &$generatedVariantCount,
            &$upToDateCount,
            &$skippedGifCount,
            &$missingSourceCount,
            &$technicalErrors,
            &$processedInThisRun,
        ) {
            foreach ($mediaChunk as $media) {
                if ($limit !== null && $processedInThisRun >= $limit) {
                    return false;
                }

                $processedInThisRun++;
                $processed++;

                try {
                    $absolutePath = $this->safeExistingFilePath($root, $media->disk_name);
                } catch (RuntimeException $exception) {
                    $missingSourceCount++;
                    $this->line("  #{$media->id} {$media->disk_name} -> sorgente mancante o non valido ({$exception->getMessage()}), saltato.");

                    continue;
                }

                if (strtolower(pathinfo($media->disk_name, PATHINFO_EXTENSION)) === 'gif') {
                    $skippedGifCount++;

                    continue;
                }

                $imageInfo = @getimagesize($absolutePath);
                if ($imageInfo === false || ($imageInfo[0] ?? 0) <= 0) {
                    $missingSourceCount++;
                    $this->line("  #{$media->id} {$media->disk_name} -> dimensioni sorgente non leggibili, saltato.");

                    continue;
                }

                // Lo stesso contratto applicato da ImageService: una
                // larghezza uguale o superiore all'originale non e' una
                // variante mancante, ma un target non applicabile. In
                // particolare, un originale da 800px richiede 480w ma non
                // 960w; il dry-run non deve suggerire un upscale che il
                // generatore rifiutera' correttamente.
                $applicableWidths = array_values(array_filter(
                    $widths,
                    fn (int $width) => $width > 0 && $width < (int) $imageInfo[0]
                ));

                $missingWidths = [];
                foreach ($applicableWidths as $width) {
                    $variantPath = $root.'/'.$imageService->responsiveVariantPath($media->disk_name, $width);
                    if (! is_file($variantPath)) {
                        $missingWidths[] = $width;
                    }
                }

                if ($missingWidths === []) {
                    $upToDateCount++;

                    continue;
                }

                $plannedCount++;

                if (! $execute) {
                    $this->line("  #{$media->id} {$media->disk_name} -> mancano: ".implode(', ', array_map(fn ($w) => $w.'w', $missingWidths)));

                    continue;
                }

                try {
                    $generated = $responsiveImageVariants->generateForUpload($absolutePath, $media->disk_name);
                    $generatedVariantCount += count($generated);

                    if (count($generated) < count($missingWidths)) {
                        $this->line("  #{$media->id} {$media->disk_name} -> generate ".count($generated).' variante/i su '.count($missingWidths).' mancanti (vedi log per il dettaglio).');
                    } else {
                        $this->line("  #{$media->id} {$media->disk_name} -> generate ".count($generated).' variante/i.');
                    }
                } catch (\Throwable $exception) {
                    $technicalErrors++;
                    $this->error("  #{$media->id} {$media->disk_name} -> errore tecnico: ".$exception->getMessage());
                }
            }

            return $limit === null || $processedInThisRun < $limit;
        });

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Riepilogo</>');
        $this->table(['Metrica', 'Conteggio'], [
            ['Media valutati', $processed],
            ['Gia aggiornati (nessuna variante mancante)', $upToDateCount],
            ['GIF escluse', $skippedGifCount],
            ['Sorgente mancante/non valida', $missingSourceCount],
            [$execute ? 'Con varianti generate in questa esecuzione' : 'Con varianti mancanti (pianificati)', $plannedCount],
            ...($execute ? [['Varianti scritte in totale', $generatedVariantCount]] : []),
            ...($execute ? [['Errori tecnici', $technicalErrors]] : []),
        ]);

        if (! $execute) {
            $this->newLine();
            $this->info('Dry-run completata: nessuna scrittura effettuata. Usa --execute per generare le varianti mancanti.');
        }

        return $technicalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
