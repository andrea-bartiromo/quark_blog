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
 * author-card.blade.php (foto autore nel box "Autore" sotto l'articolo) era
 * rimasto volutamente fuori da questa missione: FASE 6 aveva verificato che
 * usava una radice di storage diversa (storage/) da quella scritta dai
 * controller di upload (assets/img/), e la missione vietava di normalizzare
 * quella radice alla cieca senza prima confermare quale delle due fosse
 * quella reale. Prompt 283-286 ha chiuso quella verifica: nessun controller
 * scrive mai in storage/ (il symlink public/storage non esiste nemmeno in
 * questo repository), quindi la card autore sotto l'articolo restituiva un
 * 404 permanente ogni volta che un autore aveva una foto caricata. Fix e
 * relativa copertura ora vivono qui sotto, nella sezione dedicata.
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

    // ---- /articolo/{slug}: author-card (Prompt 283-286, fix 404 storage/) ----

    public function test_articolo_author_card_photo_uses_assets_img_not_the_unused_storage_symlink(): void
    {
        $author = $this->author();
        $this->placeCoverWithVariantsAt('author-card-avatar.jpg', 800, 800);
        $author->update(['photo' => 'author-card-avatar.jpg']);
        $article = $this->publishedArticle($author, ['title' => 'Articolo con autore fotografato']);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        preg_match('/kairus-author-card__avatar.*?<\/div>/s', $response->getContent(), $avatarBlock);
        $this->assertNotEmpty($avatarBlock, 'Blocco avatar della author-card non trovato in pagina.');

        // Il fix sostituisce asset('storage/'.photo) — un simlink mai
        // creato in questo repository — con il componente responsive che
        // risolve dalla radice realmente scritta dai controller di upload.
        $this->assertStringNotContainsString('/storage/', $avatarBlock[0]);
        $this->assertStringContainsString('src="'.asset('assets/img/author-card-avatar.jpg').'"', $avatarBlock[0]);
        $this->assertStringContainsString('srcset="'.asset('assets/img/author-card-avatar-480w.jpg').' 480w, '
            .asset('assets/img/author-card-avatar.jpg').' 800w"', $avatarBlock[0]);
        $this->assertStringContainsString('alt="'.$author->name.'"', $avatarBlock[0]);
    }

    /**
     * Prompt 12 (programma 100-prompt Kairus, root-causato durante la
     * revisione di un'altra PR): author-card.blade.php passava
     * `alt="{{ $article->author->name }}"` (attributo letterale, già
     * HTML-escaped da Blade) a <x-responsive-image>, che a sua volta
     * esegue `alt="{{ $alt }}"` internamente — un secondo escape sopra al
     * primo. Un nome con un carattere HTML-speciale come l'apostrofo
     * risultava quindi doppiamente codificato (`&amp;#039;` invece di
     * `&#039;`). Nome fisso qui (non Faker) perché il bug si manifestava
     * solo quando la generazione casuale includeva per caso un simile
     * carattere — non riproducibile in modo affidabile altrimenti.
     */
    public function test_articolo_author_card_photo_alt_is_html_escaped_exactly_once(): void
    {
        $author = $this->author();
        $author->update(['name' => "Jason D'Amore DVM"]);
        $this->placeCoverWithVariantsAt('author-card-apostrophe.jpg', 800, 800);
        $author->update(['photo' => 'author-card-apostrophe.jpg']);
        $article = $this->publishedArticle($author, ['title' => 'Articolo con autore apostrofo']);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        preg_match('/kairus-author-card__avatar.*?<\/div>/s', $response->getContent(), $avatarBlock);
        $this->assertNotEmpty($avatarBlock, 'Blocco avatar della author-card non trovato in pagina.');

        $this->assertStringContainsString('alt="'.e("Jason D'Amore DVM").'"', $avatarBlock[0]);
        $this->assertStringNotContainsString('&amp;#039;', $avatarBlock[0]);
    }

    public function test_articolo_author_card_falls_back_gracefully_when_photo_file_is_missing_on_disk(): void
    {
        // Stesso fallback legacy gia' verificato per /autore/{user}: un
        // dato "photo" senza file fisico corrispondente non deve mai
        // generare un errore o una pagina rotta.
        $author = $this->author();
        $author->update(['photo' => 'author-che-non-esiste.jpg']);
        $article = $this->publishedArticle($author, ['title' => 'Articolo con foto autore mancante']);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('src="'.asset('assets/img/author-che-non-esiste.jpg').'"', false);
        $response->assertDontSee('/storage/', false);
    }

    public function test_articolo_author_card_still_shows_initial_placeholder_when_no_photo_is_set(): void
    {
        $author = User::factory()->create(['role' => 'author', 'name' => 'Autrice Senza Foto', 'photo' => null]);
        $article = $this->publishedArticle($author, ['title' => 'Articolo con autore senza foto']);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        preg_match('/kairus-author-card__avatar.*?<\/div>/s', $response->getContent(), $avatarBlock);
        $this->assertNotEmpty($avatarBlock, 'Blocco avatar della author-card non trovato in pagina.');
        $this->assertStringNotContainsString('<img', $avatarBlock[0]);
        $this->assertStringContainsString(mb_substr($author->name, 0, 2), $avatarBlock[0]);
    }

    public function test_articolo_author_card_leaves_a_legacy_slash_path_photo_untouched(): void
    {
        // docs/MISSION_75_USER_PHOTO_PRODUCTION_PREFLIGHT.md: non e'
        // provato che ogni riga "photo" di produzione sia stata scritta
        // dal codice attuale (che salva sempre un disk_name senza slash).
        // Un valore con slash e' trattato come possibile path legacy e
        // deve continuare a passare per asset('storage/'...) esattamente
        // come prima del fix, finche' quella mission non fornisce i fatti
        // di produzione necessari a normalizzarlo in sicurezza.
        $author = $this->author();
        $author->update(['photo' => 'legacy/avatar-autore.jpg']);
        $article = $this->publishedArticle($author, ['title' => 'Articolo con foto autore legacy']);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        preg_match('/kairus-author-card__avatar.*?<\/div>/s', $response->getContent(), $avatarBlock);
        $this->assertNotEmpty($avatarBlock, 'Blocco avatar della author-card non trovato in pagina.');
        $this->assertStringContainsString('src="'.asset('storage/legacy/avatar-autore.jpg').'"', $avatarBlock[0]);
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
