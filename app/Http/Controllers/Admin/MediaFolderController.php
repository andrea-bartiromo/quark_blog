<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaFolderRequest;
use App\Models\MediaFolder;
use App\Services\MediaFolderService;
use DomainException;
use Throwable;

class MediaFolderController extends Controller
{
    public function __construct(private readonly MediaFolderService $mediaFolderService) {}

    public function store(StoreMediaFolderRequest $request)
    {
        $parent = $request->filled('parent_id')
            ? MediaFolder::findOrFail($request->integer('parent_id'))
            : null;

        try {
            $folder = $this->mediaFolderService->create(
                $request->user(),
                $request->string('name')->toString(),
                $parent,
                $request->input('description'),
                $request->input('icon')
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['name' => $exception->getMessage()]);
        } catch (Throwable) {
            return back()->withInput()->withErrors(['name' => 'La categoria non può essere creata sul filesystem o nel database.']);
        }

        return redirect()
            ->route('admin.media', ['folder' => $folder->id])
            ->with('success', 'Categoria immagini creata.');
    }

    public function destroy(MediaFolder $mediaFolder)
    {
        try {
            $this->mediaFolderService->delete($mediaFolder);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable) {
            return back()->with('error', 'La categoria non può essere eliminata in modo sicuro.');
        }

        return redirect()
            ->route('admin.media', $mediaFolder->parent_id ? ['folder' => $mediaFolder->parent_id] : [])
            ->with('success', 'Categoria immagini eliminata.');
    }
}
