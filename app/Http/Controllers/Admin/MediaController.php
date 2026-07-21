<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function __construct(private readonly ImageService $imageService) {}

    public function index()
    {
        return view('admin.media', [
            'files' => Media::latest()->with('user')->paginate(24),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'alt_text' => 'nullable|max:200',
        ]);

        $file = $request->file('image');
        $original = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        // Nome univoco
        $diskName = $this->imageService->buildFileName(
            $file,
            $ext,
            now()->format('YmdHis').'-'.Str::random(6)
        );

        $uploadPath = public_path('assets/img');
        $this->imageService->ensureDirectoryExists($uploadPath, 0775);

        $fullPath = $this->imageService->upload($file, $uploadPath, $diskName);

        // Ottimizzazione immagine con GD (se disponibile)
        $this->imageService->resizeAndCompress(
            $fullPath,
            $ext,
            1600,
            ['jpg' => 82, 'png' => 7, 'webp' => 82],
            preserveTransparency: true,
            alwaysReencode: true,
            logErrors: true
        );

        $media = Media::create([
            'user_id' => auth()->id(),
            'filename' => $original,
            'disk_name' => $diskName,
            'mime_type' => $file->getClientMimeType(),
            'size' => filesize($fullPath) ?: 0,
            'alt_text' => $request->input('alt_text'),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'filename' => $diskName,
                'url' => asset('assets/img/'.$diskName),
                'id' => $media->id,
            ]);
        }

        return back()->with('success', "Immagine \"{$original}\" caricata con successo.");
    }

    public function destroy(Media $media)
    {
        $path = public_path('assets/img/'.$media->disk_name);
        if (file_exists($path)) {
            unlink($path);
        }

        $media->delete();

        return back()->with('success', 'Immagine eliminata.');
    }
}
