<?php

namespace App\Services;

use App\Support\Concerns\ConfirmsFileDeletion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ImageService
{
    use ConfirmsFileDeletion;

    /**
     * Numero di tentativi per la rimozione del sorgente originale dopo una
     * conversione WebP riuscita (vedi autoConvertToWebpIfEligible()) e
     * intervallo tra un tentativo e l'altro. Stessi valori usati da
     * PublicMediaSyncService::removeFileWithRetry() per lo stesso motivo:
     * su Windows un file appena scritto puo' restare brevemente bloccato
     * da un handle non ancora rilasciato o da una scansione antivirus.
     */
    private const SOURCE_REMOVAL_RETRY_ATTEMPTS = 5;

    private const SOURCE_REMOVAL_RETRY_DELAY_MICROSECONDS = 100_000;

    /**
     * Controlli consecutivi (e intervallo tra l'uno e l'altro) per
     * confermare che il sorgente sia davvero sparito dopo un unlink() che
     * ha già riportato successo — vedi ConfirmsFileDeletion. Stessi valori
     * usati da PublicMediaSyncService per lo stesso motivo.
     */
    private const SOURCE_REMOVAL_CONFIRMATION_CHECKS = 3;

    private const SOURCE_REMOVAL_CONFIRMATION_DELAY_MICROSECONDS = 100_000;

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
     *
     * ROOT CAUSE del file orfano "resuscitato" dopo un cleanup riuscito
     * (osservato ripetutamente su Windows reale nonostante path corretto,
     * unlink() vincente al primo tentativo e retry — vedi git log di
     * PublicMediaSyncService per l'intera indagine, PR #121-#124 e #160):
     * $file->move() — per un UploadedFile "fake" nei test
     * (Illuminate\Http\Testing\File, usato da UploadedFile::fake()) —
     * esegue internamente un semplice rename() (Symfony\Component\
     * HttpFoundation\File\File::move(), nessun fallback copy()+unlink()).
     * Il file fake e' pero' scritto su un handle tmpfile() che resta
     * APERTO per il resto della richiesta (nessun punto del ciclo di vita
     * Laravel/Symfony lo chiude prima della fine dello script): dopo un
     * rename(), quell'handle continua a riferirsi alla STESSA identita' di
     * storage del file appena rinominato (stesso inode: verificato
     * empiricamente — fstat() sull'handle riporta la stessa dimensione del
     * file di destinazione, ed e' ancora scrivibile anche DOPO che
     * unlink() sulla destinazione ha gia' avuto successo). Su Windows,
     * dove la cancellazione di un file con un handle ancora aperto non ha
     * le stesse garanzie atomiche POSIX, questo e' esattamente lo scenario
     * gia' osservato: unlink() riporta successo, file_exists() e'
     * immediatamente false, ma il file torna visibile prima della fine
     * della richiesta quando quell'handle residuo scrive/si chiude.
     *
     * Fix: MAI condividere l'identita' di storage tra il file caricato
     * (temporaneo, di proprieta' del framework) e il file di destinazione
     * (di proprieta' di questa applicazione). Si legge il contenuto e lo
     * si scrive in un file indipendente — stesso effetto finale di
     * move(), ma senza alcun rename() che leghi le due identita'. Verificato
     * empiricamente: con questo approccio, scrivere e chiudere l'handle
     * originale DOPO aver rimosso la destinazione non ha alcun effetto su
     * quest'ultima (path completamente scollegati).
     */
    public function upload(
        UploadedFile $file,
        string $destinationPath,
        string $fileName
    ): string {
        $destinationPath = $this->normalizePath($destinationPath);

        $this->ensureDirectoryExists($destinationPath);

        $fullPath = $destinationPath.'/'.$fileName;

        $this->materializeUploadedFileIndependently($file, $fullPath);

        if (! is_file($fullPath)) {
            throw new RuntimeException(
                'Il caricamento dell’immagine non è riuscito.'
            );
        }

        return $fullPath;
    }

    /**
     * Scrive $destinationPath come file NUOVO e indipendente a partire dal
     * contenuto del file caricato — mai un rename()/move() diretto da quel
     * file, che legherebbe le due identita' di storage (vedi il commento
     * di upload() sopra per il perche'). Scrittura via file temporaneo
     * nella STESSA directory di destinazione + rename() atomico verso il
     * nome finale — stesso pattern gia' stabilito da writeWebpAtomically()
     * per lo stesso motivo (mai un file di destinazione a meta' scritto se
     * il processo si interrompe a meta' write). Il file temporaneo
     * intermedio e' creato da questo metodo con file_put_contents(): non
     * esiste alcun handle PHP residuo legato ad esso dopo la scrittura,
     * quindi il rename() successivo non reintroduce l'accoppiamento che
     * questo fix elimina. Il file temporaneo ORIGINALE del caricamento
     * viene rimosso per ultimo, a promozione confermata: se la sua
     * rimozione fallisse (best-effort, mai bloccante), il file applicativo
     * di destinazione resterebbe comunque scritto correttamente e
     * indipendente da esso.
     */
    private function materializeUploadedFileIndependently(UploadedFile $file, string $destinationPath): void
    {
        $sourcePath = $file->getPathname();
        $content = @file_get_contents($sourcePath);

        if ($content === false) {
            throw new RuntimeException('Impossibile leggere il file caricato: '.$sourcePath);
        }

        $tempPath = $destinationPath.'.tmp-'.getmypid().'-'.bin2hex(random_bytes(4));

        if (@file_put_contents($tempPath, $content) === false) {
            @unlink($tempPath);

            throw new RuntimeException('Impossibile scrivere il file caricato in '.$destinationPath);
        }

        if (! @rename($tempPath, $destinationPath)) {
            @unlink($tempPath);

            throw new RuntimeException('Impossibile promuovere il file caricato in '.$destinationPath);
        }

        @unlink($sourcePath);
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

    /**
     * Restituisce $path con l'estensione sostituita, preservando
     * directory e basename. Path-agnostico (funziona sia su path
     * assoluti che su disk_name relativi): usato ovunque un nome file
     * debba "diventare .webp" senza toccare il resto del percorso.
     */
    public function changeExtension(string $path, string $newExtension): string
    {
        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $prefix = ($dir === '.' || $dir === '') ? '' : $dir.'/';

        return $prefix.$base.'.'.ltrim(strtolower(trim($newExtension)), '.');
    }

    /**
     * Percorso deterministico di una variante responsive per una data
     * larghezza, derivato dal percorso originale senza alcuno stato
     * esterno (nessun registro/colonna da consultare): stesso schema sia
     * per un percorso assoluto sul filesystem sia per un disk_name
     * relativo, esattamente come changeExtension(). Esempio:
     * "articles/covers/foto.webp" + 480 -> "articles/covers/foto-480w.webp".
     *
     * Deterministico per costruzione: chi deve ripulire le varianti dopo
     * una cancellazione o una sostituzione (vedi ResponsiveImageVariantService)
     * puo' ricalcolare esattamente questi stessi percorsi, quindi trovarle
     * e cancellarle, senza doverle prima leggere da qualche parte.
     */
    public function responsiveVariantPath(string $path, int $width): string
    {
        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $prefix = ($dir === '.' || $dir === '') ? '' : $dir.'/';

        return $prefix.$base.'-'.$width.'w.'.$ext;
    }

    /**
     * Politica per i NUOVI upload editoriali (FASE 5): se il formato lo
     * consente (JPG/PNG, mai WebP-gia'-tale ne' GIF), converte il file
     * appena caricato in WebP e sostituisce l'originale sullo stesso
     * percorso (stesso basename, estensione .webp) — cosi' i nuovi
     * upload smettono di crescere lo storage in formati piu' pesanti,
     * senza toccare nulla del catalogo editoriale gia' esistente (quello
     * resta compito della migrazione legacy separata).
     *
     * Degrado sicuro e deliberatamente conservativo: se la conversione
     * fallisce per qualunque motivo (encoder assente, file corrotto,
     * scrittura fallita), il file originale caricato resta intatto e
     * invariato — il chiamante deve semplicemente procedere con
     * l'ottimizzazione nello stesso formato (resizeAndCompress()) come
     * faceva prima dell'introduzione di questo metodo. Non solleva mai
     * un'eccezione: un problema di conversione WebP non deve mai far
     * fallire un upload che altrimenti sarebbe riuscito.
     *
     * @return array{full_path: string, ext: string, mime_type: string, webp_applied: bool}
     */
    public function autoConvertToWebpIfEligible(
        string $fullPath,
        string $ext,
        int $webpQuality,
        int $webpMaxWidth,
    ): array {
        $ext = strtolower(trim($ext, '. '));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        $unchanged = [
            'full_path' => $fullPath,
            'ext' => $ext,
            'mime_type' => $mimeTypes[$ext] ?? 'application/octet-stream',
            'webp_applied' => false,
        ];

        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return $unchanged;
        }

        $webpPath = $this->changeExtension($fullPath, 'webp');

        try {
            $this->convertToWebp($fullPath, $webpPath, $webpQuality, $webpMaxWidth);
        } catch (\Throwable $exception) {
            Log::warning('ImageService: conversione automatica a WebP fallita, mantengo il formato originale.', [
                'full_path' => $fullPath,
                'exception' => $exception->getMessage(),
            ]);

            return $unchanged;
        }

        if (! $this->removeSourceWithRetry($fullPath)) {
            // Rimozione del sorgente fallita anche dopo i tentativi: se
            // registrassimo comunque webp_applied => true, il chiamante
            // salverebbe solo il riferimento al WebP mentre l'originale
            // JPG/PNG resterebbe sul filesystem come orfano non tracciato
            // (esattamente la classe di bug gia' vista su Windows con
            // PublicMediaSyncService). Trattiamo quindi la mancata
            // rimozione come un fallimento dell'intera conversione: il
            // WebP appena generato viene scartato e si ripiega
            // sull'originale intatto, coerente con la degradazione sicura
            // documentata sopra.
            Log::warning('ImageService: WebP generato ma rimozione del sorgente originale fallita, mantengo il formato originale.', [
                'full_path' => $fullPath,
                'webp_path' => $webpPath,
            ]);

            @unlink($webpPath);

            return $unchanged;
        }

        return [
            'full_path' => $webpPath,
            'ext' => 'webp',
            'mime_type' => 'image/webp',
            'webp_applied' => true,
        ];
    }

    /**
     * Ritenta la rimozione del sorgente originale un numero limitato di
     * volte, con una breve attesa tra un tentativo e l'altro (stessa
     * strategia di PublicMediaSyncService::removeFileWithRetry(), per lo
     * stesso motivo: su Windows un file appena scritto puo' restare
     * brevemente bloccato). Ricontrolla l'esistenza del file dopo ogni
     * tentativo fallito cosi' da restare idempotente anche se un unlink()
     * precedente e' in realta' riuscito nonostante un esito riportato come
     * "false".
     *
     * Un unlink() che riporta successo non e' trattato come la parola
     * finale (vedi ConfirmsFileDeletion): lo stesso file puo' ricomparire
     * poco dopo una rimozione apparentemente riuscita — esattamente il bug
     * gia' osservato su Windows reale per PublicMediaSyncService, che
     * senza questo controllo restava aperto qui per il sorgente JPG/PNG
     * nei flussi di conversione automatica a WebP.
     */
    private function removeSourceWithRetry(string $path): bool
    {
        $originalContentHash = @hash_file('sha256', $path);

        for ($attempt = 1; $attempt <= self::SOURCE_REMOVAL_RETRY_ATTEMPTS; $attempt++) {
            if ($this->removeFile($path)) {
                if ($this->confirmFileReallyGone(
                    $path,
                    $originalContentHash,
                    self::SOURCE_REMOVAL_CONFIRMATION_CHECKS,
                    self::SOURCE_REMOVAL_CONFIRMATION_DELAY_MICROSECONDS,
                )) {
                    return true;
                }
            } else {
                clearstatcache(true, $path);

                if (! file_exists($path)) {
                    return true;
                }
            }

            if ($attempt < self::SOURCE_REMOVAL_RETRY_ATTEMPTS) {
                usleep(self::SOURCE_REMOVAL_RETRY_DELAY_MICROSECONDS);
            }
        }

        return false;
    }

    /**
     * Reso "protected" (da un `@unlink()` inline) esclusivamente per i
     * test: un vero fallimento di unlink() su un file realmente presente
     * non e' simulabile in modo affidabile in un processo che gira come
     * root (i permessi vengono ignorati). Una sottoclasse di test che
     * sovrascrive questo singolo metodo e' il modo meno invasivo per
     * verificare il comportamento quando la rimozione fallisce davvero.
     */
    protected function removeFile(string $path): bool
    {
        return @unlink($path);
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

    /**
     * FASE 5 (missione S2 responsive images): genera, ACCANTO a un'immagine
     * gia' presente sul filesystem (tipicamente l'esito di upload() seguito
     * da autoConvertToWebpIfEligible()/resizeAndCompress() nello stesso
     * upload), un piccolo insieme di copie WebP piu' strette — mai al posto
     * del file sorgente, che resta sempre il limite superiore disponibile.
     *
     * Nessuna nuova pipeline: riusa la stessa lettura del formato reale
     * (detectImageType), la stessa correzione EXIF per i JPEG
     * (applyExifOrientation), la stessa preservazione alfa
     * (preserveAlphaChannel) e la stessa scrittura atomica
     * (writeWebpAtomically, temp file + validazione + rename) gia' usate da
     * convertToWebp() per il file principale.
     *
     * Mai upscale: una larghezza target >= alla larghezza reale del
     * sorgente viene sempre saltata (il sorgente stesso e' gia' la
     * variante piu' grande disponibile per quel target). Le GIF sono
     * escluse (stessa politica di resizeAndCompress(): GD non le elabora,
     * per non perdere l'animazione).
     *
     * Idempotente: se il file di destinazione esiste gia' ed e' un WebP
     * valido, non viene rigenerato. Best-effort per singola larghezza: un
     * fallimento su UN target (encoder assente, larghezza non valida) viene
     * loggato e non impedisce agli altri target di essere generati — non
     * deve mai bloccare l'upload principale, di cui questa e' solo
     * un'ottimizzazione accessoria.
     *
     * @param  list<int>  $targetWidths  Larghezze desiderate (es. da
     *                                   config('media.responsive_widths')).
     * @return list<array{width: int, path: string}> Varianti realmente
     *                                               scritte in QUESTA
     *                                               chiamata o gia'
     *                                               presenti e valide
     *                                               (sorgente escluso).
     */
    public function generateResponsiveVariants(
        string $sourceAbsolutePath,
        array $targetWidths,
        int $quality = 82,
    ): array {
        if (! extension_loaded('gd') || ! function_exists('imagewebp') || ! is_file($sourceAbsolutePath)) {
            return [];
        }

        try {
            $type = $this->detectImageType($sourceAbsolutePath);
        } catch (RuntimeException) {
            return [];
        }

        if ($type === IMAGETYPE_GIF) {
            return [];
        }

        $imageInfo = @getimagesize($sourceAbsolutePath);
        if ($imageInfo === false || $imageInfo[0] <= 0) {
            return [];
        }

        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];

        $widths = array_values(array_unique(array_filter(
            $targetWidths,
            fn (int $w) => $w > 0 && $w < $sourceWidth
        )));
        sort($widths);

        $written = [];

        foreach ($widths as $width) {
            $variantPath = $this->responsiveVariantPath($sourceAbsolutePath, $width);

            if ($this->isValidExistingWebp($variantPath, $width)) {
                $written[] = ['width' => $width, 'path' => $variantPath];

                continue;
            }

            try {
                $written[] = [
                    'width' => $width,
                    'path' => $this->renderResponsiveVariant($sourceAbsolutePath, $type, $sourceWidth, $sourceHeight, $width, $variantPath, $quality),
                ];
            } catch (\Throwable $exception) {
                Log::warning('ImageService: generazione variante responsive fallita, il file principale resta invariato.', [
                    'source' => $sourceAbsolutePath,
                    'target_width' => $width,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $written;
    }

    private function isValidExistingWebp(string $path, int $expectedWidth): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $info = @getimagesize($path);

        return $info !== false && $info[2] === IMAGETYPE_WEBP && $info[0] === $expectedWidth;
    }

    private function renderResponsiveVariant(
        string $sourceAbsolutePath,
        int $type,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        string $destinationPath,
        int $quality,
    ): string {
        $source = $this->createResourceFromDetectedType($sourceAbsolutePath, $type);

        if (! $source) {
            throw new RuntimeException('Impossibile leggere l\'immagine sorgente per la variante responsive: '.$sourceAbsolutePath);
        }

        try {
            if ($type === IMAGETYPE_JPEG) {
                $source = $this->applyExifOrientation($source, $sourceAbsolutePath);
            }

            $targetHeight = max(1, (int) round($sourceHeight * ($targetWidth / $sourceWidth)));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($resized === false) {
                throw new RuntimeException('Impossibile allocare il buffer per la variante responsive.');
            }

            $this->preserveAlphaChannel($resized);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            return $this->writeWebpAtomically($resized, $destinationPath, $quality);
        } finally {
            imagedestroy($source);
            if (isset($resized) && $resized !== false) {
                imagedestroy($resized);
            }
        }
    }
}
