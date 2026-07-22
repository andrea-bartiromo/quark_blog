<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Services\MediaFolderService;
use Database\Seeders\MediaFolderSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaFolderTest extends TestCase
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

    public function test_media_folders_table_and_unique_path_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('media_folders', [
            'name', 'slug', 'path', 'parent_id', 'created_by', 'is_protected',
            'sort_order', 'description', 'icon',
        ]));

        MediaFolder::create(['name' => 'Uno', 'slug' => 'uno', 'path' => 'uno']);

        $this->expectException(QueryException::class);
        MediaFolder::create(['name' => 'Duplicato', 'slug' => 'duplicato', 'path' => 'uno']);
    }

    public function test_model_relationships_order_depth_and_hierarchical_label(): void
    {
        $creator = User::factory()->create();
        $root = MediaFolder::create([
            'name' => 'Turing', 'slug' => 'turing', 'path' => 'turing',
            'created_by' => $creator->id, 'sort_order' => 20,
        ]);
        $later = MediaFolder::create([
            'name' => 'Ritratti', 'slug' => 'ritratti', 'path' => 'turing/ritratti',
            'parent_id' => $root->id, 'sort_order' => 20,
        ]);
        $first = MediaFolder::create([
            'name' => 'Enigma', 'slug' => 'enigma', 'path' => 'turing/enigma',
            'parent_id' => $root->id, 'sort_order' => 10,
        ]);

        $this->assertTrue($first->parent->is($root));
        $this->assertTrue($root->creator->is($creator));
        $this->assertSame([$first->id, $later->id], $root->children->pluck('id')->all());
        $this->assertSame(2, $first->depth());
        $this->assertSame('Turing / Enigma', $first->hierarchicalLabel());
        $this->assertTrue($root->hasSubfolders());
    }

    public function test_direct_media_detection_excludes_descendants(): void
    {
        $user = User::factory()->create();
        $folder = MediaFolder::create(['name' => 'Turing', 'slug' => 'turing', 'path' => 'turing']);
        $this->media($user, 'turing/enigma/deep.jpg');

        $this->assertFalse($folder->containsMediaDirectly());

        $this->media($user, 'turing/direct.jpg');
        $this->assertTrue($folder->containsMediaDirectly());
    }

    public function test_direct_media_queries_escape_underscore_in_system_paths(): void
    {
        $user = User::factory()->create();
        $folder = MediaFolder::create([
            'name' => 'Da classificare',
            'slug' => '_da-classificare',
            'path' => '_da-classificare',
        ]);
        $expected = $this->media($user, '_da-classificare/direct.jpg');
        $this->media($user, 'xda-classificare/not-a-match.jpg');
        $this->media($user, '_da-classificare/nested/deep.jpg');

        $query = app(MediaFolderService::class)->scopeDirectMedia(Media::query(), $folder);

        $this->assertTrue($folder->containsMediaDirectly());
        $this->assertSame([$expected->id], $query->pluck('id')->all());
    }

    public function test_seeder_is_idempotent_preserves_manual_folders_and_sets_taxonomy(): void
    {
        MediaFolder::create(['name' => 'Manuale', 'slug' => 'manuale', 'path' => 'manuale']);

        $this->seed(MediaFolderSeeder::class);
        $count = MediaFolder::count();
        $this->seed(MediaFolderSeeder::class);

        $this->assertSame($count, MediaFolder::count());
        $this->assertDatabaseHas('media_folders', ['path' => 'manuale']);
        $this->assertDatabaseHas('media_folders', ['path' => '_da-classificare', 'is_protected' => true, 'sort_order' => 10]);
        $this->assertDatabaseHas('media_folders', ['path' => 'turing', 'is_protected' => true, 'sort_order' => 50]);

        $enigma = MediaFolder::where('path', 'turing/enigma')->firstOrFail();
        $this->assertSame('turing', $enigma->parent->path);
        $this->assertDirectoryExists(public_path('assets/img/turing/enigma'));
    }

    public function test_service_creates_accented_names_and_same_slug_under_different_parents(): void
    {
        $service = app(MediaFolderService::class);
        $user = User::factory()->create();
        $articles = $service->create($user, 'Articoli');
        $turing = $service->create($user, 'Turing');
        $one = $service->create($user, 'Energie rinnovabili', $articles, 'Descrizione', '⚡');
        $two = $service->create($user, 'Energie rinnovabili', $turing);

        $this->assertSame('articoli/energie-rinnovabili', $one->path);
        $this->assertSame('turing/energie-rinnovabili', $two->path);
        $this->assertSame('Descrizione', $one->description);
        $this->assertSame('⚡', $one->icon);
        $this->assertDirectoryExists(public_path('assets/img/'.$one->path));
    }

    public function test_service_compensates_the_directory_when_database_persistence_fails(): void
    {
        $service = new class extends MediaFolderService
        {
            protected function persistFolder(array $attributes): MediaFolder
            {
                throw new RuntimeException('database failure');
            }
        };

        try {
            $service->create(User::factory()->create(), 'Temporanea');
            $this->fail('Expected persistence failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('database failure', $exception->getMessage());
        }

        $this->assertDirectoryDoesNotExist(public_path('assets/img/temporanea'));
        $this->assertDatabaseMissing('media_folders', ['path' => 'temporanea']);
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
