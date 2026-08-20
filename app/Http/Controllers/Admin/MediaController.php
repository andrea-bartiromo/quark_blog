<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\ImageService;
use App\Services\MediaFolderService;
use App\Services\MediaMoveService;
use App\Services\MediaReferenceService;
use App\Services\MediaStatsService;
use App\Services\MediaUsageService;
use App\Services\PublicMediaSyncService;
use App\Services\ResponsiveImageVariantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class MediaController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaFolderService $mediaFolderService,
        private readonly MediaMoveService $mediaMoveService,
        private readonly MediaReferenceService $mediaReferenceService,
        private readonly MediaUsageService $mediaUsageService,
        private readonly MediaStatsService $mediaStatsService,
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly ResponsiveImageVariantService $responsiveImageVariants,
    ) {}

    public function index(Request $request)
    {
        // 'folder' non e validato qui: MediaFolder::findOrFail() sotto deve
        // continuare a restituire 404 per un ID inesistente (comportamento
        // preesistente e testato), non un redirect di validazione.
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:jpeg,png,webp,gif',
            'category' => 'nullable|in:images,documents,others',
            'sort' => 'nullable|in:newest,oldest,name_asc,name_desc,size_desc,size_asc',
        ]);

        $currentFolder = $request->filled('folder')
            ? MediaFolder::findOrFail($request->integer('folder'))
            : null;
        $allFolders = $this->mediaFolderService->orderedHierarchy();
        $foldersById = $allFolders->keyBy('id');
        $folders = MediaFolder::query()
            ->where('parent_id', $currentFolder?->id)
            ->withCount('children')
            ->ordered()
            ->get();
        $folderCounts = $this->mediaFolderService->directMediaCounts($folders);

        $query = $this->mediaFolderService
            ->scopeDirectMedia(Media::query(), $currentFolder)
            ->with('user');

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $escapedSearch = $this->escapeLike($search);

            $query->where(function (Builder $builder) use ($escapedSearch) {
                $builder
                    ->whereRaw("filename LIKE ? ESCAPE '!'", ['%'.$escapedSearch.'%'])
                    ->orWhereRaw("disk_name LIKE ? ESCAPE '!'", ['%'.$escapedSearch.'%'])
                    ->orWhereRaw("alt_text LIKE ? ESCAPE '!'", ['%'.$escapedSearch.'%']);
            });
        }

        $type = $validated['type'] ?? null;
        if ($type) {
            $mimeTypes = match ($type) {
                'jpeg' => ['image/jpeg', 'image/jpg'],
                'png' => ['image/png'],
                'webp' => ['image/webp'],
                'gif' => ['image/gif'],
            };

            $query->whereIn('mime_type', $mimeTypes);
        }

        $category = $validated['category'] ?? null;
        match ($category) {
            'images' => $query->images(),
            'documents' => $query->documents(),
            'others' => $query->others(),
            default => null,
        };

        $sort = $validated['sort'] ?? 'newest';
        match ($sort) {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('filename')->orderBy('id'),
            'name_desc' => $query->orderByDesc('filename')->orderByDesc('id'),
            'size_desc' => $query->orderByDesc('size')->orderByDesc('id'),
            'size_asc' => $query->orderBy('size')->orderBy('id'),
            default => $query->latest(),
        };

        $files = $query
            ->paginate(24)
            ->withQueryString();

        // Una sola passata batch (poche query, non una per card) per l'intera
        // pagina corrente: vedi MediaUsageService per il dettaglio.
        $usageByDiskName = $this->mediaUsageService->usageForMany($files->getCollection());

        return view('admin.media', [
            'files' => $files,
            'folders' => $folders,
            'allFolders' => $allFolders,
            'foldersById' => $foldersById,
            'folderCounts' => $folderCounts,
            'currentFolder' => $currentFolder,
            'breadcrumb' => $currentFolder?->parentChain($foldersById) ?? collect(),
            'defaultFolder' => MediaFolder::where('path', '_da-classificare')->first(),
            'search' => $search,
            'type' => $type,
            'category' => $category,
            'sort' => $sort,
            'hasActiveFilters' => $search !== '' || $type !== null || $category !== null || $sort !== 'newest',
            'usageByDiskName' => $usageByDiskName,
            'stats' => $this->mediaStatsService->global(),
        ]);
    }

    /**
     * Metadati editoriali di un media esistente, individuato per disk_name
     * (non per id: il form articolo conosce solo il percorso salvato in
     * cover_image). Usato per precompilare i campi copertina dell'articolo
     * quando l'autore seleziona un'immagine già presente in libreria, senza
     * sovrascrivere valori che l'autore ha già personalizzato (la logica di
     * "non sovrascrivere" vive lato client, qui si restituiscono solo i dati).
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'disk_name' => 'required|string|max:255',
        ]);

        $media = Media::where('disk_name', $request->string('disk_name'))->first();

        if (! $media) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'credit' => $media->credit,
            'source' => $media->source,
            'source_url' => $media->source_url,
            'license' => $media->license,
        ]);
    }

    public function store(Request $request)
{
    $request->validate([
        'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
        'alt_text' => 'nullable|string|max:200',
        'media_folder_id' => 'nullable|integer|exists:media_folders,id',
    ]);

    $folder = $request->exists('media_folder_id')
        ? (
            $request->filled('media_folder_id')
                ? MediaFolder::findOrFail($request->integer('media_folder_id'))
                : null
        )
        : $this->mediaFolderService->defaultUploadFolder($request->user());

    $file = $request->file('image');

    $original = $file->getClientOriginalName();

    /*
     * L'estensione viene ricavata dal MIME rilevato dal server,
     * non dal nome originale inviato dal browser.
     */
    $ext = $this->imageService->safeExtension(
        $file,
        allowGif: true
    );

    $mimeType = $file->getMimeType();

    $diskName = $this->imageService->buildFileName(
        $file,
        $ext,
        now()->format('YmdHis').'-'.Str::random(6)
    );

    $uploadPath = $this->mediaFolderService->ensureDirectoryFor($folder);

    $fullPath = $this->imageService->upload(
        $file,
        $uploadPath,
        $diskName
    );

    $diskName = $this->mediaFolderService->diskName(
        $folder,
        $diskName
    );

    /*
     * FASE 5 (missione WebP): un nuovo upload JPG/PNG viene convertito
     * automaticamente in WebP prima di essere pubblicato, cosi' i nuovi
     * upload smettono di far crescere lo storage in formati piu'
     * pesanti. GIF e WebP restano invariati (autoConvertToWebpIfEligible
     * e' un no-op sicuro per entrambi); se la conversione fallisce per
     * qualunque motivo, si ricade sul comportamento preesistente
     * (ottimizzazione nello stesso formato).
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

    try {
        $this->publicMediaSync->create($fullPath, $diskName);
    } catch (RuntimeException $exception) {
        $this->publicMediaSync->cleanupAfterFailedCreate($fullPath);
        report($exception);

        $message = 'Impossibile pubblicare il file caricato. Riprova o contatta l\'assistenza.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => false, 'error' => $message], 500);
        }

        return back()->with('error', $message);
    }

    /*
     * FASE 5 (missione S2 responsive images): accessoria e best-effort,
     * eseguita DOPO che il file principale e' gia' pubblicato e verificato
     * — un suo fallimento non deve mai impedire la registrazione del Media.
     */
    $this->responsiveImageVariants->generateForUpload($fullPath, $diskName);

    // S9 — a questo punto il file e' gia' scritto, ottimizzato e pubblicato
    // (locale + eventuale radice pubblica secondaria), e le eventuali
    // varianti responsive sono gia' state generate: un fallimento di
    // Media::create() lascerebbe un file live, pubblicamente raggiungibile
    // via URL (piu' le sue varianti), senza ALCUNA riga Media che lo
    // referenzi — non gestibile ne' individuabile dalla Libreria media.
    // Stessa pulizia gia' usata sopra per un fallimento di
    // publicMediaSync->create(), estesa qui anche alle varianti responsive:
    // senza questa riga le varianti resterebbero orfane sul filesystem
    // anche dopo il rollback del file principale.
    try {
        $media = Media::create([
            'user_id' => auth()->id(),
            'filename' => $original,
            'disk_name' => $diskName,
            'mime_type' => $mimeType,
            'size' => filesize($fullPath) ?: 0,
            'alt_text' => $request->input('alt_text'),
        ]);
    } catch (\Throwable $exception) {
        $this->publicMediaSync->cleanupAfterFailedCreate($fullPath);
        $this->responsiveImageVariants->deleteForDiskName($diskName);

        try {
            $this->publicMediaSync->delete($diskName);
        } catch (RuntimeException $syncDeleteException) {
            report($syncDeleteException);
        }

        throw $exception;
    }

    if ($request->expectsJson() || $request->ajax()) {
        return response()->json([
            'ok' => true,
            'filename' => $diskName,
            'url' => asset('assets/img/'.$diskName),
            'id' => $media->id,
        ]);
    }

    return back()->with(
        'success',
        "Immagine \"{$original}\" caricata con successo."
    );
}

    public function update(UpdateMediaRequest $request, Media $media)
    {
        $media->update($request->validated());

        return back()->with('success', "Dettagli di \"{$media->filename}\" aggiornati.");
    }

    public function destroy(Media $media)
    {
        if ($media->isProtected()) {
            return back()->with('error', 'Questo file è protetto: è un riferimento statico usato nel codice del sito e non può essere eliminato.');
        }

        $usage = $this->mediaUsageService->usageFor($media);
        if ($usage !== []) {
            $count = count($usage);
            $noun = $count === 1 ? 'contenuto' : 'contenuti';

            return back()->with('error', "Questa immagine è utilizzata in {$count} {$noun} e non può essere eliminata finché è ancora collegata.");
        }

        try {
            $this->publicMediaSync->delete($media->disk_name);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Impossibile completare l\'eliminazione: la copia pubblica del file non può essere rimossa. Riprova.');
        }

        $path = public_path('assets/img/'.$media->disk_name);
        if (file_exists($path)) {
            unlink($path);
        }

        // Le varianti responsive non hanno un proprio record Media (vedi
        // ResponsiveImageVariantService): vanno ripulite esplicitamente qui,
        // altrimenti sopravviverebbero come orfani permanenti mai
        // referenziati da nulla.
        $this->responsiveImageVariants->deleteForDiskName($media->disk_name);

        $media->delete();

        return back()->with('success', 'Immagine eliminata.');
    }

    public function movePreflight(Request $request, Media $media)
    {
        $request->validate([
            'media_folder_id' => 'nullable|integer|exists:media_folders,id',
        ]);

        $destination = $request->filled('media_folder_id')
            ? MediaFolder::findOrFail($request->integer('media_folder_id'))
            : null;

        $newDiskName = $this->mediaFolderService->diskName($destination, basename($media->disk_name));

        if ($newDiskName === $media->disk_name) {
            return response()->json([
                'old_disk_name' => $media->disk_name,
                'new_disk_name' => $newDiskName,
                'is_noop' => true,
                'can_move' => true,
                'updatable_references' => [],
                'blocking_references' => [],
                'informational_references' => [],
                'total_usage_count' => 0,
            ]);
        }

        $preflight = $this->mediaReferenceService->preflight($media, $newDiskName);

        return response()->json([
            'old_disk_name' => $media->disk_name,
            'new_disk_name' => $newDiskName,
            'is_noop' => false,
            ...$preflight,
        ]);
    }

    public function move(MoveMediaRequest $request, Media $media)
    {
        $result = $this->mediaMoveService->move(
            $media->id,
            $request->filled('media_folder_id') ? $request->integer('media_folder_id') : null,
            $request->user()?->id
        );

        if ($result->isBlocked()) {
            $blockedCount = count($result->preflight['blocking_references']);

            return back()->with('error', "Spostamento bloccato: {$blockedCount} riferimento/i non aggiornabile/i in sicurezza.");
        }

        if ($result->isNoop()) {
            return back()->with('success', $result->message);
        }

        return back()->with('success', 'Immagine spostata in "'.$result->newDiskName.'".');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
