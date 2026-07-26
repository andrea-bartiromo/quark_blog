<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveMediaRequest;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\ImageService;
use App\Services\MediaFolderService;
use App\Services\MediaMoveService;
use App\Services\MediaReferenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaFolderService $mediaFolderService,
        private readonly MediaMoveService $mediaMoveService,
        private readonly MediaReferenceService $mediaReferenceService
    ) {}

    public function index(Request $request)
    {
        // 'folder' non e validato qui: MediaFolder::findOrFail() sotto deve
        // continuare a restituire 404 per un ID inesistente (comportamento
        // preesistente e testato), non un redirect di validazione.
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:jpeg,png,webp,gif',
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
            'sort' => $sort,
            'hasActiveFilters' => $search !== '' || $type !== null || $sort !== 'newest',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'alt_text' => 'nullable|max:200',
            'media_folder_id' => 'nullable|integer|exists:media_folders,id',
        ]);

        $folder = $request->exists('media_folder_id')
            ? ($request->filled('media_folder_id') ? MediaFolder::findOrFail($request->integer('media_folder_id')) : null)
            : $this->mediaFolderService->defaultUploadFolder($request->user());

        $file = $request->file('image');
        $original = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        $diskName = $this->imageService->buildFileName(
            $file,
            $ext,
            now()->format('YmdHis').'-'.Str::random(6)
        );

        $uploadPath = $this->mediaFolderService->ensureDirectoryFor($folder);

        $fullPath = $this->imageService->upload($file, $uploadPath, $diskName);
        $diskName = $this->mediaFolderService->diskName($folder, $diskName);

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
