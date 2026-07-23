<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Services\MediaMoveService;
use App\Services\MediaReferenceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaMoveServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    private MediaMoveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        $this->service = app(MediaMoveService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function mediaWithFile(string $diskName, string $content = 'fake-image-bytes'): Media
    {
        $user = User::factory()->create();
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);

        return Media::create([
            'user_id' => $user->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => strlen($content),
        ]);
    }

    private function folder(string $path): MediaFolder
    {
        @mkdir(public_path('assets/img/'.$path), 0775, true);

        return MediaFolder::create(['name' => ucfirst($path), 'slug' => $path, 'path' => $path]);
    }

    public function test_root_to_folder_move_updates_disk_name_and_moves_the_file(): void
    {
        $media = $this->mediaWithFile('foto.jpg');
        $folder = $this->folder('archivio');

        $result = $this->service->move($media->id, $folder->id);

        $this->assertTrue($result->isMoved());
        $this->assertSame('archivio/foto.jpg', $result->newDiskName);
        $this->assertFileExists(public_path('assets/img/archivio/foto.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/foto.jpg'));
        $this->assertSame('archivio/foto.jpg', $media->fresh()->disk_name);
    }

    public function test_folder_to_root_move(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('archivio/foto.jpg');

        $result = $this->service->move($media->id, null);

        $this->assertTrue($result->isMoved());
        $this->assertSame('foto.jpg', $result->newDiskName);
        $this->assertFileExists(public_path('assets/img/foto.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/archivio/foto.jpg'));
    }

    public function test_folder_to_folder_move(): void
    {
        $this->folder('a');
        $b = $this->folder('b');
        $media = $this->mediaWithFile('a/foto.jpg');

        $result = $this->service->move($media->id, $b->id);

        $this->assertTrue($result->isMoved());
        $this->assertSame('b/foto.jpg', $result->newDiskName);
        $this->assertFileExists(public_path('assets/img/b/foto.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/a/foto.jpg'));
    }

    public function test_same_destination_is_a_noop_and_touches_nothing(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('archivio/foto.jpg');

        $result = $this->service->move($media->id, $folder->id);

        $this->assertTrue($result->isNoop());
        $this->assertSame('archivio/foto.jpg', $media->fresh()->disk_name);
        $this->assertFileExists(public_path('assets/img/archivio/foto.jpg'));
    }

    public function test_missing_source_file_throws_and_leaves_the_record_untouched(): void
    {
        $media = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'fantasma.jpg',
            'disk_name' => 'fantasma.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
        ]);
        $folder = $this->folder('archivio');

        try {
            $this->service->move($media->id, $folder->id);
            $this->fail('Doveva lanciare RuntimeException per sorgente mancante.');
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertSame('fantasma.jpg', $media->fresh()->disk_name);
    }

    public function test_invalid_destination_folder_throws(): void
    {
        $media = $this->mediaWithFile('foto.jpg');

        $this->expectException(ModelNotFoundException::class);
        $this->service->move($media->id, 999999);
    }

    public function test_folder_deleted_during_the_operation_throws(): void
    {
        $folder = $this->folder('temporanea');
        $media = $this->mediaWithFile('foto.jpg');
        $folderId = $folder->id;
        $folder->delete();

        $this->expectException(ModelNotFoundException::class);
        $this->service->move($media->id, $folderId);
    }

    public function test_database_collision_blocks_the_move_before_touching_the_filesystem(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('foto.jpg');
        $this->mediaWithFile('archivio/foto.jpg');

        try {
            $this->service->move($media->id, $folder->id);
            $this->fail('Doveva lanciare RuntimeException per collisione DB.');
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertFileExists(public_path('assets/img/foto.jpg'));
        $this->assertSame('foto.jpg', $media->fresh()->disk_name);
    }

    public function test_filesystem_collision_blocks_the_move_even_without_a_db_record(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('foto.jpg');
        file_put_contents(public_path('assets/img/archivio/foto.jpg'), 'file orfano non registrato');

        try {
            $this->service->move($media->id, $folder->id);
            $this->fail('Doveva lanciare RuntimeException per collisione filesystem.');
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertFileExists(public_path('assets/img/foto.jpg'));
        $this->assertSame('file orfano non registrato', file_get_contents(public_path('assets/img/archivio/foto.jpg')));
    }

    public function test_destination_directory_is_created_when_missing(): void
    {
        $folder = $this->folder('nuova-cartella');
        $media = $this->mediaWithFile('foto.jpg');

        rmdir(public_path('assets/img/nuova-cartella'));
        $this->assertDirectoryDoesNotExist(public_path('assets/img/nuova-cartella'));

        $result = $this->service->move($media->id, $folder->id);

        $this->assertTrue($result->isMoved());
        $this->assertFileExists(public_path('assets/img/nuova-cartella/foto.jpg'));
    }

    public function test_path_traversal_in_a_crafted_disk_name_is_rejected(): void
    {
        $media = $this->mediaWithFile('foto.jpg');
        Media::whereKey($media->id)->update(['disk_name' => '../../etc/passwd']);
        $folder = $this->folder('archivio');

        $this->expectException(\Throwable::class);
        $this->service->move($media->id, $folder->id);
    }

    public function test_basename_and_metadata_are_preserved_across_the_move(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('immagine-originale.jpg', 'contenuto-specifico');
        $media->update(['alt_text' => 'testo alternativo', 'filename' => 'Nome Originale.jpg']);

        $result = $this->service->move($media->id, $folder->id);

        $this->assertSame('archivio/immagine-originale.jpg', $result->newDiskName);
        $fresh = $media->fresh();
        $this->assertSame('Nome Originale.jpg', $fresh->filename);
        $this->assertSame('testo alternativo', $fresh->alt_text);
        $this->assertSame('image/jpeg', $fresh->mime_type);
        $this->assertSame(strlen('contenuto-specifico'), $fresh->size);
    }

    public function test_updatable_references_are_applied_together_with_the_move(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('cover.jpg');
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo con copertina',
            'slug' => 'articolo-con-copertina',
            'body' => 'Testo.',
            'category' => 'scienza',
            'cover_image' => 'cover.jpg',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $result = $this->service->move($media->id, $folder->id);

        $this->assertTrue($result->isMoved());
        $this->assertSame('archivio/cover.jpg', $article->fresh()->cover_image);
    }

    public function test_blocking_reference_prevents_the_move_and_leaves_everything_untouched(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('protetta.jpg');
        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Con html inline',
            'slug' => 'con-html-inline',
            'body' => 'Vedi <img src="/assets/img/protetta.jpg">',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $result = $this->service->move($media->id, $folder->id);

        $this->assertTrue($result->isBlocked());
        $this->assertSame('protetta.jpg', $media->fresh()->disk_name);
        $this->assertFileExists(public_path('assets/img/protetta.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/archivio/protetta.jpg'));
    }

    public function test_no_overwrite_of_an_existing_destination_file_ever_occurs(): void
    {
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('foto.jpg', 'contenuto-originale');
        file_put_contents(public_path('assets/img/archivio/foto.jpg'), 'contenuto-esistente-da-non-toccare');

        try {
            $this->service->move($media->id, $folder->id);
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertSame('contenuto-esistente-da-non-toccare', file_get_contents(public_path('assets/img/archivio/foto.jpg')));
        $this->assertSame('contenuto-originale', file_get_contents(public_path('assets/img/foto.jpg')));
    }

    public function test_move_reloads_current_state_instead_of_trusting_a_stale_reference(): void
    {
        $folder = $this->folder('destinazione');
        $media = $this->mediaWithFile('originale.jpg');

        // Simula un secondo processo che ha gia agito sullo stesso Media nel
        // frattempo (es. un'altra richiesta concorrente gia completata).
        rename(public_path('assets/img/originale.jpg'), public_path('assets/img/gia-rinominato.jpg'));
        Media::whereKey($media->id)->update(['disk_name' => 'gia-rinominato.jpg']);

        $result = $this->service->move($media->id, $folder->id);

        $this->assertTrue($result->isMoved());
        $this->assertSame('destinazione/gia-rinominato.jpg', $result->newDiskName);
        $this->assertFileExists(public_path('assets/img/destinazione/gia-rinominato.jpg'));
    }

    public function test_a_failure_after_the_physical_move_triggers_filesystem_compensation(): void
    {
        $this->mock(MediaReferenceService::class, function ($mock) {
            $mock->shouldReceive('preflight')->andReturn([
                'updatable_references' => [[
                    'type' => 'tipo_sconosciuto_di_test',
                    'model' => null,
                    'record_id' => null,
                    'field' => null,
                    'json_path' => null,
                    'description' => 'riferimento fittizio per test di rollback',
                    'old_value' => 'da-compensare.jpg',
                    'new_value' => 'archivio/da-compensare.jpg',
                    'blocking_reason' => null,
                ]],
                'blocking_references' => [],
                'informational_references' => [],
                'can_move' => true,
                'total_usage_count' => 1,
            ]);
        });

        $service = app(MediaMoveService::class);
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile('da-compensare.jpg');

        try {
            $service->move($media->id, $folder->id);
            $this->fail('Doveva lanciare un errore per il tipo di riferimento sconosciuto.');
        } catch (RuntimeException) {
            // atteso: la compensazione deve comunque riuscire
        }

        $this->assertSame('da-compensare.jpg', $media->fresh()->disk_name);
        $this->assertFileExists(public_path('assets/img/da-compensare.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/archivio/da-compensare.jpg'));
    }
}
