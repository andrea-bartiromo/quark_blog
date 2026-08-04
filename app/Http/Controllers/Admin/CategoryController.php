<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Services\ImageService;
use App\Services\MediaRetirementService;
use App\Services\MediaService;
use App\Services\PublicMediaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaService $mediaService,
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly MediaRetirementService $mediaRetirement
    ) {}

    public function index()
    {
        return view('admin.categories', [
            'categories' => Category::ordered()->withCount('articles')->get(),
        ]);
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

        // Ritirata solo ora che non è più una categoria a puntarci: una
        // categoria appena creata non aveva comunque un'immagine precedente,
        // ma manteniamo lo stesso ordine di update() per coerenza.
        $this->retirePreviousImage($imageToRetire);

        return back()->with('success', 'Categoria creata con successo.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category->id);

        try {
            [$data, $imageToRetire] = $this->handleImageUpload($request, $data, $category);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['image_upload' => 'Impossibile pubblicare la nuova immagine. Riprova o contatta l\'assistenza.']);
        }

        $oldSlug = $category->slug;

        $category->update($data);

        if ($oldSlug !== $category->slug) {
            Article::where('category', $oldSlug)
                ->update(['category' => $category->slug]);
        }

        /*
         * Ritirata solo dopo che la categoria è già stata salvata con il
         * nuovo valore (o null): a questo punto il controllo "è ancora
         * referenziata?" non troverà più questa stessa categoria puntare
         * all'immagine precedente.
         */
        $this->retirePreviousImage($imageToRetire);

        return back()->with('success', 'Categoria aggiornata.');
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

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => 'required|max:100',
            'slug' => 'nullable|max:120|unique:categories,slug,'.$ignoreId,
            'description' => 'nullable|max:500',
            'image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'remove_image' => 'nullable|boolean',
            'color' => 'nullable|max:20',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        unset($validated['image_upload'], $validated['remove_image']);

        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    /**
     * @return array{0: array<string, mixed>, 1: ?string} i dati aggiornati e,
     *                                                     se presente, il
     *                                                     disk_name
     *                                                     dell'immagine
     *                                                     precedente da
     *                                                     ritirare (solo
     *                                                     dopo che la
     *                                                     categoria è stata
     *                                                     salvata: vedi
     *                                                     store()/update()).
     */
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
            $fileName = $this->imageService->buildFileName(
                $file,
                $ext,
                date('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6)
            );
            $uploadPath = public_path('assets/img/categories');

            $this->imageService->ensureDirectoryExists($uploadPath, 0755);

            $fullPath = $this->imageService->upload($file, $uploadPath, $fileName);
            $this->imageService->resizeAndCompress(
                $fullPath,
                $ext,
                1200,
                ['jpg' => 84, 'png' => 7, 'webp' => 84]
            );

            /*
             * Come per le copertine articolo: sincronizza verso la
             * document root pubblica secondaria prima di registrare il
             * Media, cosi' un fallimento qui non lascia un riferimento
             * a un file non davvero raggiungibile.
             */
            $this->publicMediaSync->create($fullPath, 'categories/'.$fileName);

            $this->mediaService->register(
                $request->user(),
                $originalName,
                'categories/'.$fileName,
                $mimeType,
                filesize($fullPath)
            );

            $data['image'] = $fileName;
        }

        return [$data, $imageToRetire];
    }

    /**
     * Ritira, se non più referenziata da nessun'altra categoria/articolo/
     * pagina, l'immagine categoria sostituita o rimossa. Best-effort (vedi
     * MediaRetirementService): un fallimento non blocca mai l'azione
     * principale già completata sulla categoria.
     */
    private function retirePreviousImage(?string $fileName): void
    {
        if (! $fileName) {
            return;
        }

        $this->mediaRetirement->retireIfUnused('categories/'.$fileName, 'category_image_replaced');
    }
}
