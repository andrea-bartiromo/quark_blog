<?php

namespace App\Http\Controllers\Redazione;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ActivityLog;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class ArticleController extends Controller
{
    public function __construct(private readonly ImageService $imageService)
    {
    }

    public function index()
    {
        $articles = Article::where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('redazione.articles', compact('articles'));
    }

    public function create()
    {
        $categories = config('laboratorio.categories');
        return view('redazione.article-form', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'    => 'required|max:200',
            'excerpt'  => 'nullable|max:300',
            'body'     => 'required',
            'category' => 'required|in:' . implode(',', array_keys(config('laboratorio.categories'))),
            'cover_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'cover_image'        => 'nullable|max:255',
            'read_minutes'       => 'nullable|integer|min:1|max:60',
        ]);

        // Upload immagine
        if ($request->hasFile('cover_image_upload') && $request->file('cover_image_upload')->isValid()) {
            $file = $request->file('cover_image_upload');
            $ext  = strtolower($file->getClientOriginalExtension());
            $diskName = $this->imageService->buildFileName(
                $file,
                $ext,
                date('YmdHis') . '-' . Str::random(6)
            );
            $this->imageService->upload($file, public_path('assets/img'), $diskName);
            $data['cover_image'] = $diskName;
        }

        $wordCount = str_word_count(strip_tags($data['body'] ?? ''));
        $article = Article::create([
            'user_id'             => auth()->id(),
            'title'               => $data['title'],
            'slug'                => Str::slug($data['title']) . '-' . time(),
            'excerpt'             => $data['excerpt'] ?? null,
            'body'                => $data['body'],
            'category'            => $data['category'],
            'cover_image'         => $data['cover_image'] ?? null,
            'status'              => 'review', // ← sempre in revisione
            'read_minutes'        => $data['read_minutes'] ?? max(1, (int) ceil($wordCount / 180)),
            'verification_status' => 'unverified',
            'published_at'        => now(),
        ]);

        // Notifica email all'editor
        $this->notifyEditor($article);

        ActivityLog::record('Articolo inviato in revisione', 'article', $article->id, $article->title);

        return redirect()->route('redazione.articles')
            ->with('success', 'Articolo inviato in revisione. L\'editor ti contatterà presto.');
    }

    public function edit(Article $article)
    {
        // Solo il proprio autore può modificare
        if ($article->user_id !== auth()->id()) {
            abort(403);
        }

        // Non modificabile se pubblicato (solo l'editor può)
        if ($article->status === 'published') {
            return redirect()->route('redazione.articles')
                ->with('error', 'Gli articoli pubblicati non possono essere modificati. Contatta l\'editor.');
        }

        $categories = config('laboratorio.categories');
        return view('redazione.article-form', compact('article', 'categories'));
    }

    public function update(Request $request, Article $article)
    {
        if ($article->user_id !== auth()->id()) abort(403);
        if ($article->status === 'published') abort(403);

        $data = $request->validate([
            'title'    => 'required|max:200',
            'excerpt'  => 'nullable|max:300',
            'body'     => 'required',
            'category' => 'required|in:' . implode(',', array_keys(config('laboratorio.categories'))),
            'cover_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'cover_image'        => 'nullable|max:255',
            'read_minutes'       => 'nullable|integer|min:1|max:60',
        ]);

        if ($request->hasFile('cover_image_upload') && $request->file('cover_image_upload')->isValid()) {
            $file = $request->file('cover_image_upload');
            $ext  = strtolower($file->getClientOriginalExtension());
            $diskName = $this->imageService->buildFileName(
                $file,
                $ext,
                date('YmdHis') . '-' . Str::random(6)
            );
            $this->imageService->upload($file, public_path('assets/img'), $diskName);
            $data['cover_image'] = $diskName;
        }

        $wordCount = str_word_count(strip_tags($data['body'] ?? ''));
        $article->update([
            'title'        => $data['title'],
            'excerpt'      => $data['excerpt'] ?? null,
            'body'         => $data['body'],
            'category'     => $data['category'],
            'cover_image'  => $data['cover_image'] ?? $article->cover_image,
            'status'       => 'review', // rinvia in revisione dopo modifica
            'read_minutes' => $data['read_minutes'] ?? max(1, (int) ceil($wordCount / 180)),
        ]);

        $this->notifyEditor($article, true);
        ActivityLog::record('Articolo modificato e inviato in revisione', 'article', $article->id, $article->title);

        return redirect()->route('redazione.articles')
            ->with('success', 'Articolo aggiornato e rimandato in revisione.');
    }

    public function destroy(Article $article)
    {
        if ($article->user_id !== auth()->id()) abort(403);
        if ($article->status === 'published') abort(403);

        $article->delete();
        return redirect()->route('redazione.articles')
            ->with('success', 'Articolo eliminato.');
    }

    private function notifyEditor(Article $article, bool $isUpdate = false): void
    {
        try {
            $editorEmail = \App\Models\User::where('role', 'editor')->value('email');
            if (!$editorEmail) return;

            $subject = $isUpdate
                ? '✏️ Articolo modificato — in attesa di revisione'
                : '📝 Nuovo articolo da revisionare — Quark';

            $reviewUrl = route('admin.articles.edit', $article);
            $author    = auth()->user()->name;
            $cat       = config('laboratorio.categories.' . $article->category);

            Mail::send([], [], function ($m) use ($editorEmail, $article, $subject, $reviewUrl, $author, $cat, $isUpdate) {
                $m->to($editorEmail)->subject($subject)->html("
                    <div style='font-family:Arial,sans-serif;max-width:540px;padding:1.5rem;'>
                        <h2 style='color:#0d9488;margin-bottom:.75rem;'>
                            " . ($isUpdate ? '✏️ Articolo modificato' : '📝 Nuovo articolo') . "
                        </h2>
                        <table style='width:100%;border-collapse:collapse;font-size:.875rem;margin-bottom:1rem;'>
                            <tr><td style='padding:.4rem 0;color:#6b7280;width:90px;'>Autore</td>
                                <td style='font-weight:600;'>{$author}</td></tr>
                            <tr><td style='padding:.4rem 0;color:#6b7280;'>Titolo</td>
                                <td style='font-weight:600;'>" . htmlspecialchars($article->title) . "</td></tr>
                            <tr><td style='padding:.4rem 0;color:#6b7280;'>Categoria</td>
                                <td>{$cat}</td></tr>
                        </table>
                        <a href='{$reviewUrl}' style='display:inline-block;background:#0d9488;color:#fff;
                            padding:.65rem 1.25rem;border-radius:6px;text-decoration:none;font-weight:600;'>
                            Revisiona articolo →
                        </a>
                    </div>
                ");
            });
        } catch (\Exception $e) {
            // Silenzioso
        }
    }
}