<?php

namespace App\Services;

use App\Models\Media;
use App\Services\Concerns\DetectsAbsolutePaths;
use App\Services\Concerns\WalksMediaDirectorySafely;

/**
 * FASE 12 della missione WebP: sola lettura, elenca ESCLUSIVAMENTE i file
 * JPG/PNG che sono "originali lasciati indietro" dalla migrazione di
 * media:convert-webp — mai un generico "file non referenziato da nulla"
 * (che non equivale a "eliminabile", vedi docs/EDITORIAL_MEDIA_WEBP.md).
 *
 * Un file e' un candidato SOLO se tutte queste condizioni sono vere:
 * - e' un JPG/PNG sotto public/assets/img;
 * - esiste un Media il cui disk_name e' la sua controparte .webp (quindi
 *   e' stato davvero migrato da questa pipeline, non un file mai toccato);
 * - quel file .webp esiste realmente su disco ed e' un'immagine valida
 *   (mai suggerire di rimuovere un originale se il suo sostituto e'
 *   mancante o corrotto: sarebbe l'errore piu' pericoloso possibile qui);
 * - nessun Media ha ancora il disk_name dell'originale stesso;
 * - non e' nella lista protetta (config('media.protected_disk_names'));
 * - non e' sotto turing/ (nessun record Media da cui dedurre l'uso, stessa
 *   esclusione categorica di media:webp-audit e media:convert-webp);
 * - nessuna menzione in testo libero (article.body, ad.html_code — riusa
 *   MediaReferenceService::findFreeTextMentions(), mai una seconda
 *   definizione di "potrebbe essere nascosto in un campo di testo");
 * - nessuna menzione letterale nel codice sorgente versionato
 *   (resources/views, resources/css, resources/js, public/css, public/js);
 * - e' trascorso il periodo di osservazione minimo dalla migrazione
 *   (config('media.webp_cleanup_min_age_days'), basato su Media.updated_at
 *   del record WebP: e' l'unico timestamp affidabile di "quando e' stata
 *   applicata la migrazione" gia' disponibile, senza introdurre una nuova
 *   colonna).
 *
 * La conferma di un backup locale NON e' verificabile da questo comando
 * (nessuna integrazione di backup esiste nel progetto): resta sempre un
 * passo umano, esplicito, riportato nell'output come promemoria — mai
 * automaticamente assunto vero.
 *
 * Nessun --force, nessuna cancellazione, in questa missione: SOLO
 * l'elenco dei candidati.
 */
class MediaWebpCleanupService
{
    use DetectsAbsolutePaths;
    use WalksMediaDirectorySafely;

    private const SOURCE_SCAN_EXTENSIONS = ['blade.php', 'css', 'js'];

    /**
     * Da config, non un const: cosi' i test possono puntare la scansione
     * a una directory temporanea isolata invece dell'albero resources/
     * reale del repository (config('media.webp_cleanup_source_directories'),
     * stesso principio gia' usato da protected_disk_names).
     *
     * @return list<string>
     */
    private function sourceScanDirectories(): array
    {
        return config('media.webp_cleanup_source_directories', [
            'resources/views',
            'resources/css',
            'resources/js',
            'public/css',
            'public/js',
        ]);
    }

    public function __construct(
        private readonly MediaReferenceService $referenceService,
        private readonly ImageService $imageService,
        private readonly PublicMediaSyncService $publicMediaSync,
    ) {}

