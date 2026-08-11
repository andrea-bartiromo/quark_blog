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
use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\ImageService;
use App\Services\MediaRetirementService;
use App\Services\MediaService;
use App\Services\PublicMediaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaService $mediaService,
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly ArticleLinkSuggestionService $linkSuggestionService,
        private readonly ArticleLinkInsertionService $linkInsertionService,
        private readonly EditorialQualityChecker $qualityChecker,
        private readonly MediaRetirementService $mediaRetirementService,
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

        $articles = $query->get();

        // Indicatore "collegamenti ad articoli": limitato ai soli articoli
        // programmati (il caso d'uso richiesto — capire prima della
        // pubblicazione se un pezzo ha già collegato altri articoli
        // Kairus). Il parsing DOM del body ha un costo per riga non
        // trascurabile su liste grandi (vedi PR #141); qui resta limitato
        // al sottoinsieme "programmato", tipicamente poche unità, non
        // all'intera lista (che oggi non è paginata). Nessuna query
        // aggiuntiva: countArticleLinks() opera sul body già caricato
        // dalla query principale sopra.
        //
        // Conta SOLO i link verso altri articoli Kairus (/articolo/{slug}),
        // non qualunque link interno (homepage/categorie/pagine statiche
        // incluse, come faceva countInternalLinks() usato qui prima —
        // ambiguità rilevata in audit, corretta con decisione prodotto B:
        // "Collegamenti ad articoli"). Stessa definizione, stessa funzione
        // condivisa con App\Services\ArticleLinkSuggestionService (mai due
        // implementazioni divergenti di "collegamento ad articolo") — vedi
        // ArticleLinkInsertionService::linkedArticleSlugsInBody().
        foreach ($articles as $article) {
            if ($article->isScheduled()) {
                $article->article_links_count = $this->linkInsertionService->countArticleLinks((string) $article->body);
            }
        }

        return view('admin.articles', [
            'articles' => $articles,
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
        try {
            $data = $this->applyBusinessRules(
                $request,
                $request->validated()
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['cover_image_upload' => 'Impossibile pubblicare la nuova copertina. Riprova o contatta l\'assistenza.']);
        }

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
            'linkSuggestions' => $article->proposedLinkSuggestions(),
            'qualityReport' => $this->qualityChecker->check($article),
        ]);
    }

    public function update(
        UpdateArticleRequest $request,
        Article $article
    ) {
        try {
            $data = $this->applyBusinessRules(
                $request,
                $request->validated()
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['cover_image_upload' => 'Impossibile pubblicare la nuova copertina. Riprova o contatta l\'assistenza.']);
        }

        try {
            // Codex (PR #165, P2 round 9): il salvataggio dell'articolo e
            // la conseguente revalidazione/pulizia dei suggerimenti
            // applicati (markAccepted() può a sua volta salvare di nuovo il
            // body se un link non è più sicuro — vedi
            // ArticleLinkSuggestionService) devono avvenire come un'unica
            // unità: senza transazione, un fallimento tra i due update()
            // lascerebbe l'articolo pubblicato con un link ormai non
            // sicuro ancora nel testo, o un suggerimento già marcato
            // superato senza che il body sia stato ripulito.
            DB::transaction(function () use ($article, $data, $request) {
                $article->update($data);

                $this->linkSuggestionService->markAccepted(
                    $article,
                    (array) $request->input('applied_link_suggestions', []),
                    $request->user()->id
                );
            });
        } catch (\Throwable $exception) {
            // Codex (PR #165, round 18): applyBusinessRules() carica la
            // nuova copertina, la sincronizza sulla radice pubblica
            // secondaria e registra il Media PRIMA che questa transazione
            // inizi — un fallimento al suo interno (es. markAccepted())
            // annulla solo l'update() dell'Article, non quei side effect
            // già scritti su filesystem/DB. Senza questa pulizia
            // resterebbero un file orfano e una riga Media senza alcun
            // articolo che la referenzi. retireIfUnused() è già lo
            // strumento esistente per questo esatto scenario (ritiro
            // sicuro di un file media non più referenziato): appena
            // caricato in questa stessa richiesta, non può essere
            // referenziato altrove.
            if (array_key_exists('cover_image', $data)) {
                $this->mediaRetirementService->retireIfUnused(
                    $data['cover_image'],
                    'article_update_transaction_rolled_back'
                );
            }

            throw $exception;
        }

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
             * FASE 5 (missione WebP): un nuovo upload JPG/PNG viene
             * convertito automaticamente in WebP prima di essere
             * pubblicato. Se la conversione non si applica (gia' WebP) o
             * fallisce, si ricade sulla ricodifica/ottimizzazione nello
             * stesso formato che uniforma comunque le copertine e rimuove
             * eventuali contenuti estranei incorporati.
             */
            $webpApplied = false;

            if (config('media.auto_webp_on_upload', true)) {
                $conversion = $this->imageService->autoConvertToWebpIfEligible(
                    $fullPath,
                    $ext,
                    (int) config('media.webp_quality', 82),
                    (int) config('media.webp_max_width', 1600)
                );

                $webpApplied = $conversion['webp_applied'];

                if ($webpApplied) {
                    $fullPath = $conversion['full_path'];
                    $ext = $conversion['ext'];
                    $mimeType = $conversion['mime_type'];
                    $diskName = $this->imageService->changeExtension($diskName, 'webp');
                }
            }

            if (! $webpApplied) {
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
            }

            /*
             * Replica la copertina nella document root pubblica secondaria
             * (public_html su cPanel), quando configurata: senza questo,
             * asset('assets/img/...') può puntare a un file assente sul
             * dominio pubblico anche se presente in public/assets/img.
             * Eseguito prima della registrazione in Libreria media, cosi
             * che un fallimento qui non lasci un Media senza articolo
             * associato che punta a un file non davvero raggiungibile.
             */
            try {
                $this->publicMediaSync->create($fullPath, $diskName);
            } catch (RuntimeException $exception) {
                $this->publicMediaSync->cleanupAfterFailedCreate($fullPath);

                throw $exception;
            }

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

        $data['published_at'] = match ($data['status']) {
            Article::STATUS_PUBLISHED => now(),
            Article::STATUS_SCHEDULED => Article::scheduledAtFromEditorialInput(
                $data['published_date'],
                $data['published_time']
            ),
            default => null,
        };

        unset($data['published_date'], $data['published_time']);

        // array_key_exists, non empty(): un body '0' e' valido (passa la
        // regola 'required' del FormRequest) ma empty('0') e' true in PHP,
        // il che salterebbe silenziosamente il calcolo per quel solo caso.
        if (array_key_exists('body', $data)) {
            $data['read_minutes'] = Article::calculateReadMinutes($data['body']);
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
