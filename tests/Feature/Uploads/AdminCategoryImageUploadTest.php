<?php

namespace Tests\Feature\Uploads;

use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class AdminCategoryImageUploadTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function categoryPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nuova Categoria',
            'slug' => '',
        ], $overrides);
    }

    public function test_authorized_editor_can_create_a_category_with_an_image(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('cat.jpg', 800, 600);

        $response = $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload([
            'image_upload' => $image,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Categoria creata con successo.');

        $category = Category::where('name', 'Nuova Categoria')->firstOrFail();
        $fullPath = public_path('assets/img/categories/'.$category->image);

        $this->assertNotNull($category->image);
        $this->assertFileExists($fullPath);

        $media = Media::where('disk_name', 'categories/'.$category->image)->firstOrFail();

        $this->assertSame($editor->id, $media->user_id);
        $this->assertSame('cat.jpg', $media->filename);
        $this->assertSame('categories/'.$category->image, $media->disk_name);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame(filesize($fullPath), $media->size);
    }

    public function test_image_upload_creates_the_categories_directory_when_missing(): void
    {
        $this->deleteDirectoryForTest(public_path('assets/img/categories'));
        $this->assertDirectoryDoesNotExist(public_path('assets/img/categories'));

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('cat.jpg', 400, 300);

        $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload([
            'image_upload' => $image,
        ]));

        $this->assertDirectoryExists(public_path('assets/img/categories'));
        $category = Category::where('name', 'Nuova Categoria')->firstOrFail();
        $this->assertFileExists(public_path('assets/img/categories/'.$category->image));
    }

    public function test_category_image_is_resized_to_the_1200px_limit(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('grande.jpg', 2000, 1000);

        $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload([
            'image_upload' => $image,
        ]));

        $category = Category::where('name', 'Nuova Categoria')->firstOrFail();
        [$w, $h] = getimagesize(public_path('assets/img/categories/'.$category->image));

        $this->assertSame(1200, $w);
        $this->assertSame(600, $h);
    }

    public function test_category_image_upload_is_optional(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload());

        $response->assertRedirect();

        $category = Category::where('name', 'Nuova Categoria')->firstOrFail();
        $this->assertNull($category->image);
    }

    public function test_validation_rejects_an_unsupported_image_format(): void
    {
        $editor = $this->editor();
        $bmp = UploadedFile::fake()->create('cat.bmp', 100, 'image/bmp');

        $response = $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload([
            'image_upload' => $bmp,
        ]));

        $response->assertSessionHasErrors('image_upload');
        $this->assertDatabaseMissing('categories', ['name' => 'Nuova Categoria']);
    }

    public function test_validation_rejects_an_image_over_the_size_limit(): void
    {
        $editor = $this->editor();
        $tooBig = UploadedFile::fake()->image('pesante.jpg')->size(4097);

        $response = $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload([
            'image_upload' => $tooBig,
        ]));

        $response->assertSessionHasErrors('image_upload');
        $this->assertDatabaseMissing('categories', ['name' => 'Nuova Categoria']);
    }

    public function test_update_with_a_new_image_replaces_the_reference_and_deletes_the_old_file(): void
    {
        $editor = $this->editor();
        $original = UploadedFile::fake()->image('originale.jpg', 400, 300);

        $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload([
            'image_upload' => $original,
        ]));
        $category = Category::where('name', 'Nuova Categoria')->firstOrFail();
        $oldImage = $category->image;
        $oldPath = public_path('assets/img/categories/'.$oldImage);
        $this->assertFileExists($oldPath);

        $newImage = UploadedFile::fake()->image('nuova.jpg', 400, 300);
        $response = $this->actingAs($editor)->put(route('admin.categories.update', $category), $this->categoryPayload([
            'image_upload' => $newImage,
        ]));

        $response->assertRedirect();

        $category->refresh();
        $this->assertNotSame($oldImage, $category->image);
        $this->assertFileExists(public_path('assets/img/categories/'.$category->image));
        $this->assertFileDoesNotExist($oldPath);
    }

    public function test_update_with_remove_image_deletes_the_current_file(): void
    {
        $editor = $this->editor();
        $original = UploadedFile::fake()->image('originale.jpg', 400, 300);

        $this->actingAs($editor)->post(route('admin.categories.store'), $this->categoryPayload([
            'image_upload' => $original,
        ]));
        $category = Category::where('name', 'Nuova Categoria')->firstOrFail();
        $oldPath = public_path('assets/img/categories/'.$category->image);
        $this->assertFileExists($oldPath);

        $response = $this->actingAs($editor)->put(route('admin.categories.update', $category), $this->categoryPayload([
            'remove_image' => '1',
        ]));

        $response->assertRedirect();

        $category->refresh();
        $this->assertNull($category->image);
        $this->assertFileDoesNotExist($oldPath);
    }

    private function deleteDirectoryForTest(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteDirectoryForTest($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
