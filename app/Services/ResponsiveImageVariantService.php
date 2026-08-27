<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * FASE 5 (missione S2 responsive images): unico punto che orchestra la
 * generazione, la cancellazione e la risoluzione delle varianti responsive
 * di un'immagine gia' gestita dalla Libreria media — evolve la pipeline
 * esistente (ImageService per il calcolo/scrittura, PublicMediaSyncService
 * per la radice pubblica secondaria), non la duplica.
 *
 * Decisione architetturale deliberata: le varianti NON hanno un proprio
 * record Media. Sono file derivati, il cui nome e' calcolato in modo
 * deterministico da ImageService::responsiveVariantPath() a partire dal
 * disk_name del Media originale — MediaUsageService/MediaReferenceService
 * confrontano per uguaglianza esatta sul disk_name salvato nei campi DB
 * (cover_image, banner_image, ecc.): un ipotetico Media per ogni variante
 * non verrebbe MAI trovato "in uso" da quella scansione (nessun campo DB
 * salva mai "foto-480w.webp") e verrebbe quindi considerato erroneamente
 * orfano dal primo giro di pulizia — esattamente il rischio di orfano
 * permanente che la missione vieta esplicitamente. Restando fuori dal
 * catalogo Media, le varianti sono invece invisibili (mai toccate, mai
 * proposte per la cancellazione) a MediaRetirementService,
 * MediaWebpMigrationService, media:webp-cleanup e alla Libreria media
 * stessa: la loro unica gestione del ciclo di vita passa da questo
 * servizio, chiamato esplicitamente nei punti di cancellazione/sostituzione
 * del file originale (vedi deleteForDiskName()).
 */
