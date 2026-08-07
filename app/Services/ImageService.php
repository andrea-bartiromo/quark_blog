<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
            throw new \RuntimeException(
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

        if (! is_file($fullPath)) {
            throw new \RuntimeException(
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
                throw new \RuntimeException(
                    'Il file caricato non contiene un’immagine valida.'
                );
            }

            [$width, $height] = $imageInfo;

            if ($width <= 0 || $height <= 0) {
                throw new \RuntimeException(
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
                    throw new \RuntimeException(
                        'Impossibile leggere l’immagine caricata.'
                    );
                }

                $destination = imagecreatetruecolor(
                    $newWidth,
                    $newHeight
                );

                if ($destination === false) {
                    imagedestroy($source);

                    throw new \RuntimeException(
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
            throw new \RuntimeException(
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
}