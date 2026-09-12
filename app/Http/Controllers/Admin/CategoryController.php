<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Services\CategoryDiscoveryPageData;
use App\Services\CategoryPublicationReadiness;
use App\Services\ImageService;
use App\Services\MediaRetirementService;
use App\Services\MediaService;
use App\Services\PublicMediaSyncService;
use App\Services\ResponsiveImageVariantService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaService $mediaService,
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly MediaRetirementService $mediaRetirement,
        private readonly ResponsiveImageVariantService $responsiveImageVariants,
        private readonly CategoryPublicationReadiness $readiness,
    ) {}

    public function index(Request $request)
    {
        if ($request->filled('modifica')) {
            $category = Category::withCount('articles')->findOrFail($request->integer('modifica'));

            // Sola anteprima, mai bloccante: vedi CategoryPublicationReadiness.
            return view('admin.categories-edit', [
                'category' => $category,
                'readiness' => $this->readiness->evaluate($category),
            ]);
        }

        return view('admin.categories', [
            'categories' => Category::ordered()->withCount('articles')->get(),
        ]);
    }

    /**
     * Cantiere 11 (programma 100-cantieri Kairus): anteprima di sola
     * lettura per una categoria bozza/programmata/disattivata, riservata
     * allo staff (dentro il gruppo di rotte auth+editor). Riusa la STESSA
     * vista pubblica categoria.blade.php con gli STESSI dati di
     * ArticleController::category() (via CategoryDiscoveryPageData), per
     * evitare che anteprima e pagina reale divergano nel tempo — l'unica
     * differenza è che qui il controllo isPubliclyVisible() viene
     * deliberatamente saltato, ed è la vista stessa a mostrare un banner
     * "anteprima" quando riceve previewMode=true.
     */
    public function preview(Request $request, Category $category, CategoryDiscoveryPageData $pageData)
    {
        $paginationQuery = $request->query();
        unset($paginationQuery['page']);

        $pageUrl = static fn (int $page): string => route('admin.categories.preview', array_merge(
            ['category' => $category->id],
            $paginationQuery,
            $page === 1 ? [] : ['page' => $page],
        ));

        $data = $pageData->build($request, $category->slug, $category, $pageUrl);

        abort_if($data === null, 404);

        return view('categoria', $data + ['previewMode' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            [$data, $imageToRetire] = $this->handleImageUpload($request, $data);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['image_upload' => 'Impossibile pubblicare la nuova immagine. Riprova o contatta l\'assistenza.']);
        }

        Category::create($data);
        $this->retirePreviousImage($imageToRetire);

        return back()->with('success', 'Categoria creata con successo.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);

        try {
            [$data, $imageToRetire] = $this->handleImageUpload($request, $data, $category);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['image_upload' => 'Impossibile pubblicare la nuova immagine. Riprova o contatta l\'assistenza.']);
        }

        $oldSlug = $category->slug;
        $category->update($data);

        if ($oldSlug !== $category->slug) {
            Article::where('category', $oldSlug)->update(['category' => $category->slug]);
        }

        $this->retirePreviousImage($imageToRetire);

        return redirect()->route('admin.categories', ['modifica' => $category->id])->with('success', 'Categoria aggiornata.');
    }

    public function destroy(Category $category)
    {
        if ($category->articles()->count() > 0) {
            return back()->with('error', 'Impossibile eliminare una categoria con articoli associati.');
        }

        $imageToRetire = $category->image;
        $category->delete();
        $this->retirePreviousImage($imageToRetire);

        return back()->with('success', 'Categoria eliminata.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $validated = $request->validate([
            'name' => 'required|max:100',
            'slug' => 'nullable|max:120|unique:categories,slug,'.$category?->id,
            'description' => 'nullable|max:500',
            'image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'remove_image' => 'nullable|boolean',
            'color' => 'nullable|max:20',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|in:'.implode(',', [Category::STATUS_DRAFT, Category::STATUS_SCHEDULED, Category::STATUS_PUBLISHED]),
            'scheduled_date' => 'nullable|date_format:Y-m-d|required_if:status,'.Category::STATUS_SCHEDULED,
            'scheduled_time' => 'nullable|date_format:H:i|required_if:status,'.Category::STATUS_SCHEDULED,
        ]);

        unset($validated['image_upload'], $validated['remove_image']);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['is_active'] = $request->boolean('is_active');

        $status = $validated['status'] ?? Category::STATUS_PUBLISHED;
        $validated['status'] = $status;

        // Una categoria che diventa "published" ORA (nuova creazione, oppure
        // transizione da bozza/programmata) pubblica sempre con published_at
        // = now(). Un salvataggio che lascia invariato uno status già
        // "published" NON deve invece mai spostare in avanti published_at:
        // altrimenti ogni piccola modifica (es. colore, descrizione)
        // sposterebbe il lastmod di sitemap.xml (SeoController::sitemap()),
        // che si affida a published_at come segnale editoriale stabile —
        // vedi Category::scopePubliclyVisible() e booted().
        $becomingPublishedNow = $status === Category::STATUS_PUBLISHED
            && ($category === null || $category->status !== Category::STATUS_PUBLISHED);

        $validated['published_at'] = match (true) {
            $status === Category::STATUS_DRAFT => null,
            $status === Category::STATUS_SCHEDULED => Category::scheduledAtFromEditorialInput(
                $validated['scheduled_date'],
                $validated['scheduled_time']
            ),
            $becomingPublishedNow => now(),
            default => $category?->published_at,
        };

        unset($validated['scheduled_date'], $validated['scheduled_time']);

        return $validated;
    }

    private function handleImageUpload(Request $request, array $data, ?Category $category = null): array
    {
        $imageToRetire = null;

        if ($request->boolean('remove_image') && $category?->image) {
            $imageToRetire = $category->image;
            $data['image'] = null;
        }

        if ($request->hasFile('image_upload') && $request->file('image_upload')->isValid()) {
            if ($category?->image) {
                $imageToRetire = $category->image;
            }

            $file = $request->file('image_upload');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $ext = strtolower($file->getClientOriginalExtension());
            $fileName = $this->imageService->buildFileName($file, $ext, date('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6));
            $uploadPath = public_path('assets/img/categories');

            $this->imageService->ensureDirectoryExists($uploadPath, 0755);
            $fullPath = $this->imageService->upload($file, $uploadPath, $fileName);
            $this->imageService->resizeAndCompress($fullPath, $ext, 1200, ['jpg' => 84, 'png' => 7, 'webp' => 84]);

            try {
                $this->publicMediaSync->create($fullPath, 'categories/'.$fileName);
            } catch (RuntimeException $exception) {
                $this->publicMediaSync->cleanupAfterFailedCreate($fullPath);
                throw $exception;
            }

            $this->responsiveImageVariants->generateForUpload($fullPath, 'categories/'.$fileName);
            $this->mediaService->register($request->user(), $originalName, 'categories/'.$fileName, $mimeType, filesize($fullPath));
            $data['image'] = $fileName;
        }

        return [$data, $imageToRetire];
    }

    private function retirePreviousImage(?string $fileName): void
    {
        if (! $fileName) {
            return;
        }

        $this->mediaRetirement->retireIfUnused('categories/'.$fileName, 'category_image_replaced');
    }
}