class ResponsiveImageVariantService
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly PublicMediaSyncService $publicMediaSync,
    ) {}

    /**
     * Genera le varianti responsive per un file appena caricato/sostituito
     * e le sincronizza sulla radice pubblica secondaria se configurata.
     * Chiamata a valle dello stesso blocco di upload gia' esistente in ogni
     * controller (Libreria media, copertina articolo, immagine categoria),
     * sullo stesso file finale (dopo l'eventuale conversione WebP).
     *
     * Best-effort per costruzione (vedi ImageService::generateResponsiveVariants()):
     * un fallimento qui non deve mai interrompere ne' far fallire l'upload
     * principale, di cui e' solo un'ottimizzazione accessoria.
     *
     * @return list<array{width: int, disk_name: string}>
     */
    public function generateForUpload(string $absolutePath, string $diskName): array
    {
        $widths = config('media.responsive_widths', []);

        if ($widths === []) {
            return [];
        }

        $variants = $this->imageService->generateResponsiveVariants(
            $absolutePath,
            $widths,
            (int) config('media.webp_quality', 82)
        );

        $generated = [];

        foreach ($variants as $variant) {
            $variantDiskName = $this->imageService->responsiveVariantPath($diskName, $variant['width']);

            try {
                $this->publicMediaSync->create($variant['path'], $variantDiskName);
            } catch (RuntimeException $exception) {
                // Best-effort: la variante resta valida e servibile dalla
                // document root primaria anche se la radice secondaria non
                // e' stata raggiunta — stessa tolleranza gia' accordata da
                // MediaRetirementService/MediaWebpMigrationService a questo
                // stesso tipo di fallimento, mai bloccante per un file
                // accessorio.
                Log::warning('ResponsiveImageVariantService: sincronizzazione pubblica secondaria fallita per una variante.', [
                    'disk_name' => $variantDiskName,
                    'error' => $exception->getMessage(),
                ]);
            }

            $generated[] = ['width' => $variant['width'], 'disk_name' => $variantDiskName];
        }

        return $generated;
    }

    /**
     * Cancella tutte le varianti responsive derivabili da un disk_name,
     * best-effort e idempotente (una variante mancante non e' un errore:
     * puo' semplicemente non essere mai stata generata). Va chiamata in
     * OGNI punto in cui il file originale viene rimosso o sostituito
     * (Admin\MediaController::destroy(), MediaRetirementService::retireIfUnused()),
     * cosi' che una variante non sopravviva mai al proprio originale come
     * orfano permanente.
     *
     * Ricalcola i percorsi dallo stesso schema deterministico usato in
     * generazione (nessun registro separato da consultare): usa
     * config('media.responsive_widths') come limite superiore dei target
     * possibili, ma cancella anche varianti generate quando la config
     * aveva larghezze diverse da quelle attuali, scansionando la
     * directory per il pattern "{base}-{N}w.{ext}" — cosi' un cambio della
     * config nel tempo non lascia comunque orfani.
     */
    public function deleteForDiskName(string $diskName): void
    {
        // I disk_name correnti sono sempre POSIX-style, ma record legacy o
        // import eseguiti su Windows possono contenere backslash. La
        // generazione li normalizza gia' tramite ImageService; la pulizia
        // deve applicare la stessa regola o lascerebbe varianti orfane.
        $diskName = str_replace('\\', '/', $diskName);

        // Never change a stored media identity silently. Whitespace/control
        // characters are invalid here: reject them instead of trimming into
        // the name of a different media item.
        if ($diskName !== trim($diskName) || preg_match('/[\\x00-\\x1F\\x7F]/', $diskName) === 1) {
            return;
        }

        $diskName = ltrim($diskName, '/');

        // Questo servizio gestisce esclusivamente nomi relativi sotto
        // public/assets/img. Non consentire mai che una scansione derivata
        // da un dato anomalo risalga fuori dalla media root.
        if ($diskName === '' || in_array('..', explode('/', $diskName), true)) {
            return;
        }

        $primaryRoot = public_path('assets/img');
        $dir = dirname($diskName);
        $base = pathinfo($diskName, PATHINFO_FILENAME);
        $ext = pathinfo($diskName, PATHINFO_EXTENSION);
        $prefix = ($dir === '.' || $dir === '') ? '' : $dir.'/';

        $absoluteDir = $primaryRoot.'/'.($dir === '.' ? '' : $dir);

        if (! is_dir($absoluteDir)) {
            return;
        }

        $pattern = preg_quote($base, '/').'-(\d+)w\.'.preg_quote($ext, '/');

        foreach (scandir($absoluteDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (! preg_match('/^'.$pattern.'$/', $entry)) {
                continue;
            }

            $variantDiskName = $prefix.$entry;

            try {
                $this->publicMediaSync->delete($variantDiskName);
            } catch (RuntimeException $exception) {
                Log::warning('ResponsiveImageVariantService: pulizia variante non riuscita nella directory pubblica secondaria.', [
                    'disk_name' => $variantDiskName,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            $absolutePath = $absoluteDir.'/'.$entry;
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    /**
     * Risolve, per un dato disk_name, l'elenco delle varianti REALMENTE
     * presenti e valide sul filesystem in QUESTO momento — mai una lista
     * calcolata solo dalla config, cosi' un'immagine gia' in produzione
     * senza varianti (legacy, o generazione fallita) continua a funzionare
     * col solo src originale, senza alcuna migrazione distruttiva
     * necessaria (requisito esplicito della missione). Usata dal
     * componente Blade <x-responsive-image> per costruire srcset.
     *
     * @return list<array{width: int, url: string}> Ordinato per larghezza
     *                                              crescente, SENZA il
     *                                              file originale (lo
     *                                              aggiunge il chiamante,
     *                                              che ne conosce gia'
     *                                              l'URL e la larghezza
     *                                              reale).
     */
    public function existingVariantsFor(string $diskName): array
    {
        $widths = config('media.responsive_widths', []);
        $primaryRoot = public_path('assets/img');
        $found = [];

        foreach ($widths as $width) {
            $variantDiskName = $this->imageService->responsiveVariantPath($diskName, $width);
            $absolutePath = $primaryRoot.'/'.$variantDiskName;

            if (! is_file($absolutePath)) {
                continue;
            }

            $found[] = ['width' => $width, 'url' => asset('assets/img/'.$variantDiskName)];
        }

        usort($found, fn ($a, $b) => $a['width'] <=> $b['width']);

        return $found;
    }

    /**
     * Punto di risoluzione unico per il componente Blade
     * <x-responsive-image>: dato un disk_name della Libreria media,
     * restituisce src/srcset/width/height gia' pronti per il markup.
     * Nessuna elaborazione immagine qui (solo file_exists/getimagesize in
     * lettura): mai un costo GD durante una GET pubblica normale.
     *
     * Fallback legacy esplicito (requisito della missione): se il file
     * originale manca o non e' un'immagine leggibile, o non esiste ancora
     * nessuna variante, restituisce comunque un src valido con srcset
     * vuoto — l'immagine gia' in produzione continua a funzionare
     * esattamente come prima, senza alcuna migrazione necessaria.
     *
     * @return array{src: string, srcset: string, width: ?int, height: ?int}
     */
    public function resolveForMarkup(string $diskName): array
    {
        $originalUrl = asset('assets/img/'.$diskName);
        $absoluteOriginal = public_path('assets/img/'.$diskName);

        $info = is_file($absoluteOriginal) ? @getimagesize($absoluteOriginal) : false;
        $originalWidth = $info !== false ? $info[0] : null;
        $originalHeight = $info !== false ? $info[1] : null;

        $variants = $originalWidth !== null ? $this->existingVariantsFor($diskName) : [];

        $candidates = $variants;
        if ($originalWidth !== null) {
            $candidates[] = ['width' => $originalWidth, 'url' => $originalUrl];
        }

        $srcset = implode(', ', array_map(
            fn ($c) => $c['url'].' '.$c['width'].'w',
            $candidates
        ));

        return [
            'src' => $originalUrl,
            'srcset' => $srcset,
            'width' => $originalWidth,
            'height' => $originalHeight,
        ];
    }
}
