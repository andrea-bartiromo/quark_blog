<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ImageService
{
    /**
     * Determina l'estensione sicura a partire dal MIME type
     * rilevato dal server, senza fidarsi del nome originale.
     */
    public function safeExtension(
        UploadedFile $file,
        bool $allowGif = false
    ): string {
        $mimeType = strtolower((string) $file->getMimeType());

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if ($allowGif) {
            $extensions['image/gif'] = 'gif';
        }

        if (! array_key_exists($mimeType, $extensions)) {
            throw new InvalidArgumentException(
                'Formato immagine non consentito: '.$mimeType
            );
        }

        return $extensions[$mimeType];
    }

    /**
     * Costruisce un nome file sicuro.
     *
     * L'estensione deve essere ottenuta tramite safeExtension().
     */
    public function buildFileName(
        UploadedFile $file,
        string $extension,
        string $suffix
    ): string {
        $originalBaseName = pathinfo(
            $file->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $baseName = Str::slug($originalBaseName);

        if ($baseName === '') {
            $baseName = 'immagine';
        }

        $extension = strtolower(trim($extension));
        $suffix = Str::slug($suffix);

        if ($suffix === '') {
            $suffix = now()->format('YmdHis').'-'.Str::random(6);
        }

        return $baseName.'-'.$suffix.'.'.$extension;
    }

    /**
     * Normalizza un path a separatori "/" uniformi.
     *
     * Contratto: ImageService restituisce e accetta SEMPRE path con "/"
     * (mai DIRECTORY_SEPARATOR nativo) — sia per i path filesystem
     * (upload/ensureDirectoryExists) sia, per costruzione, per qualunque
     * cosa derivata da essi altrove nel progetto (disk_name/URL pubblici
     * usano già solo "/" tramite gli helper asset()/url() di Laravel).
     * "/" è accettato da tutte le API filesystem di PHP anche su Windows
     * (fopen, is_file, is_dir, mkdir, GD, ecc. passano attraverso lo
     * strato di stream wrapper che lo risolve correttamente), quindi non
     * serve mai costruire un path nativo con backslash.
     *
     * Senza questa normalizzazione, un chiamante che passa un sotto-path
     * con "/" hardcoded (es. public_path('assets/img')) combinato con
     * DIRECTORY_SEPARATOR su Windows produceva un path con separatori
     * misti (es. "...\assets/img\nome-file.jpg"): stringhe così non sono
     * mai confrontabili in modo affidabile con il path realmente usato
     * per scrivere il file, il che rompeva sia gli assert dei test sia il
     * cleanup basato su unlink() dopo un fallimento di sync (vedi
     * PublicMediaSyncService).
     */
    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return rtrim($normalized, '/');
    }

    /**
     * Crea la directory di destinazione, se non esiste.
     */
    public function ensureDirectoryExists(
        string $path,
        int $permissions = 0755
    ): void {
        $path = $this->normalizePath($path);

        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, $permissions, true) && ! is_dir($path)) {
            throw new RuntimeException(
                'Impossibile creare la directory: '.$path
            );
        }
    }

    /**
     * Salva il file nella directory indicata.
     */
    public function upload(
        UploadedFile $file,
        string $destinationPath,
        string $fileName
    ): string {
        $destinationPath = $this->normalizePath($destinationPath);

        $this->ensureDirectoryExists($destinationPath);

        $file->move($destinationPath, $fileName);

        $fullPath = $destinationPath.'/'.$fileName;

        // DIAGNOSTICA TEMPORANEA (vedi PublicMediaSyncService::logPathDiagnostics):
        // registra il path esatto restituito da questo metodo, cosi' da
        // poterlo confrontare — a partire dai log di una vera esecuzione
        // Windows — con quello effettivamente ricevuto piu' avanti da
        // PublicMediaSyncService::cleanupAfterFailedCreate(), per escludere
        // (o confermare) una qualunque ricostruzione/alterazione del path
        // nel tragitto tra i due punti.
        Log::debug('ImageService: diagnostica — path restituito da upload().', [
            'checkpoint' => 'image_service:upload:returned',
            'destination_path' => $destinationPath,
            'file_name' => $fileName,
            'full_path' => $fullPath,
            'file_exists' => file_exists($fullPath),
            'is_file' => is_file($fullPath),
            'realpath' => realpath($fullPath),
        ]);

        if (! is_file($fullPath)) {
            throw new RuntimeException(
                'Il caricamento dell’immagine non è riuscito.'
            );
        }

        return $fullPath;
    }

    /**
     * Ridimensiona e comprime un'immagine salvata su disco tramite GD.
     *
     * @param array{
     *     jpg?: int,
     *     png?: int,
     *     webp?: int
     * } $quality
     */
    public function resizeAndCompress(
        string $fullPath,
        string $ext,
        int $maxWidth,
        array $quality,
        bool $preserveTransparency = false,
        bool $alwaysReencode = false,
        bool $logErrors = false
    ): void {
        if (! extension_loaded('gd') || ! is_file($fullPath)) {
            return;
        }

        $ext = $this->normalizeExtension($ext);

        /*
         * Le GIF sono ammesse solo dove esplicitamente previsto,
         * ma non vengono elaborate da GD per evitare perdita
         * dell'animazione o conversioni involontarie.
         */
        if ($ext === 'gif') {
            return;
        }

        try {
            $imageInfo = @getimagesize($fullPath);

            if ($imageInfo === false) {
                throw new RuntimeException(
                    'Il file caricato non contiene un’immagine valida.'
                );
            }

            [$width, $height] = $imageInfo;

            if ($width <= 0 || $height <= 0) {
                throw new RuntimeException(
                    'Dimensioni dell’immagine non valide.'
                );
            }

            if ($width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = max(
                    1,
                    (int) round($height * ($maxWidth / $width))
                );

                $source = $this->createImageResource(
                    $fullPath,
                    $ext
                );

                if (! $source) {
                    throw new RuntimeException(
                        'Impossibile leggere l’immagine caricata.'
                    );
                }

                $destination = imagecreatetruecolor(
                    $newWidth,
                    $newHeight
                );

                if ($destination === false) {
                    imagedestroy($source);

                    throw new RuntimeException(
                        'Impossibile elaborare l’immagine.'
                    );
                }

                if (
                    $preserveTransparency
                    && in_array($ext, ['png', 'webp'], true)
                ) {
                    imagealphablending($destination, false);
                    imagesavealpha($destination, true);

                    $transparent = imagecolorallocatealpha(
                        $destination,
                        255,
                        255,
                        255,
                        127
                    );

                    imagefill(
                        $destination,
                        0,
                        0,
                        $transparent
                    );
                }

                imagecopyresampled(
                    $destination,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $newWidth,
                    $newHeight,
                    $width,
                    $height
                );

                $this->saveImageResource(
                    $destination,
                    $fullPath,
                    $ext,
                    $quality
                );

                imagedestroy($source);
                imagedestroy($destination);

                return;
            }

            if ($alwaysReencode) {
                $this->compressOnly(
                    $fullPath,
                    $ext,
                    $quality
                );
            }
        } catch (\Throwable $exception) {
            if ($logErrors) {
                logger()->warning(
                    'Ottimizzazione immagine fallita.',
                    [
                        'path' => $fullPath,
                        'extension' => $ext,
                        'error' => $exception->getMessage(),
                    ]
                );
            }
        }
    }

    /**
     * Ricomprime un'immagine senza modificarne le dimensioni.
     *
     * @param array{
     *     jpg?: int,
     *     png?: int,
     *     webp?: int
     * } $quality
     */
    private function compressOnly(
        string $path,
        string $ext,
        array $quality
    ): void {
        $source = $this->createImageResource($path, $ext);

        if (! $source) {
            return;
        }

        try {
            $this->saveImageResource(
                $source,
                $path,
                $ext,
                $quality
            );
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * Crea una risorsa GD dal file.
     */
    protected function createImageResource(
        string $path,
        string $ext
    ) {
        return match ($this->normalizeExtension($ext)) {
            'jpg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($path)
                : false,
            default => false,
        };
    }

    /**
     * Salva una risorsa GD nel formato previsto.
     *
     * @param array{
     *     jpg?: int,
     *     png?: int,
     *     webp?: int
     * } $quality
     */
    private function saveImageResource(
        $image,
        string $path,
        string $ext,
        array $quality
    ): void {
        $saved = match ($this->normalizeExtension($ext)) {
            'jpg' => imagejpeg(
                $image,
                $path,
                $quality['jpg'] ?? 82
            ),

            'png' => imagepng(
                $image,
                $path,
                $quality['png'] ?? 7
            ),

            'webp' => function_exists('imagewebp')
                ? imagewebp(
                    $image,
                    $path,
                    $quality['webp'] ?? 82
                )
                : false,

            default => false,
        };

        if ($saved !== true) {
            throw new RuntimeException(
                'Impossibile salvare l’immagine elaborata.'
            );
        }
    }

    /**
     * Uniforma e verifica l'estensione interna.
     */
    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(
            ltrim(trim($extension), '.')
        );

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        if (! in_array(
            $extension,
            ['jpg', 'png', 'webp', 'gif'],
            true
        )) {
            throw new InvalidArgumentException(
                'Estensione immagine non consentita.'
            );
        }

        return $extension;
    }

    // ────────────────────────────────────────────────────────────────
    // Conversione WebP (media:webp-audit / media:convert-webp)
    //
    // A differenza di resizeAndCompress() (che ottimizza un file GIA'
    // scritto, in place, nello stesso formato), convertToWebp() scrive
    // SEMPRE in un percorso nuovo e non tocca mai $sourcePath: chi
    // decide se/quando sostituire un riferimento o rimuovere
    // l'originale e' deliberatamente un livello sopra questo metodo
    // (vedi docs/EDITORIAL_MEDIA_WEBP.md).
    // ────────────────────────────────────────────────────────────────

    /**
     * Converte un'immagine esistente su disco in WebP, scrivendo
     * esclusivamente in $destinationPath — non modifica mai
     * $sourcePath. Il formato sorgente e' rilevato dal contenuto reale
     * del file (getimagesize), mai dall'estensione del nome: un file
     * "foto.jpg" che in realta' contiene un PNG viene comunque
     * convertito correttamente (e viceversa un mismatch genuino viene
     * rifiutato, non silenziosamente mal-interpretato).
     *
     * Scrittura via file temporaneo nella stessa directory di
     * $destinationPath (cosi' rename() resta un'operazione atomica sullo
     * stesso filesystem) + validazione con getimagesize() + rename
     * atomico: non esiste una finestra in cui $destinationPath esiste
     * ma e' incompleto o non valido. Se una qualunque fase fallisce, il
     * file temporaneo viene rimosso e $destinationPath non viene mai
     * creato ne' toccato.
     *
     * @return string il path assoluto scritto (= $destinationPath).
     *
     * @throws RuntimeException se GD o l'encoder WebP non sono
     *                          disponibili, il sorgente non e'
     *                          un'immagine valida/supportata (GIF
     *                          incluso: perderebbe l'animazione, va
     *                          escluso a monte dal chiamante), o la
     *                          scrittura/validazione fallisce.
     */
    public function convertToWebp(
        string $sourcePath,
        string $destinationPath,
        int $quality = 82,
        ?int $maxWidth = null,
    ): string {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('L\'estensione GD non e\' disponibile su questo ambiente.');
        }

        if (! function_exists('imagewebp')) {
            throw new RuntimeException('Questo build di GD non supporta la scrittura di file WebP.');
        }

        if (! is_file($sourcePath)) {
            throw new RuntimeException('File sorgente non trovato: '.$sourcePath);
        }

        $type = $this->detectImageType($sourcePath);

        if ($type === IMAGETYPE_GIF) {
            throw new RuntimeException(
                'Conversione GIF->WebP non supportata da questo metodo (perderebbe l\'animazione): va esclusa a monte.'
            );
        }

        $source = $this->createResourceFromDetectedType($sourcePath, $type);

        if (! $source) {
            throw new RuntimeException('Impossibile leggere l\'immagine sorgente: '.$sourcePath);
        }

        try {
            if ($type === IMAGETYPE_JPEG) {
                $source = $this->applyExifOrientation($source, $sourcePath);
            }

            $width = imagesx($source);
            $height = imagesy($source);

            if ($maxWidth !== null && $width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = max(1, (int) round($height * ($maxWidth / $width)));

                $resized = imagecreatetruecolor($newWidth, $newHeight);

                if ($resized === false) {
                    throw new RuntimeException('Impossibile allocare il buffer per il ridimensionamento.');
                }

                $this->preserveAlphaChannel($resized);

                imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($source);
                $source = $resized;
            } else {
                // Anche senza resize, un canale alfa gia' presente (PNG
                // trasparente) va preservato esplicitamente: senza
                // queste due chiamate GD appiattirebbe la trasparenza
                // su nero scrivendo il WebP.
                $this->preserveAlphaChannel($source);
            }

            return $this->writeWebpAtomically($source, $destinationPath, $quality);
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * Rileva il formato reale di un file immagine dal suo contenuto
     * (mai dall'estensione del nome). Restituisce una costante
     * IMAGETYPE_*.
     *
     * @throws RuntimeException se il file non e' un'immagine valida o e'
     *                          in un formato non supportato da questo
     *                          servizio (solo JPEG/PNG/WebP/GIF).
     */
    private function detectImageType(string $path): int
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException('Il file non contiene un\'immagine valida: '.$path);
        }

        $type = $info[2];

        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new RuntimeException('Formato immagine non supportato (tipo GD #'.$type.'): '.$path);
        }

        return $type;
    }

    /**
     * @return \GdImage|false
     */
    private function createResourceFromDetectedType(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function preserveAlphaChannel($image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    /**
     * Corregge l'orientamento di un JPEG in base al tag EXIF
     * "Orientation", cosi' che il WebP risultante appaia sempre
     * verticale senza dipendere da quel metadato (che WebP non
     * preserva comunque nello stesso modo). Degrado sicuro: se
     * l'estensione exif non e' disponibile, o il file non ha dati EXIF
     * leggibili, l'immagine viene restituita invariata — mai un errore
     * bloccante per un problema puramente cosmetico.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function applyExifOrientation($image, string $sourcePath)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        if (! is_int($orientation) || $orientation < 1 || $orientation > 8 || $orientation === 1) {
            return $image;
        }

        $rotated = match ($orientation) {
            2 => $this->flipped($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotated($image, 180),
            4 => $this->flipped($image, IMG_FLIP_VERTICAL),
            5 => $this->flipped($this->rotated($image, -90, destroyOriginal: true), IMG_FLIP_HORIZONTAL),
            6 => $this->rotated($image, -90),
            7 => $this->flipped($this->rotated($image, -90, destroyOriginal: true), IMG_FLIP_VERTICAL),
            8 => $this->rotated($image, 90),
            default => $image,
        };

        return $rotated ?? $image;
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function rotated($image, int $angleCounterClockwise, bool $destroyOriginal = false)
    {
        $result = imagerotate($image, $angleCounterClockwise, 0);

        if ($destroyOriginal) {
            imagedestroy($image);
        }

        if ($result === false) {
            return $image;
        }

        if (! $destroyOriginal) {
            imagedestroy($image);
        }

        return $result;
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function flipped($image, int $mode)
    {
        // imageflip() modifica la risorsa in place e restituisce solo
        // un bool, a differenza di imagerotate(): nessuna risorsa
        // intermedia da distruggere qui.
        imageflip($image, $mode);

        return $image;
    }

    /**
     * @param  \GdImage  $image
     *
     * @throws RuntimeException se la scrittura o la validazione del
     *                          file risultante falliscono.
     */
    private function writeWebpAtomically($image, string $destinationPath, int $quality): string
    {
        $destinationPath = $this->normalizePath($destinationPath);
        $this->ensureDirectoryExists(dirname($destinationPath));

        $tempPath = $destinationPath.'.tmp-'.getmypid().'-'.bin2hex(random_bytes(4));

        if (! imagewebp($image, $tempPath, $quality)) {
            @unlink($tempPath);

            throw new RuntimeException('Scrittura del file WebP temporaneo fallita: '.$tempPath);
        }

        clearstatcache(true, $tempPath);

        // Validazione: il file appena scritto deve essere davvero un
        // WebP leggibile, non solo "esistente". Un encoder che fallisse
        // in modo silenzioso (scrivendo un file vuoto o troncato) verrebbe
        // cosi' intercettato prima del rename, non dopo.
        $verify = @getimagesize($tempPath);

        if ($verify === false || $verify[2] !== IMAGETYPE_WEBP) {
            @unlink($tempPath);

            throw new RuntimeException('Il file WebP scritto non supera la validazione: '.$tempPath);
        }

        if (! @rename($tempPath, $destinationPath)) {
            @unlink($tempPath);

            throw new RuntimeException('Impossibile spostare il file WebP nella destinazione finale: '.$destinationPath);
        }

        return $destinationPath;
    }
}
