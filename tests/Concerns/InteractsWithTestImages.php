<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Genera fixture immagine locali, deterministiche e di piccole dimensioni
 * per i test di ImageService e dei flussi di upload. Nessuna fixture viene
 * versionata: tutto è creato in una directory temporanea per-test e
 * rimosso in tearDown().
 */
trait InteractsWithTestImages
{
    /** @var string[] */
    private array $tempImageFiles = [];

    protected function tearDownTestImages(): void
    {
        foreach ($this->tempImageFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->tempImageFiles = [];
    }

    /**
     * Alloca un path temporaneo univoco con la data estensione, pulendo
     * subito il file "vuoto" creato da tempnam() (che altrimenti resta
     * orfano perche il path finale ha un suffisso diverso) e registrando
     * il path finale per la rimozione in tearDownTestImages().
     */
    private function newTempImagePath(string $ext): string
    {
        $base = tempnam(sys_get_temp_dir(), 'quark-img-');
        @unlink($base);

        $path = $base.'.'.$ext;
        $this->tempImageFiles[] = $path;

        return $path;
    }

    /**
     * Crea un UploadedFile "reale" (non la fixture leggera di Laravel) a
     * partire da un file temporaneo generato con GD, cosi da poter
     * costruire immagini con proprieta specifiche (es. trasparenza).
     */
    private function uploadedFileFromGdImage($gdImage, string $originalName, string $ext): UploadedFile
    {
        $path = $this->newTempImagePath($ext);

        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($gdImage, $path, 90),
            'png' => imagepng($gdImage, $path),
            'webp' => imagewebp($gdImage, $path),
            default => throw new \InvalidArgumentException("Formato non supportato nella fixture: {$ext}"),
        };

        imagedestroy($gdImage);

        return new UploadedFile($path, $originalName, mime_content_type($path), null, true);
    }

    /**
     * Immagine JPEG/PNG/WebP a tinta unita di dimensioni note.
     */
    private function makeSolidImageUpload(string $originalName, int $width, int $height, string $ext = 'jpg'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 30, 144, 255);
        imagefill($image, 0, 0, $color);

        return $this->uploadedFileFromGdImage($image, $originalName, $ext);
    }

    /**
     * PNG con canale alfa: l'intera immagine e completamente trasparente,
     * cosi da poter verificare in modo inequivocabile se la trasparenza
     * sopravvive al resize.
     */
    private function makeTransparentPngUpload(string $originalName, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 10, 200, 10, 127);
        imagefill($image, 0, 0, $transparent);

        return $this->uploadedFileFromGdImage($image, $originalName, 'png');
    }

    /**
     * File con estensione .jpg ma contenuto JPEG troncato: getimagesize()
     * riesce a leggere le dimensioni dall'header, ma la decodifica GD
     * fallisce in modo "morbido" (nessuna eccezione nativa in questo
     * ambiente — vedi ImageServiceTest per la verifica empirica).
     */
    private function makeTruncatedJpegUpload(string $originalName, int $width, int $height): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'quark-img-src-');
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $tmp, 90);
        imagedestroy($image);

        $full = file_get_contents($tmp);
        @unlink($tmp);

        $path = $this->newTempImagePath('jpg');
        file_put_contents($path, substr($full, 0, (int) (strlen($full) * 0.3)));

        return new UploadedFile($path, $originalName, 'image/jpeg', null, true);
    }

    /**
     * File testuale con estensione immagine: non e affatto un'immagine.
     */
    private function makeNonImageUpload(string $originalName): UploadedFile
    {
        $path = $this->newTempImagePath('jpg');
        file_put_contents($path, str_repeat('not an image ', 20));

        return new UploadedFile($path, $originalName, 'image/jpeg', null, true);
    }
}
