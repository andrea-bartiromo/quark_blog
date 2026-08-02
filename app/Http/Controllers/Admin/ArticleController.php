<?php

/**
 * Kairus — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://kairus.it
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Category;
use App\Services\ImageService;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaService $mediaService
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $query = Article::latest()->with('author');

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return view('admin.articles', [
            'articles' => $query->get(),
        ]);
    }

    public function create()
    {
        return view('admin.article-form', [
            'article' => null,
            'categories' => Category::options(),
        ]);
    }

    public function store(StoreArticleRequest $request)
    {
        $data = $this->applyBusinessRules(
            $request,
            $request->validated()
        );

        Article::create(
            $data + [
                'user_id' => auth()->id(),
            ]
        );

        return redirect()
            ->route('admin.articles')
            ->with('success', 'Articolo creato.');
    }

    public function edit(Article $article)
    {
        return view('admin.article-form', [
            'article' => $article,
            'categories' => Category::options(),
        ]);
    }

    public function update(
        UpdateArticleRequest $request,
        Article $article
    ) {
        $article->update(
            $this->applyBusinessRules(
                $request,
                $request->validated()
            )
        );

        return redirect()
            ->route('admin.articles')
            ->with('success', 'Articolo aggiornato.');
    }

    public function destroy(Article $article)
    {
        ActivityLog::record(
            'Articolo eliminato',
            'article',
            $article->id,
            $article->title
        );

        $article->delete();

        return redirect()
            ->route('admin.articles')
            ->with('success', 'Articolo eliminato.');
    }

    private function applyBusinessRules(
        Request $request,
        array $data
    ): array {
        if (
            $request->hasFile('cover_image_upload')
            && $request->file('cover_image_upload')->isValid()
        ) {
            $file = $request->file('cover_image_upload');

            $filename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();

            /*
             * L'estensione viene ricavata dal MIME rilevato
             * dal server, non dal nome inviato dal browser.
             *
             * Le copertine accettano solo JPG, PNG e WebP.
             */
            $ext = $this->imageService->safeExtension($file);

            $diskName = $this->imageService->buildFileName(
                $file,
                $ext,
                now()->format('YmdHis').'-'.Str::random(6)
            );

            $uploadPath = public_path('assets/img');

            $fullPath = $this->imageService->upload(
                $file,
                $uploadPath,
                $diskName
            );

            /*
             * Ricodifica e ottimizza l'immagine.
             * Questo uniforma le copertine e rimuove
             * eventuali contenuti estranei incorporati.
             */
            $this->imageService->resizeAndCompress(
                $fullPath,
                $ext,
                1600,
                [
                    'jpg' => 82,
                    'png' => 7,
                    'webp' => 82,
                ],
                preserveTransparency: true,
                alwaysReencode: true,
                logErrors: true
            );

            $this->mediaService->register(
                $request->user(),
                $filename,
                $diskName,
                $mimeType,
                filesize($fullPath) ?: 0
            );

            $data['cover_image'] = $diskName;
        }

        unset($data['cover_image_upload']);

        $data['slug'] = Str::slug($data['title']);
        $data['featured'] = $request->boolean('featured');

        $data['published_at'] = $data['status'] === 'published'
            ? now()
            : null;

        if (! empty($data['body'])) {
            $wordCount = str_word_count(
                strip_tags($data['body'])
            );

            $data['read_minutes'] = max(
                1,
                (int) round($wordCount / 200)
            );
        }

        return $data;
    }

    public function updateVerification(
        Request $request,
        Article $article
    ) {
        $validated = $request->validate([
            'verification_status' => [
                'required',
                'in:unverified,in_progress,verified,needs_update',
            ],
            'verification_notes' => 'nullable|max:1000',
            'primary_sources' => 'nullable|max:500',
        ]);

        $data = $validated;

        if ($validated['verification_status'] === 'verified') {
            $data['verified_at'] = now();
            $data['verified_by'] = auth()->user()->name;
        }

        $article->update($data);

        return back()->with(
            'success',
            'Stato verifica aggiornato.'
        );
    }

    public function duplicate(Article $article)
    {
        $newArticle = $article->replicate();

        $newArticle->title = 'Copia di — '.$article->title;
        $newArticle->slug = Str::slug($newArticle->title).'-'.time();
        $newArticle->status = 'draft';
        $newArticle->featured = false;
        $newArticle->views = 0;
        $newArticle->published_at = null;
        $newArticle->verification_status = 'unverified';

        $newArticle->push();

        ActivityLog::record(
            'Articolo duplicato',
            'article',
            $newArticle->id,
            $newArticle->title
        );

        return redirect()
            ->route('admin.articles.edit', $newArticle)
            ->with(
                'success',
                'Articolo duplicato come bozza.'
            );
    }

    public function quickDraft()
    {
        return view('admin.quick-draft');
    }
}