<?php
/**
 * Il Laboratorio — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 * @link      https://www.illaboratorio.it
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function index()
    {
        return view('admin.articles', [
            'articles' => Article::latest()->with('author')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.article-form', [
            'article'    => null,
            'categories' => config('laboratorio.categories'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Article::create($data + ['user_id' => auth()->id()]);

        return redirect()->route('admin.articles')->with('success', 'Articolo creato.');
    }

    public function edit(Article $article)
    {
        return view('admin.article-form', [
            'article'    => $article,
            'categories' => config('laboratorio.categories'),
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $article->update($this->validated($request));

        return redirect()->route('admin.articles')->with('success', 'Articolo aggiornato.');
    }

    public function destroy(Article $article)
    {
        ActivityLog::record('Articolo eliminato', 'article', $article->id, $article->title);
        $article->delete();

        return redirect()->route('admin.articles')->with('success', 'Articolo eliminato.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'              => 'required|max:255',
            'excerpt'            => 'nullable|max:300',
            'body'               => 'required',
            'category'           => 'required',
            'cover_image'        => 'nullable|max:255',
            'cover_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'status'             => 'required|in:draft,published,review',
            'read_minutes'       => 'integer|min:1|max:60',
            'featured'           => 'boolean',
        ]);

        // Upload diretto immagine dal form articolo
        if ($request->hasFile('cover_image_upload') && $request->file('cover_image_upload')->isValid()) {
            $file     = $request->file('cover_image_upload');
            $ext      = strtolower($file->getClientOriginalExtension());
            $diskName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                        . '-' . date('YmdHis')
                        . '-' . substr(md5(rand()), 0, 6)
                        . '.' . $ext;
            $uploadPath = public_path('assets/img');
            $file->move($uploadPath, $diskName);

            // Ottimizzazione automatica con GD
            $fullPath = $uploadPath . '/' . $diskName;
            if (extension_loaded('gd') && file_exists($fullPath)) {
                try {
                    [$w, $h] = getimagesize($fullPath);
                    if ($w > 1600) {
                        $nw = 1600; $nh = (int) round($h * (1600 / $w));
                        $src = match($ext) {
                            'jpg','jpeg' => imagecreatefromjpeg($fullPath),
                            'png'        => imagecreatefrompng($fullPath),
                            'webp'       => imagecreatefromwebp($fullPath),
                            default      => null,
                        };
                        if ($src) {
                            $dst = imagecreatetruecolor($nw, $nh);
                            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                            match($ext) {
                                'jpg','jpeg' => imagejpeg($dst, $fullPath, 82),
                                'png'        => imagepng($dst, $fullPath, 7),
                                'webp'       => imagewebp($dst, $fullPath, 82),
                                default      => null,
                            };
                            imagedestroy($src); imagedestroy($dst);
                        }
                    }
                } catch (\Throwable $e) { /* fallback silenzioso */ }
            }

            $data['cover_image'] = $diskName;
        }

        unset($data['cover_image_upload']);

        $data['slug']         = Str::slug($data['title']);
        $data['featured']     = $request->boolean('featured');
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        // Calcolo automatico tempo di lettura (200 parole/minuto)
        if (!empty($data['body'])) {
            $wordCount = str_word_count(strip_tags($data['body']));
            $data['read_minutes'] = max(1, (int) round($wordCount / 200));
        }

        return $data;
    }

    /**
     * Aggiorna lo stato di verifica di un articolo.
     */
    public function updateVerification(\Illuminate\Http\Request $request, Article $article)
    {
        $validated = $request->validate([
            'verification_status' => 'required|in:unverified,in_progress,verified,needs_update',
            'verification_notes'  => 'nullable|max:1000',
            'primary_sources'     => 'nullable|max:500',
        ]);

        $data = $validated;

        if ($validated['verification_status'] === 'verified') {
            $data['verified_at'] = now();
            $data['verified_by'] = auth()->user()->name;
        }

        $article->update($data);

        return back()->with('success', 'Stato verifica aggiornato.');
    }


    public function duplicate(\App\Models\Article $article)
    {
        $new = $article->replicate();
        $new->title       = 'Copia di — ' . $article->title;
        $new->slug        = \Illuminate\Support\Str::slug($new->title) . '-' . time();
        $new->status      = 'draft';
        $new->featured    = false;
        $new->views       = 0;
        $new->published_at = null;
        $new->verification_status = 'unverified';
        $new->push();

        \App\Models\ActivityLog::record('Articolo duplicato', 'article', $new->id, $new->title);

        return redirect()->route('admin.articles.edit', $new)
            ->with('success', 'Articolo duplicato come bozza.');
    }

    public function quickDraft()
    {
        return view('admin.quick-draft');
    }
}