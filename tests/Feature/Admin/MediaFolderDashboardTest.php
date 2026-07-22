<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Services\MediaFolderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaFolderDashboardTest extends TestCase
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

    public function test_editor_can_create_a_root_and_nested_empty_category(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.media-folders.store'), [
            'name' => 'Energie rinnovabili',
            'description' => 'Raccolta editoriale',
            'icon' => '⚡',
        ]);

        $root = MediaFolder::where('path', 'energie-rinnovabili')->firstOrFail();
        $response->assertRedirect(route('admin.media', ['folder' => $root->id]));

        $this->actingAs($editor)->post(route('admin.media-folders.store'), [
            'name' => 'Solare',
            'parent_id' => $root->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('media_folders', [
            'path' => 'energie-rinnovabili/solare',
            'parent_id' => $root->id,
            'created_by' => $editor->id,
        ]);
        $this->assertDirectoryExists(public_path('assets/img/energie-rinnovabili/solare'));
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_category_names_are_rejected(string $name): void
    {
        $this->actingAs($this->editor())
            ->from(route('admin.media'))
            ->post(route('admin.media-folders.store'), ['name' => $name])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, MediaFolder::count());
    }

    public static function invalidNames(): array
    {
        return [
            'slash' => ['bad/name'],
            'backslash' => ['bad\\name'],
            'traversal' => ['..'],
            'null byte' => ["bad\0name"],
            'empty slug' => ['🔥🔥'],
        ];
    }

    public function test_duplicate_and_fourth_level_are_rejected_but_same_name_under_other_parent_is_allowed(): void
    {
        $service = app(MediaFolderService::class);
        $editor = $this->editor();
        $one = $service->create($editor, 'Uno');
        $two = $service->create($editor, 'Due');
        $service->create($editor, 'Figlia', $one);

        $this->actingAs($editor)->post(route('admin.media-folders.store'), [
            'name' => 'Figlia', 'parent_id' => $one->id,
        ])->assertSessionHasErrors('name');

        $this->actingAs($editor)->post(route('admin.media-folders.store'), [
            'name' => 'Figlia', 'parent_id' => $two->id,
        ])->assertSessionHasNoErrors();

        $levelTwo = MediaFolder::where('path', 'due/figlia')->firstOrFail();
        $levelThree = $service->create($editor, 'Tre', $levelTwo);

        $this->actingAs($editor)->post(route('admin.media-folders.store'), [
            'name' => 'Quattro', 'parent_id' => $levelThree->id,
        ])->assertSessionHasErrors('name');
    }

    public function test_missing_parent_and_unwritable_media_root_are_reported_without_records(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.media-folders.store'), [
            'name' => 'Orfana', 'parent_id' => 999999,
        ])->assertSessionHasErrors('parent_id');

        rmdir(public_path('assets/img/categories'));
        rmdir(public_path('assets/img'));
        file_put_contents(public_path('assets/img'), 'not a directory');

        $this->actingAs($editor)->post(route('admin.media-folders.store'), [
            'name' => 'Impossibile',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('media_folders', ['path' => 'impossibile']);
    }

    public function test_author_cannot_create_or_delete_media_folders(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $folder = MediaFolder::create(['name' => 'Manuale', 'slug' => 'manuale', 'path' => 'manuale']);

        $this->actingAs($author)->post(route('admin.media-folders.store'), ['name' => 'Negata'])
            ->assertRedirect(route('redazione.dashboard'));
        $this->actingAs($author)->delete(route('admin.media-folders.destroy', $folder))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_root_and_folder_navigation_only_show_direct_content_with_breadcrumb(): void
    {
        $editor = $this->editor();
        $turing = MediaFolder::create(['name' => 'Turing', 'slug' => 'turing', 'path' => 'turing', 'sort_order' => 10]);
        $enigma = MediaFolder::create(['name' => 'Enigma', 'slug' => 'enigma', 'path' => 'turing/enigma', 'parent_id' => $turing->id]);
        $this->media($editor, 'root-direct.jpg');
        $this->media($editor, 'turing/direct.jpg');
        $this->media($editor, 'turing/enigma/deep.jpg');

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Turing')
            ->assertSee('root-direct.jpg')
            ->assertDontSee('turing/direct.jpg')
            ->assertDontSee('deep.jpg');

        $this->actingAs($editor)->get(route('admin.media', ['folder' => $turing->id]))
            ->assertOk()
            ->assertSee('Enigma')
            ->assertSee('turing/direct.jpg')
            ->assertDontSee('deep.jpg')
            ->assertSee('Cartella superiore');

        $this->actingAs($editor)->get(route('admin.media', ['folder' => $enigma->id]))
            ->assertOk()
            ->assertSeeInOrder(['Libreria media', 'Turing', 'Enigma'])
            ->assertSee('turing/enigma/deep.jpg');
    }

    public function test_missing_folder_returns_404_and_pagination_keeps_folder_parameter(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);

        foreach (range(1, 25) as $index) {
            $this->media($editor, "archivio/file-{$index}.jpg");
        }

        $this->actingAs($editor)->get(route('admin.media', ['folder' => 999999]))->assertNotFound();
        $this->actingAs($editor)->get(route('admin.media', ['folder' => $folder->id]))
            ->assertOk()
            ->assertSee('folder='.$folder->id, false);
    }

    public function test_only_a_truly_empty_unprotected_folder_can_be_deleted(): void
    {
        $editor = $this->editor();
        $service = app(MediaFolderService::class);
        $empty = $service->create($editor, 'Vuota');
        $emptyPath = public_path('assets/img/vuota');

        $this->actingAs($editor)->delete(route('admin.media-folders.destroy', $empty))
            ->assertSessionHas('success');
        $this->assertDirectoryDoesNotExist($emptyPath);

        $protected = MediaFolder::create(['name' => 'Protetta', 'slug' => 'protetta', 'path' => 'protetta', 'is_protected' => true]);
        $this->actingAs($editor)->delete(route('admin.media-folders.destroy', $protected))
            ->assertSessionHas('error');

        $parent = $service->create($editor, 'Parent');
        $service->create($editor, 'Child', $parent);
        $this->actingAs($editor)->delete(route('admin.media-folders.destroy', $parent))
            ->assertSessionHas('error');

        $withMedia = $service->create($editor, 'Media');
        $this->media($editor, 'media/nested/image.jpg');
        $this->actingAs($editor)->delete(route('admin.media-folders.destroy', $withMedia))
            ->assertSessionHas('error');

        $withFile = $service->create($editor, 'File');
        file_put_contents(public_path('assets/img/file/unregistered.txt'), 'x');
        $this->actingAs($editor)->delete(route('admin.media-folders.destroy', $withFile))
            ->assertSessionHas('error');
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function media(User $user, string $diskName): Media
    {
        return Media::create([
            'user_id' => $user->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => 10,
        ]);
    }
}
