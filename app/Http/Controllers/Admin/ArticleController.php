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
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Models\Article;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function __construct(private readonly ImageService $imageService)
    {
    }

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
            'categories' => Category::options(),
        ]);
    }

    public function store(StoreArticleRequest $request)
    {
        $data = $this->applyBusinessRules($request, $request->validated());
        Article::create($data + ['user_id' => auth()->id()]);

        return redirect()->route('admin.articles')->with('success', 'Articolo creato.');
    }

    public function edit(Article $article)
    {
        return view('admin.article-form', [
            'article'    => $article,
            'categories' => Category::options(),
        ]);
    }

    public function update(UpdateArticleRequest $request, Article $article)
    {
        $article->update($this->applyBusinessRules($request, $request->validated()));

        return redirect()->route('admin.articles')->with('success', 'Articolo aggiornato.');
    }

    public function destroy(Article $article)
    {
        ActivityLog::record('Articolo eliminato', 'article', $article->id, $article->title);
        $article->delete();

        return redirect()->route('admin.articles')->with('success', 'Articolo eliminato.');
    }

    private function applyBusinessRules(Request $request, array $data): array
    {
        if ($request->hasFile('cover_image_upload') && $request->file('cover_image_upload')->isValid()) {
            $file     = $request->file('cover_image_upload');
            $ext      = strtolower($file->getClientOriginalExtension());
            $diskName = $this->imageService->buildFileName(
                $file,
                $ext,
                date('YmdHis') . '-' . substr(md5(rand()), 0, 6)
            );
            $uploadPath = public_path('assets/img');
            $fullPath = $this->imageService->upload($file, $uploadPath, $diskName);

            $this->imageService->resizeAndCompress(
                $fullPath,
                $ext,
                1600,
                ['jpg' => 82, 'png' => 7, 'webp' => 82]
            );

            $data['cover_image'] = $diskName;
        }

        unset($data['cover_image_upload']);

        $data['slug']         = Str::slug($data['title']);
        $data['featured']     = $request->boolean('featured');
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        if (!empty($data['body'])) {
            $wordCount = str_word_count(strip_tags($data['body']));
            $data['read_minutes'] = max(1, (int) round($wordCount / 200));
        }

        return $data;
    }

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
