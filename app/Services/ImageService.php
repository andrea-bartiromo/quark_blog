<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageService
{
    public function buildFileName(UploadedFile $file, string $extension, string $suffix): string
    {
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return $baseName . '-' . $suffix . '.' . $extension;
    }

    public function ensureDirectoryExists(string $path, int $permissions): void
    {
        if (! is_dir($path)) {
            mkdir($path, $permissions, true);
        }
    }

    public function upload(UploadedFile $file, string $destinationPath, string $fileName): string
    {
        $file->move($destinationPath, $fileName);

        return $destinationPath . '/' . $fileName;
    }

    /**
     * Ridimensiona (se oltre $maxWidth) e comprime un'immagine già salvata su disco tramite GD.
     *
     * @param array{jpg?: int, png?: int, webp?: int} $quality
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
        if (! extension_loaded('gd') || ! file_exists($fullPath)) {
            return;
        }

        try {
            [$w, $h] = getimagesize($fullPath);

            if ($alwaysReencode && (! $w || ! $h)) {
                return;
            }

            if ($w > $maxWidth) {
                $newWidth  = $maxWidth;
                $newHeight = (int) round($h * ($maxWidth / $w));

                $src = $this->createImageResource($fullPath, $ext);
                if (! $src) {
                    return;
                }

                $dst = imagecreatetruecolor($newWidth, $newHeight);

                if ($preserveTransparency && $ext === 'png') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                    imagefill($dst, 0, 0, $transparent);
                }

                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $w, $h);
                $this->saveImageResource($dst, $fullPath, $ext, $quality);

                imagedestroy($src);
                imagedestroy($dst);
            } elseif ($alwaysReencode) {
                $this->compressOnly($fullPath, $ext, $quality);
            }
        } catch (\Throwable $e) {
            if ($logErrors) {
                \Log::warning('Ottimizzazione immagine fallita: ' . $e->getMessage());
            }
        }
    }

    /**
     * Ricomprime un'immagine senza ridimensionarla (fallback silenzioso in caso di errore).
     *
     * @param array{jpg?: int, png?: int, webp?: int} $quality
     */
    private function compressOnly(string $path, string $ext, array $quality): void
    {
        try {
            $src = $this->createImageResource($path, $ext);
            if (! $src) {
                return;
            }

            $this->saveImageResource($src, $path, $ext, $quality);

            imagedestroy($src);
        } catch (\Throwable $e) {
            // Fallback silenzioso
        }
    }

    private function createImageResource(string $path, string $ext)
    {
        return match ($ext) {
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            'png'         => imagecreatefrompng($path),
            'webp'        => imagecreatefromwebp($path),
            default       => null,
        };
    }

    /**
     * @param array{jpg?: int, png?: int, webp?: int} $quality
     */
    private function saveImageResource($image, string $path, string $ext, array $quality): void
    {
        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, $quality['jpg'] ?? 82),
            'png'         => imagepng($image, $path, $quality['png'] ?? 7),
            'webp'        => imagewebp($image, $path, $quality['webp'] ?? 82),
            default       => null,
        };
    }
}
