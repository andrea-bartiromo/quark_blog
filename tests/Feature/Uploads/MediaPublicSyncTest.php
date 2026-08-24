<?php

namespace Tests\Feature\Uploads;

use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedMediaPublicRoot;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * Copre l'intero ciclo di vita dei media rispetto alla sincronizzazione
 * verso la document root pubblica secondaria (MEDIA_PUBLIC_ROOT): upload da
 * form articolo (admin e redazione), upload dalla Libreria media (anche
 * AJAX), spostamento in cartella, eliminazione, e comportamento quando la
 * sincronizzazione e' configurata ma fallisce.
 */
class MediaPublicSyncTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedMediaPublicRoot;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        $this->setUpIsolatedMediaPublicRoot();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedMediaPublicRoot();
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function publicRootPath(string $diskName): string
    {
        return $this->isolatedMediaPublicRoot.'/'.$diskName;
    }

    /**
     * Elenca ricorsivamente solo i file regolari sotto una directory
     * (mai le sottodirectory stesse): usato per verificare che un upload
     * fallito non lasci file orfani, ignorando l'eventuale creazione di
     * cartelle vuote (es. classificazione automatica) che avviene comunque
     * indipendentemente dall'esito della sincronizzazione pubblica.
     *
     * @return list<string>
     */
    private function filesUnder(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (scandir($directory) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;

            if (is_dir($path)) {
                array_push($files, ...$this->filesUnder($path));
            } else {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    // 1. Upload copertina da form articolo (admin)
    public function test_admin_article_cover_upload_is_synced_to_the_public_root(): void
    {
        $editor = $this->editor();
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo con copertina sincronizzata',
            'excerpt' => 'Sommario',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'status' => 'draft',
            'cover_image_upload' => $cover,
        ]);

        $article = Article::where('title', 'Articolo con copertina sincronizzata')->firstOrFail();

        $this->assertFileExists(public_path('assets/img/'.$article->cover_image));
        $this->assertFileExists($this->publicRootPath($article->cover_image));
        $this->assertSame(
            file_get_contents(public_path('assets/img/'.$article->cover_image)),
            file_get_contents($this->publicRootPath($article->cover_image))
        );
    }

    // 1bis. Upload copertina da form articolo (redazione)
    public function test_redazione_article_cover_upload_is_synced_to_the_public_root(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $this->actingAs($author)->post(route('redazione.articles.store'), [
            'title' => 'Bozza redazione con copertina',
            'excerpt' => 'Sommario',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'cover_image_upload' => $cover,
        ]);

        $article = Article::where('title', 'Bozza redazione con copertina')->firstOrFail();

        $this->assertFileExists($this->publicRootPath($article->cover_image));
    }

    // 2. Upload dalla Libreria media (AJAX incluso)
    public function test_media_library_upload_is_synced_to_the_public_root(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('libreria.png', 400, 300);

        $response = $this->actingAs($editor)
            ->postJson(route('admin.media.upload'), ['image' => $image]);

        $response->assertOk()->assertJson(['ok' => true]);
        $diskName = $response->json('filename');

        $this->assertFileExists(public_path('assets/img/'.$diskName));
        $this->assertFileExists($this->publicRootPath($diskName));
    }

    // 3. Anteprima disponibile: il file referenziato da Media esiste
    // davvero in entrambe le directory dopo l'upload.
    public function test_uploaded_media_preview_is_available_in_both_roots(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('anteprima.jpg', 400, 300);

        $this->actingAs($editor)->post(route('admin.media.store'), ['image' => $image]);

        $media = Media::firstOrFail();

        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
        $this->assertFileExists($this->publicRootPath($media->disk_name));
    }

    // 4. Spostamento media in cartella
    public function test_moving_media_into_a_folder_keeps_both_roots_in_sync(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('da-spostare.jpg', 400, 300);
        $this->actingAs($editor)->post(route('admin.media.store'), ['image' => $image]);
        $media = Media::firstOrFail();

        @mkdir(public_path('assets/img/archivio'), 0775, true);
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);

        $this->actingAs($editor)->patch(route('admin.media.move', $media), [
            'media_folder_id' => $folder->id,
        ]);

        $media->refresh();

        $this->assertStringStartsWith('archivio/', $media->disk_name);
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
        $this->assertFileExists($this->publicRootPath($media->disk_name));
        $this->assertFileDoesNotExist($this->publicRootPath('da-spostare.jpg'));
    }

    // 5. Eliminazione media
    public function test_deleting_media_removes_the_file_from_both_roots(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('da-eliminare.jpg', 400, 300);
        $this->actingAs($editor)->post(route('admin.media.store'), ['image' => $image]);
        $media = Media::firstOrFail();
        $diskName = $media->disk_name;

        $this->assertFileExists($this->publicRootPath($diskName));

        $this->actingAs($editor)->delete(route('admin.media.destroy', $media));

        $this->assertFileDoesNotExist(public_path('assets/img/'.$diskName));
        $this->assertFileDoesNotExist($this->publicRootPath($diskName));
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    /**
     * Configura MEDIA_PUBLIC_ROOT su un percorso che non potrà mai
     * diventare una directory: un file normale esiste già in quella
     * esatta posizione, quindi mkdir() fallisce per un vincolo del
     * filesystem (non per permessi — che nei container di test spesso
     * girano come root e ignorerebbero un chmod 0000, rendendo quel
     * trucco inaffidabile qui).
     */
    private function configureUnusablePublicRoot(): void
    {
        $blockedRoot = $this->isolatedMediaPublicRoot.'/bloccato-da-un-file';
        file_put_contents($blockedRoot, 'non è una directory');

        config(['media.public_root' => $blockedRoot]);
    }

    // 6. Errore durante la sincronizzazione: nessuno stato incoerente nel DB.
    public function test_sync_failure_on_article_cover_upload_does_not_create_an_incoherent_article(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo che non deve esistere',
            'excerpt' => 'Sommario',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'status' => 'draft',
            'cover_image_upload' => $cover,
        ]);

        $response->assertSessionHasErrors('cover_image_upload');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo che non deve esistere']);
        $this->assertDatabaseCount('media', 0);
    }

    // 6bis. Lo stesso fallimento non deve lasciare orfano il file gia'
    // scritto in public/assets/img prima che la sincronizzazione fallisse.
    public function test_sync_failure_on_article_cover_upload_cleans_up_the_local_file(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $filesBefore = $this->filesUnder(public_path('assets/img'));

        $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title' => 'Articolo che non deve lasciare file orfani',
            'excerpt' => 'Sommario',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'status' => 'draft',
            'cover_image_upload' => $cover,
        ]);

        $filesAfter = $this->filesUnder(public_path('assets/img'));

        $this->assertSame($filesBefore, $filesAfter, 'Il file caricato non deve restare orfano se la sincronizzazione fallisce.');
    }

    public function test_sync_failure_on_media_library_upload_does_not_create_a_media_record(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce.jpg', 400, 300);

        $response = $this->actingAs($editor)
            ->postJson(route('admin.media.upload'), ['image' => $image]);

        $response->assertStatus(500)->assertJson(['ok' => false]);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_sync_failure_on_media_library_upload_cleans_up_the_local_file(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce.jpg', 400, 300);

        $filesBefore = $this->filesUnder(public_path('assets/img'));

        $this->actingAs($editor)->postJson(route('admin.media.upload'), ['image' => $image]);

        $filesAfter = $this->filesUnder(public_path('assets/img'));

        $this->assertSame($filesBefore, $filesAfter, 'Il file caricato non deve restare orfano se la sincronizzazione fallisce.');
    }
}
