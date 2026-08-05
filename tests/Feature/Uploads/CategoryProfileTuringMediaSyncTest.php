<?php

namespace Tests\Feature\Uploads;

use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\SpecialPage;
use App\Models\User;
use App\Services\PublicMediaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\UsesIsolatedMediaPublicRoot;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * Estende la sincronizzazione verso MEDIA_PUBLIC_ROOT (già coperta per
 * copertine articolo e Libreria media in MediaPublicSyncTest) agli altri
 * punti che scrivono immagini: categorie, foto profilo (admin e
 * redazione) e immagini dello Speciale Turing. Copre anche la
 * sostituzione (l'immagine precedente non deve restare orfana tra
 * public/assets/img e la radice pubblica secondaria) e il comportamento
 * quando la sincronizzazione è configurata ma fallisce.
 */
class CategoryProfileTuringMediaSyncTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;
    use UsesIsolatedMediaPublicRoot;

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
     * Elenca ricorsivamente solo i file regolari sotto una directory: usato
     * per verificare che un upload fallito non lasci file orfani, ignorando
     * l'eventuale creazione di cartelle vuote indipendente dall'esito della
     * sincronizzazione pubblica.
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

    private function configureUnusablePublicRoot(): void
    {
        $blockedRoot = $this->isolatedMediaPublicRoot.'/bloccato-da-un-file';
        file_put_contents($blockedRoot, 'non è una directory');

        config(['media.public_root' => $blockedRoot]);
    }

    // --- Categorie ------------------------------------------------------

    public function test_category_image_upload_is_synced_to_the_public_root(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('cat.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.categories.store'), [
            'name' => 'Categoria con immagine',
            'slug' => '',
            'image_upload' => $image,
        ]);

        $category = Category::where('name', 'Categoria con immagine')->firstOrFail();

        $this->assertFileExists(public_path('assets/img/categories/'.$category->image));
        $this->assertFileExists($this->publicRootPath('categories/'.$category->image));
    }

    public function test_replacing_a_category_image_removes_the_previous_one_from_both_roots(): void
    {
        $editor = $this->editor();
        $original = UploadedFile::fake()->image('originale.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.categories.store'), [
            'name' => 'Categoria da aggiornare',
            'slug' => '',
            'image_upload' => $original,
        ]);
        $category = Category::where('name', 'Categoria da aggiornare')->firstOrFail();
        $oldImage = $category->image;

        $this->assertFileExists($this->publicRootPath('categories/'.$oldImage));

        $replacement = UploadedFile::fake()->image('sostituta.jpg', 800, 600);
        $this->actingAs($editor)->put(route('admin.categories.update', $category), [
            'name' => 'Categoria da aggiornare',
            'slug' => $category->slug,
            'image_upload' => $replacement,
        ]);

        $category->refresh();

        $this->assertNotSame($oldImage, $category->image);
        $this->assertFileExists($this->publicRootPath('categories/'.$category->image));
        $this->assertFileDoesNotExist($this->publicRootPath('categories/'.$oldImage));
        $this->assertFileDoesNotExist(public_path('assets/img/categories/'.$oldImage));
    }

    public function test_deleting_a_category_removes_its_image_from_both_roots(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('da-eliminare.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.categories.store'), [
            'name' => 'Categoria da eliminare',
            'slug' => '',
            'image_upload' => $image,
        ]);
        $category = Category::where('name', 'Categoria da eliminare')->firstOrFail();
        $diskName = $category->image;

        $this->actingAs($editor)->delete(route('admin.categories.destroy', $category));

        $this->assertFileDoesNotExist(public_path('assets/img/categories/'.$diskName));
        $this->assertFileDoesNotExist($this->publicRootPath('categories/'.$diskName));
    }

    public function test_sync_failure_on_category_image_upload_does_not_create_the_category(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce.jpg', 800, 600);

        $response = $this->actingAs($editor)->post(route('admin.categories.store'), [
            'name' => 'Categoria che non deve esistere',
            'slug' => '',
            'image_upload' => $image,
        ]);

        $response->assertSessionHasErrors('image_upload');
        $this->assertDatabaseMissing('categories', ['name' => 'Categoria che non deve esistere']);
    }

    public function test_sync_failure_on_category_image_upload_cleans_up_the_local_file(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce.jpg', 800, 600);

        $filesBefore = $this->filesUnder(public_path('assets/img'));

        $this->actingAs($editor)->post(route('admin.categories.store'), [
            'name' => 'Categoria che non deve lasciare file orfani',
            'slug' => '',
            'image_upload' => $image,
        ]);

        $filesAfter = $this->filesUnder(public_path('assets/img'));

        $this->assertSame($filesBefore, $filesAfter, 'Il file caricato non deve restare orfano se la sincronizzazione fallisce.');
    }

    // --- Foto profilo (admin) -------------------------------------------

    public function test_admin_profile_photo_upload_is_synced_to_the_public_root(): void
    {
        $editor = $this->editor();
        $photo = UploadedFile::fake()->image('foto.jpg', 400, 400);

        $this->actingAs($editor)->post(route('admin.profile.photo'), ['photo' => $photo]);

        $editor->refresh();

        $this->assertFileExists(public_path('assets/img/'.$editor->photo));
        $this->assertFileExists($this->publicRootPath($editor->photo));
    }

    public function test_replacing_the_admin_profile_photo_syncs_the_new_one_and_retires_the_previous_one(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.profile.photo'), [
            'photo' => UploadedFile::fake()->image('prima.jpg', 400, 400),
        ]);
        $editor->refresh();
        $firstPhoto = $editor->photo;

        $this->actingAs($editor)->post(route('admin.profile.photo'), [
            'photo' => UploadedFile::fake()->image('seconda.jpg', 400, 400),
        ]);
        $editor->refresh();

        $this->assertNotSame($firstPhoto, $editor->photo);
        $this->assertFileExists(public_path('assets/img/'.$editor->photo));
        $this->assertFileExists($this->publicRootPath($editor->photo));

        // La foto precedente, non più referenziata da nessuno, viene
        // ritirata da entrambe le directory e dal suo record Media.
        $this->assertFileDoesNotExist(public_path('assets/img/'.$firstPhoto));
        $this->assertFileDoesNotExist($this->publicRootPath($firstPhoto));
        $this->assertDatabaseMissing('media', ['disk_name' => $firstPhoto]);
    }

    public function test_previous_admin_profile_photo_is_not_retired_while_still_referenced_elsewhere(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.profile.photo'), [
            'photo' => UploadedFile::fake()->image('condivisa.jpg', 400, 400),
        ]);
        $editor->refresh();
        $sharedPhoto = $editor->photo;

        // La stessa immagine è anche usata come copertina di un articolo:
        // deve restare intatta anche dopo la sostituzione della foto profilo.
        Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo che riusa la foto',
            'slug' => 'articolo-che-riusa-la-foto-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'energia',
            'cover_image' => $sharedPhoto,
            'status' => 'draft',
        ]);

        $this->actingAs($editor)->post(route('admin.profile.photo'), [
            'photo' => UploadedFile::fake()->image('nuova.jpg', 400, 400),
        ]);

        $this->assertFileExists(public_path('assets/img/'.$sharedPhoto));
        $this->assertFileExists($this->publicRootPath($sharedPhoto));
        $this->assertDatabaseHas('media', ['disk_name' => $sharedPhoto]);
    }

    public function test_previous_admin_profile_photo_cleanup_failure_is_logged_and_does_not_block_the_upload(): void
    {
        Log::spy();

        /*
         * La foto "precedente" è costruita direttamente (file + record
         * Media), non tramite un primo upload HTTP: evita una seconda
         * richiesta live nello stesso test (il resolver dei controller in
         * questo ambiente di test riusa l'istanza già costruita alla prima
         * richiesta, quindi un doppio round-trip non ripescherebbe qui il
         * binding sostituito a metà test) e isola esattamente lo scenario
         * voluto: un solo upload, con la pulizia della foto precedente che
         * fallisce.
         *
         * Un vero fallimento di permessi non è simulabile in modo
         * affidabile qui (il processo di test gira come root, che ignora
         * i bit di permesso): si sostituisce quindi il servizio con una
         * sottoclasse minima che fa fallire solo delete(), lasciando
         * create() (il nuovo upload) invariato.
         */
        $this->app->instance(PublicMediaSyncService::class, new class extends PublicMediaSyncService
        {
            public function delete(string $diskName): void
            {
                throw new RuntimeException('simulazione: pulizia fallita');
            }
        });

        $editor = $this->editor();
        $firstPhoto = 'author-'.$editor->id.'-esistente.jpg';
        file_put_contents(public_path('assets/img/'.$firstPhoto), 'contenuto-foto-precedente');
        Media::create([
            'user_id' => $editor->id,
            'filename' => 'precedente.jpg',
            'disk_name' => $firstPhoto,
            'mime_type' => 'image/jpeg',
            'size' => strlen('contenuto-foto-precedente'),
        ]);
        $editor->update(['photo' => $firstPhoto]);

        $response = $this->actingAs($editor)->post(route('admin.profile.photo'), [
            'photo' => UploadedFile::fake()->image('nuova.jpg', 400, 400),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $editor->refresh();
        $this->assertNotSame($firstPhoto, $editor->photo);
        $this->assertNotNull($editor->photo);
        $this->assertFileExists(public_path('assets/img/'.$editor->photo));

        // La foto precedente non è stata toccata (pulizia fallita, nessuna
        // modifica parziale): resta comunque presente e coerente.
        $this->assertFileExists(public_path('assets/img/'.$firstPhoto));
        $this->assertDatabaseHas('media', ['disk_name' => $firstPhoto]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($firstPhoto) {
                return $context['operation'] === 'admin_profile_photo_replaced'
                    && $context['disk_name'] === $firstPhoto
                    && $context['exception'] instanceof \Throwable;
            });
    }

    public function test_sync_failure_on_admin_profile_photo_upload_does_not_change_the_stored_photo(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $photo = UploadedFile::fake()->image('fallisce.jpg', 400, 400);

        $response = $this->actingAs($editor)->post(route('admin.profile.photo'), ['photo' => $photo]);

        $response->assertSessionHasErrors('photo');
        $this->assertNull($editor->fresh()->photo);
    }

    // --- Foto profilo (redazione) ---------------------------------------

    public function test_redazione_profile_photo_upload_is_synced_to_the_public_root(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $photo = UploadedFile::fake()->image('foto.jpg', 400, 400);

        $this->actingAs($author)->post(route('redazione.profile.photo'), ['photo' => $photo]);

        $author->refresh();

        $this->assertFileExists(public_path('assets/img/'.$author->photo));
        $this->assertFileExists($this->publicRootPath($author->photo));
    }

    public function test_replacing_the_redazione_profile_photo_syncs_the_new_one_and_retires_the_previous_one(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $this->actingAs($author)->post(route('redazione.profile.photo'), [
            'photo' => UploadedFile::fake()->image('prima.jpg', 400, 400),
        ]);
        $author->refresh();
        $firstPhoto = $author->photo;

        $this->actingAs($author)->post(route('redazione.profile.photo'), [
            'photo' => UploadedFile::fake()->image('seconda.jpg', 400, 400),
        ]);
        $author->refresh();

        $this->assertNotSame($firstPhoto, $author->photo);
        $this->assertFileExists(public_path('assets/img/'.$author->photo));
        $this->assertFileExists($this->publicRootPath($author->photo));

        // La foto precedente, non più referenziata da nessuno, viene
        // ritirata da entrambe le directory e dal suo record Media.
        $this->assertFileDoesNotExist(public_path('assets/img/'.$firstPhoto));
        $this->assertFileDoesNotExist($this->publicRootPath($firstPhoto));
        $this->assertDatabaseMissing('media', ['disk_name' => $firstPhoto]);
    }

    // --- Immagini Turing --------------------------------------------------

    private function turingPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Alan Turing',
            'hero_title' => 'Alan Turing',
            'hero_lead' => 'Lead di prova.',
            'intro_title' => 'Introduzione di prova',
            'intro_text' => 'Testo introduzione di prova.',
            'why_title' => 'Perché conta ancora',
            'why_text' => 'Testo perché conta ancora.',
            'final_title' => 'Prossima lettura',
            'final_text' => 'Testo finale di prova.',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_turing_hero_background_image_upload_is_synced_to_the_public_root(): void
    {
        $editor = $this->editor();
        $image = UploadedFile::fake()->image('hero-bg.jpg', 1600, 900);

        $this->actingAs($editor)
            ->post(route('admin.turing.update'), $this->turingPayload([
                'hero_background_image_upload' => $image,
            ]))
            ->assertRedirect(route('admin.turing'));

        $page = SpecialPage::where('slug', 'turing')->firstOrFail();
        $diskName = $page->content['hero']['background_image'];

        $this->assertNotNull($diskName);
        $this->assertFileExists(public_path('assets/img/'.$diskName));
        $this->assertFileExists($this->publicRootPath($diskName));
    }

    public function test_sync_failure_on_turing_image_upload_does_not_update_the_page(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce.jpg', 1600, 900);

        $originalTitle = 'Titolo originale mai sovrascritto';
        SpecialPage::create([
            'slug' => 'turing',
            'title' => $originalTitle,
            'is_active' => true,
            'content' => [],
        ]);

        $response = $this->actingAs($editor)
            ->post(route('admin.turing.update'), $this->turingPayload([
                'title' => 'Titolo che non deve essere salvato',
                'hero_background_image_upload' => $image,
            ]));

        $response->assertSessionHasErrors('content');

        $page = SpecialPage::where('slug', 'turing')->firstOrFail();
        $this->assertSame($originalTitle, $page->title);
    }

    public function test_sync_failure_on_turing_image_upload_cleans_up_the_local_file(): void
    {
        $this->configureUnusablePublicRoot();

        $editor = $this->editor();
        $image = UploadedFile::fake()->image('fallisce.jpg', 1600, 900);

        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Titolo esistente',
            'is_active' => true,
            'content' => [],
        ]);

        $filesBefore = $this->filesUnder(public_path('assets/img'));

        $this->actingAs($editor)
            ->post(route('admin.turing.update'), $this->turingPayload([
                'hero_background_image_upload' => $image,
            ]));

        $filesAfter = $this->filesUnder(public_path('assets/img'));

        $this->assertSame($filesBefore, $filesAfter, 'Il file caricato non deve restare orfano se la sincronizzazione fallisce.');
    }
}
