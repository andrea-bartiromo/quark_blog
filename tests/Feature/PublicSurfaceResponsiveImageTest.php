<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ImageService;
use App\Services\ResponsiveImageVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithTestImages;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * MISSIONE S2-A (coverage completion responsive images): copre le
 * superfici pubbliche convertite in questa missione da raw <img> a
 * <x-responsive-image> — /notizie, /ricerca e l'avatar/le card articolo di
 * /autore/{user} — sia col percorso "varianti reali presenti" (srcset
 * popolato, stesso meccanismo gia' coperto in isolamento da
 * ResponsiveImageVariantServiceTest) sia col fallback legacy (nessuna
 * variante ancora generata: comportamento identico al raw <img>
 * preesistente, nessuna migrazione necessaria).
 *
 * author-card.blade.php (foto autore nel box "Autore" sotto l'articolo)
 * resta volutamente fuori da questo file: FASE 6 della missione ha
 * verificato che usa una radice di storage diversa (storage/) da quella
 * scritta oggi dai controller di upload (assets/img/), e la missione vieta
 * esplicitamente di normalizzare quella radice alla cieca — vedi il report
 * finale per l'analisi completa.
 */
class PublicSurfaceResponsiveImageTest extends TestCase
{
    use InteractsWithTestImages;
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        config(['media.responsive_widths' => [480, 960]]);
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        $this->tearDownTestImages();
        parent::tearDown();
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid('', true),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
            'read_minutes' => 3,
        ], $overrides));
    }

    /**
     * Scrive un file immagine reale in assets/img/{diskName} e genera le
     * relative varianti responsive, cosi' resolveForMarkup() trovi sia
     * l'originale leggibile sia le varianti — stesso schema gia' usato da
     * ResponsiveImageVariantServiceTest e ResponsiveImageLifecycleTest.
     */
    private function placeCoverWithVariantsAt(string $diskName, int $width, int $height): void
    {
        $file = $this->makeSolidImageUpload(basename($diskName), $width, $height);
        $target = public_path('assets/img/'.$diskName);
        @mkdir(dirname($target), 0775, true);
        rename($file->getPathname(), $target);

        app(ResponsiveImageVariantService::class)->generateForUpload($target, $diskName);
    }

    // ---- /notizie ----

    public function test_notizie_card_image_has_srcset_and_coherent_sizes_when_variants_exist(): void
    {
        $this->placeCoverWithVariantsAt('articles/covers/notizie-cover.jpg', 2000, 1250);

        $this->publishedArticle($this->author(), [
            'title' => 'Notizia con copertina',
            'cover_image' => 'articles/covers/notizie-cover.jpg',
        ]);

        $response = $this->get(route('notizie'));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/articles/covers/notizie-cover.jpg').'"', false);
        $response->assertSee('srcset="'.asset('assets/img/articles/covers/notizie-cover-480w.jpg').' 480w, '
            .asset('assets/img/articles/covers/notizie-cover-960w.jpg').' 960w, '
            .asset('assets/img/articles/covers/notizie-cover.jpg').' 2000w"', false);
        $response->assertSee('sizes="(max-width: 900px) 100vw, 290px"', false);
        $response->assertSee('alt="Notizia con copertina"', false);
        $response->assertSee('loading="lazy"', false);
        $response->assertSee('decoding="async"', false);
    }

    public function test_notizie_card_image_falls_back_to_legacy_src_without_srcset_when_no_variants_exist(): void
    {
        $this->publishedArticle($this->author(), [
            'title' => 'Notizia senza copertina',
            'cover_image' => null,
        ]);

        $response = $this->get(route('notizie'));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/placeholder-1.svg').'"', false);
        $response->assertDontSee('srcset=', false);
        $response->assertSee(
            'onerror="this.onerror=null;this.src=\''.asset('assets/img/placeholder-1.svg').'\';"',
            false
        );
    }

    // ---- /ricerca ----

    public function test_ricerca_result_image_has_srcset_and_fixed_sizes_when_variants_exist(): void
    {
        $author = $this->author();
        $this->placeCoverWithVariantsAt('articles/covers/ricerca-cover.jpg', 1800, 1000);

        $this->publishedArticle($author, [
            'title' => 'Risultato con copertina',
            'cover_image' => 'articles/covers/ricerca-cover.jpg',
        ]);

        $response = $this->get(route('ricerca', ['autore' => $author->id]));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/articles/covers/ricerca-cover.jpg').'"', false);
        $response->assertSee('srcset="'.asset('assets/img/articles/covers/ricerca-cover-480w.jpg').' 480w, '
            .asset('assets/img/articles/covers/ricerca-cover-960w.jpg').' 960w, '
            .asset('assets/img/articles/covers/ricerca-cover.jpg').' 1800w"', false);
        $response->assertSee('sizes="180px"', false);
        $response->assertSee('alt="Risultato con copertina"', false);
        $response->assertSee('loading="lazy"', false);
    }

    public function test_ricerca_result_image_falls_back_to_legacy_src_without_srcset_when_no_variants_exist(): void
    {
        $author = $this->author();
        $this->publishedArticle($author, [
            'title' => 'Risultato senza copertina',
            'cover_image' => null,
        ]);

        $response = $this->get(route('ricerca', ['autore' => $author->id]));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/placeholder-1.svg').'"', false);
        $response->assertDontSee('srcset=', false);
    }

    // ---- /autore/{user}: avatar ----

    public function test_autore_avatar_has_srcset_and_coherent_sizes_when_variants_exist(): void
    {
        $author = $this->author();
        $this->publishedArticle($author);
        $this->placeCoverWithVariantsAt('author-avatar.jpg', 800, 800);
        $author->update(['photo' => 'author-avatar.jpg']);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/author-avatar.jpg').'"', false);
        $response->assertSee('srcset="'.asset('assets/img/author-avatar-480w.jpg').' 480w, '
            .asset('assets/img/author-avatar.jpg').' 800w"', false);
        $response->assertSee('sizes="(max-width: 980px) 104px, 118px"', false);
        $response->assertSee('alt="'.$author->name.'"', false);
        $response->assertSee('aria-hidden="false"', false);
    }

    // Review chatgpt-codex-connector su PR #247: l'avatar e' visibile above
    // the fold in cima a /autore/{user} — il raw <img> preesistente non
    // aveva alcun attributo "loading" (quindi eager per default del
    // browser); il componente invece imposta "lazy" per default, quindi il
    // chiamante deve passare esplicitamente loading="eager" per non
    // ritardarne il caricamento.
    public function test_autore_avatar_is_loaded_eagerly_not_lazily(): void
    {
        $author = $this->author();
        $this->publishedArticle($author);
        $author->update(['photo' => 'author-avatar.jpg']);

        $response = $this->get(route('autore', $author));

        $response->assertOk();

        // Scoped al blocco avatar, non all'intera pagina: la fixture ora
        // richiede un articolo pubblicato, la cui card nell'elenco usa
        // legittimamente loading="lazy" (non above the fold) —
        // irrilevante per questo test, che riguarda solo l'avatar.
        preg_match('/author-premium-hero__avatar.*?<\/div>/s', $response->getContent(), $avatarBlock);
        $this->assertNotEmpty($avatarBlock, 'Blocco avatar non trovato in pagina.');
        $this->assertStringNotContainsString('loading="lazy"', $avatarBlock[0]);
        $this->assertStringContainsString('loading="eager"', $avatarBlock[0]);
        $response->assertSee('alt="'.$author->name.'"', false);
    }

    public function test_autore_avatar_falls_back_gracefully_when_photo_file_is_missing_on_disk(): void
    {
        // Il record ha un valore in "photo" ma il file non esiste sul
        // filesystem isolato di questo test (es. dato legacy/seed senza il
        // file fisico corrispondente): stesso fallback legacy gia' coperto
        // da ResponsiveImageVariantServiceTest, mai un errore o una pagina
        // rotta.
        $author = $this->author();
        $this->publishedArticle($author);
        $author->update(['photo' => 'author-che-non-esiste.jpg']);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/author-che-non-esiste.jpg').'"', false);
        $response->assertDontSee('srcset=', false);
        $response->assertSee('aria-hidden="false"', false);
    }

    public function test_autore_page_still_shows_initial_placeholder_when_no_photo_is_set(): void
    {
        // Nessuna regressione sul ramo @else (nessuna foto): non deve mai
        // provare a risolvere un diskName vuoto/nullo.
        $author = User::factory()->create([
            'role' => 'author',
            'name' => 'Autrice Senza Foto',
            'photo' => null,
        ]);
        $this->publishedArticle($author);

        $response = $this->get(route('autore', $author));

        $response->assertOk();

        // Scoped al blocco avatar, non all'intera pagina: la fixture ora
        // richiede un articolo pubblicato, che porta con sé una propria
        // <img> di copertina nell'elenco — irrilevante per questo test.
        preg_match('/author-premium-hero__avatar.*?<\/div>/s', $response->getContent(), $avatarBlock);
        $this->assertNotEmpty($avatarBlock, 'Blocco avatar non trovato in pagina.');
        $this->assertStringNotContainsString('<img', $avatarBlock[0]);

        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('<span>A</span>', false);
    }

    // ---- /autore/{user}: card articolo ----

    public function test_autore_article_card_image_uses_the_same_responsive_markup_as_ricerca(): void
    {
        $author = $this->author();
        $this->placeCoverWithVariantsAt('articles/covers/autore-cover.jpg', 1600, 900);

        $this->publishedArticle($author, [
            'title' => 'Articolo di autore con copertina',
            'cover_image' => 'articles/covers/autore-cover.jpg',
        ]);

        $response = $this->get(route('autore', $author));

        $response->assertOk();
        $response->assertSee('srcset="'.asset('assets/img/articles/covers/autore-cover-480w.jpg').' 480w, '
            .asset('assets/img/articles/covers/autore-cover-960w.jpg').' 960w, '
            .asset('assets/img/articles/covers/autore-cover.jpg').' 1600w"', false);
        $response->assertSee('sizes="180px"', false);
        $response->assertSee('alt="Articolo di autore con copertina"', false);
    }

    // ---- Upload profilo: le foto autore devono generare varianti ----
    //
    // Review chatgpt-codex-connector su PR #247: la conversione della vista
    // autore a <x-responsive-image> serve a nulla se il percorso di upload
    // non genera mai le varianti che il componente prova a servire — prima
    // di questo fix ne' Admin\ProfileController::updatePhoto() ne'
    // Redazione\ProfileController::updatePhoto() chiamavano
    // ResponsiveImageVariantService::generateForUpload(), a differenza
    // dell'upload copertina articolo/categoria che lo fa gia'.

    public function test_admin_profile_photo_upload_generates_responsive_variants(): void
    {
        config(['media.responsive_widths' => [480, 960]]);
        $editor = User::factory()->create(['role' => 'editor']);
        $image = UploadedFile::fake()->image('foto-profilo.jpg', 2000, 2000);

        $this->actingAs($editor)
            ->post(route('admin.profile.photo'), ['photo' => $image])
            ->assertSessionHasNoErrors();

        $editor->refresh();
        $this->assertNotNull($editor->photo);
        $this->assertFileExists(public_path('assets/img/'.$editor->photo));

        $variantPath = app(ImageService::class)->responsiveVariantPath($editor->photo, 480);
        $this->assertFileExists(public_path('assets/img/'.$variantPath));
    }

    public function test_redazione_profile_photo_upload_generates_responsive_variants(): void
    {
        config(['media.responsive_widths' => [480, 960]]);
        $author = User::factory()->create(['role' => 'author']);
        $image = UploadedFile::fake()->image('foto-profilo.jpg', 2000, 2000);

        $this->actingAs($author)
            ->post(route('redazione.profile.photo'), ['photo' => $image])
            ->assertSessionHasNoErrors();

        $author->refresh();
        $this->assertNotNull($author->photo);
        $this->assertFileExists(public_path('assets/img/'.$author->photo));

        $variantPath = app(ImageService::class)->responsiveVariantPath($author->photo, 480);
        $this->assertFileExists(public_path('assets/img/'.$variantPath));
    }
}
