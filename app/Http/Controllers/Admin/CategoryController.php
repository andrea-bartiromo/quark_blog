<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Services\ImageService;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaService $mediaService
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
        $data = $this->handleImageUpload($request, $data);

        Category::create($data);

        return back()->with('success', 'Categoria creata con successo.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category->id);
        $data = $this->handleImageUpload($request, $data, $category);

        $oldSlug = $category->slug;

        $category->update($data);

        if ($oldSlug !== $category->slug) {
            Article::where('category', $oldSlug)
                ->update(['category' => $category->slug]);
        }

        return back()->with('success', 'Categoria aggiornata.');
    }

    public function destroy(Category $category)
    {
        if ($category->articles()->count() > 0) {
            return back()->with('error', 'Impossibile eliminare una categoria con articoli associati.');
        }

        $this->deleteImageFile($category->image);
        $category->delete();

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

    private function handleImageUpload(Request $request, array $data, ?Category $category = null): array
    {
        if ($request->boolean('remove_image') && $category?->image) {
            $this->deleteImageFile($category->image);
            $data['image'] = null;
        }

        if ($request->hasFile('image_upload') && $request->file('image_upload')->isValid()) {
            if ($category?->image) {
                $this->deleteImageFile($category->image);
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

            $this->mediaService->register(
                $request->user(),
                $originalName,
                'categories/'.$fileName,
                $mimeType,
                filesize($fullPath)
            );

            $data['image'] = $fileName;
        }

        return $data;
    }

    private function deleteImageFile(?string $fileName): void
    {
        if (! $fileName) {
            return;
        }

        $path = public_path('assets/img/categories/'.$fileName);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