    /**
     * @param  array{path?: ?string, minAgeDays?: int}  $options
     * @return array<string, mixed>
     */
    public function scan(array $options = []): array
    {
        $pathFilter = $options['path'] ?? null;
        $minAgeDays = $options['minAgeDays'] ?? (int) config('media.webp_cleanup_min_age_days', 14);

        $baseDir = public_path('assets/img');
        $allFiles = $this->walkDirectory($baseDir);

        $normalizedPath = $pathFilter !== null ? trim($pathFilter, '/') : null;
        $files = array_values(array_filter(
            $allFiles,
            fn (array $f) => in_array($f['extension'], ['jpg', 'jpeg', 'png'], true)
                && ($normalizedPath === null
                    || $f['relative_path'] === $normalizedPath
                    || str_starts_with($f['relative_path'], $normalizedPath.'/'))
        ));

        $candidates = [];
        $excluded = [
            'not_migrated' => [],
            'still_referenced_by_media' => [],
            'protected' => [],
            'turing_unmanaged' => [],
            'webp_missing_or_invalid' => [],
            'webp_missing_in_secondary_root' => [],
            'structured_reference_without_media' => [],
            'free_text_reference' => [],
            'static_source_reference' => [],
            'observation_period_not_elapsed' => [],
        ];

        $preScreened = [];

        foreach ($files as $file) {
            $relativePath = $file['relative_path'];
            $webpDiskName = $this->imageService->changeExtension($relativePath, 'webp');

            if (str_starts_with($relativePath, 'turing/')) {
                $excluded['turing_unmanaged'][] = $this->excludedEntry($file, 'Pagine speciali Turing: nessun record Media, esclusa categoricamente (stessa regola di media:webp-audit).');

                continue;
            }

            if (in_array($relativePath, config('media.protected_disk_names', []), true)) {
                $excluded['protected'][] = $this->excludedEntry($file, 'Riferimento statico protetto in config/media.php.');

                continue;
            }

            if (Media::where('disk_name', $relativePath)->exists()) {
                $excluded['still_referenced_by_media'][] = $this->excludedEntry($file, 'Un Media punta ancora a questo esatto file: non e\' un residuo di migrazione.');

                continue;
            }

            $webpMedia = Media::where('disk_name', $webpDiskName)->first();

            if ($webpMedia === null) {
                $excluded['not_migrated'][] = $this->excludedEntry($file, "Nessun Media punta a '{$webpDiskName}': questo originale non risulta migrato da media:convert-webp (potrebbe non essere mai stato candidato, o essere un file indipendente).");

                continue;
            }

            $webpAbsolute = $baseDir.'/'.$webpDiskName;
            $webpImageInfo = is_file($webpAbsolute) ? @getimagesize($webpAbsolute) : false;

            // getimagesize() valida "e' un'immagine decodificabile", non
            // "e' davvero WebP": un file rinominato/corrotto che contiene
            // bytes JPEG/PNG validi supererebbe un controllo piu' debole.
            // Il tipo rilevato (indice [2]) deve essere esattamente
            // IMAGETYPE_WEBP, altrimenti il sostituto non e' affidabile.
            if ($webpImageInfo === false || $webpImageInfo[2] !== IMAGETYPE_WEBP) {
                $excluded['webp_missing_or_invalid'][] = $this->excludedEntry($file, "Il Media punta a '{$webpDiskName}' ma il file non esiste sul filesystem o non e' realmente un'immagine WebP valida: richiede indagine manuale, l'originale NON deve essere considerato eliminabile finche' questo non e' risolto.");

                continue;
            }

            // Quando MEDIA_PUBLIC_ROOT e' configurato, la copia realmente
            // servita al pubblico puo' essere quella radice secondaria
            // (es. public_html su cPanel), non solo public/assets/img:
            // un originale non deve mai essere considerato eliminabile se
            // quella copia manca, anche se la radice primaria e' a posto
            // (drift di sincronizzazione, es. copia creata prima
            // dell'attivazione di MEDIA_PUBLIC_ROOT).
            if ($this->publicMediaSync->isEnabled() && ! $this->publicMediaSync->targetIsFile($webpDiskName)) {
                $excluded['webp_missing_in_secondary_root'][] = $this->excludedEntry($file, "Il WebP '{$webpDiskName}' e' valido nella radice primaria ma manca nella radice pubblica secondaria configurata (MEDIA_PUBLIC_ROOT): rimuovere l'originale potrebbe lasciare il sito senza un sostituto realmente servito.");

                continue;
            }

            // Un confronto per solo nome file tra originale e la sua
            // ipotetica controparte WebP non prova che quel WebP sia
            // stato prodotto DA quell'originale (potrebbero coesistere
            // per puro caso di stesso basename in file diversi): un
            // riferimento strutturato residuo al nome dell'originale
            // stesso, anche senza un Media proprietario, deve bloccare
            // la candidatura esattamente come una menzione in testo
            // libero.
            if ($this->referenceService->hasAnyStructuredReference($relativePath)) {
                $excluded['structured_reference_without_media'][] = $this->excludedEntry(
                    $file,
                    'Un campo strutturato (copertina articolo, banner, foto profilo, immagine categoria o contenuto pagina speciale) fa ancora riferimento a questo file, pur non esistendo un Media che lo possiede: potrebbe non essere davvero un residuo di QUESTA migrazione.'
                );

                continue;
            }

            $mentions = array_merge(
                $this->referenceService->findFreeTextMentions($relativePath),
                $this->referenceService->findFreeTextMentions(basename($relativePath))
            );

            if ($mentions !== []) {
                $excluded['free_text_reference'][] = $this->excludedEntry(
                    $file,
                    'Menzionato in un campo di testo libero: '.implode(', ', array_map(fn (array $m) => $m['description'], $mentions))
                );

                continue;
            }

            $migratedAt = $webpMedia->updated_at;
            $ageDays = $migratedAt !== null ? $migratedAt->diffInDays(now()) : null;

            $preScreened[] = [
                'file' => $file,
                'webp_disk_name' => $webpDiskName,
                'webp_media_id' => $webpMedia->id,
                'migrated_at' => $migratedAt?->toIso8601String(),
                'age_days' => $ageDays,
            ];
        }

        // Scansione del codice sorgente in un solo passaggio su tutti i
        // file rilevanti, non un passaggio per candidato: con N candidati
        // e M file sorgente il costo resta O(M), non O(N*M).
        $staticMatches = $this->findStaticSourceMatches(array_map(
            fn (array $entry) => $entry['file']['relative_path'],
            $preScreened
        ));

        foreach ($preScreened as $entry) {
            $file = $entry['file'];
            $relativePath = $file['relative_path'];

            if (isset($staticMatches[$relativePath])) {
                $excluded['static_source_reference'][] = $this->excludedEntry(
                    $file,
                    'Menzione letterale trovata nel codice sorgente versionato: '.implode(', ', $staticMatches[$relativePath])
                );

                continue;
            }

            if ($entry['age_days'] === null || $entry['age_days'] < $minAgeDays) {
                $excluded['observation_period_not_elapsed'][] = $this->excludedEntry(
                    $file,
                    $entry['age_days'] === null
                        ? 'Data di migrazione non disponibile: periodo di osservazione non verificabile.'
                        : "Migrato {$entry['age_days']} giorni fa, sotto la soglia minima di {$minAgeDays} giorni."
                );

                continue;
            }

            $candidates[] = [
                'relative_path' => $relativePath,
                'size_bytes' => $file['size_bytes'],
                'webp_disk_name' => $entry['webp_disk_name'],
                'webp_media_id' => $entry['webp_media_id'],
                'migrated_at' => $entry['migrated_at'],
                'age_days' => $entry['age_days'],
            ];
        }

        return [
            'scanned_count' => count($files),
            'min_age_days' => $minAgeDays,
            'candidates' => [
                'count' => count($candidates),
                'total_bytes' => array_sum(array_column($candidates, 'size_bytes')),
                'files' => $candidates,
            ],
            'excluded' => array_map(fn (array $list) => [
                'count' => count($list),
                'files' => $list,
            ], $excluded),
            'backup_confirmation_required' => true,
        ];
    }

