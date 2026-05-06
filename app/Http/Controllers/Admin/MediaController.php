<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index()
    {
        return view('admin.media', [
            'files' => Media::latest()->with('user')->paginate(24),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image'    => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'alt_text' => 'nullable|max:200',
        ]);

        $file     = $request->file('image');
        $original = $file->getClientOriginalName();
        $ext      = strtolower($file->getClientOriginalExtension());

        // Nome univoco
        $diskName = Str::slug(pathinfo($original, PATHINFO_FILENAME))
                  . '-' . now()->format('YmdHis')
                  . '-' . Str::random(6)
                  . '.' . $ext;

        $uploadPath = public_path('assets/img');

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0775, true);
        }

        $file->move($uploadPath, $diskName);
        $fullPath = $uploadPath . '/' . $diskName;

        // Ottimizzazione immagine con GD (se disponibile)
        $this->optimizeImage($fullPath, $ext);

        $media = Media::create([
            'user_id'   => auth()->id(),
            'filename'  => $original,
            'disk_name' => $diskName,
            'mime_type' => $file->getClientMimeType(),
            'size'      => filesize($fullPath) ?: 0,
            'alt_text'  => $request->input('alt_text'),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'       => true,
                'filename' => $diskName,
                'url'      => asset('assets/img/' . $diskName),
                'id'       => $media->id,
            ]);
        }

        return back()->with('success', "Immagine \"{$original}\" caricata con successo.");
    }

    private function optimizeImage(string $path, string $ext): void
    {
        // Controlla se GD è disponibile
        if (!extension_loaded('gd')) return;

        try {
            // Dimensioni originali
            [$width, $height] = getimagesize($path);
            if (!$width || !$height) return;

            // Ridimensiona solo se più grande di 1600px
            $maxWidth = 1600;
            if ($width <= $maxWidth) {
                // Solo comprimi senza ridimensionare
                $this->compressImage($path, $ext, $width, $height);
                return;
            }

            // Calcola nuove dimensioni mantenendo proporzioni
            $ratio     = $maxWidth / $width;
            $newWidth  = $maxWidth;
            $newHeight = (int) round($height * $ratio);

            // Crea immagine ridimensionata
            $src = match($ext) {
                'jpg', 'jpeg' => imagecreatefromjpeg($path),
                'png'         => imagecreatefrompng($path),
                'webp'        => imagecreatefromwebp($path),
                default       => null,
            };

            if (!$src) return;

            $dst = imagecreatetruecolor($newWidth, $newHeight);

            // Mantieni trasparenza per PNG
            if ($ext === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefill($dst, 0, 0, $transparent);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Salva con compressione
            match($ext) {
                'jpg', 'jpeg' => imagejpeg($dst, $path, 82),
                'png'         => imagepng($dst, $path, 7),
                'webp'        => imagewebp($dst, $path, 82),
                default       => null,
            };

            imagedestroy($src);
            imagedestroy($dst);

        } catch (\Throwable $e) {
            \Log::warning('Ottimizzazione immagine fallita: ' . $e->getMessage());
        }
    }

    private function compressImage(string $path, string $ext, int $w, int $h): void
    {
        try {
            $src = match($ext) {
                'jpg', 'jpeg' => imagecreatefromjpeg($path),
                'png'         => imagecreatefrompng($path),
                'webp'        => imagecreatefromwebp($path),
                default       => null,
            };

            if (!$src) return;

            match($ext) {
                'jpg', 'jpeg' => imagejpeg($src, $path, 82),
                'png'         => imagepng($src, $path, 7),
                'webp'        => imagewebp($src, $path, 82),
                default       => null,
            };

            imagedestroy($src);
        } catch (\Throwable $e) {
            // Fallback silenzioso
        }
    }

    public function destroy(Media $media)
    {
        $path = public_path('assets/img/' . $media->disk_name);
        if (file_exists($path)) {
            unlink($path);
        }

        $media->delete();
        return back()->with('success', 'Immagine eliminata.');
    }
}