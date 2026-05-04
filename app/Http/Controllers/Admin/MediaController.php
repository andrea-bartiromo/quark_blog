<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /** Lista di tutti i file caricati */
    public function index()
    {
        return view('admin.media', [
            'files' => Media::latest()->with('user')->paginate(24),
        ]);
    }

    /** Carica un nuovo file */
    public function store(Request $request)
    {
        $request->validate([
            'image'    => 'required|image|mimes:jpeg,png,webp,gif|max:5120', // max 5MB
            'alt_text' => 'nullable|max:200',
        ]);

        $file     = $request->file('image');
        $original = $file->getClientOriginalName();
        $ext      = $file->getClientOriginalExtension();

        // Nome univoco per evitare sovrascritture
        $diskName = Str::slug(pathinfo($original, PATHINFO_FILENAME))
                  . '-' . now()->format('YmdHis')
                  . '-' . Str::random(6)
                  . '.' . $ext;

        // Salviamo in public/assets/img/
        $uploadPath = public_path('assets/img');

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0775, true);
        }

        $file->move($uploadPath, $diskName);

        $media = Media::create([
            'user_id'   => auth()->id(),
            'filename'  => $original,
            'disk_name' => $diskName,
            'mime_type' => $file->getClientMimeType(),
            'size'      => filesize($uploadPath . '/' . $diskName) ?: 0,
            'alt_text'  => $request->input('alt_text'),
        ]);

        // Se la richiesta è AJAX (dal form articolo), ritorna JSON
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

    /** Elimina un file */
    public function destroy(Media $media)
    {
        // Rimuovi dal disco
        $path = public_path('assets/img/' . $media->disk_name);
        if (file_exists($path)) {
            unlink($path);
        }

        $media->delete();

        return back()->with('success', 'Immagine eliminata.');
    }
}