    /**
     * @param  array{relative_path: string, absolute_path: string, size_bytes: int, extension: string}  $file
     * @return array{relative_path: string, size_bytes: int, reason: string}
     */
    private function excludedEntry(array $file, string $reason): array
    {
        return [
            'relative_path' => $file['relative_path'],
            'size_bytes' => $file['size_bytes'],
            'reason' => $reason,
        ];
    }

    /**
     * Cerca ogni $relativePath (per basename, l'unico confronto sensato
     * contro codice sorgente: Blade/CSS/JS referenziano quasi sempre solo
     * il nome file, mai il path assoluto del filesystem) come sottostringa
     * letterale in ogni file versionato sotto le directory sorgente
     * rilevanti. Un solo passaggio sui file sorgente per tutti i
     * candidati insieme.
     *
     * @param  list<string>  $relativePaths
     * @return array<string, list<string>> relative_path => elenco dei file sorgente che lo menzionano
     */
    private function findStaticSourceMatches(array $relativePaths): array
    {
        if ($relativePaths === []) {
            return [];
        }

        $needles = [];
        foreach ($relativePaths as $relativePath) {
            $needles[$relativePath] = basename($relativePath);
        }

        $matches = [];

        foreach ($this->sourceScanDirectories() as $directory) {
            $absoluteDirectory = $this->isAbsolutePath($directory) ? $directory : base_path($directory);

            if (! is_dir($absoluteDirectory)) {
                continue;
            }

            foreach ($this->sourceFilesUnder($absoluteDirectory) as $sourceFile) {
                $content = @file_get_contents($sourceFile);

                if ($content === false) {
                    continue;
                }

                foreach ($needles as $relativePath => $basename) {
                    if (str_contains($content, $basename)) {
                        $matches[$relativePath][] = $this->relativeToBase($sourceFile);
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    private function sourceFilesUnder(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $name = $fileInfo->getFilename();

            foreach (self::SOURCE_SCAN_EXTENSIONS as $extension) {
                if (str_ends_with($name, '.'.$extension)) {
                    $files[] = $fileInfo->getPathname();

                    break;
                }
            }
        }

        return $files;
    }

    private function relativeToBase(string $absolutePath): string
    {
        $base = base_path();

        return str_starts_with($absolutePath, $base)
            ? ltrim(substr($absolutePath, strlen($base)), '/\\')
            : $absolutePath;
    }
}
