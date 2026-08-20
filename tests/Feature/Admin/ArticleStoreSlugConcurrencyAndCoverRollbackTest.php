<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * S10 — test di INTEGRAZIONE per Admin\ArticleController::store(): copre il
 * comportamento COMBINATO introdotto separatamente da #221 (retry-una-sola-
 * volta su UniqueConstraintViolationException, S6 FASE 4) e #224 (rollback
 * della copertina appena caricata se Article::create() fallisce, S9), che i
 * test originali delle due PR non esercitano insieme: nessuno dei due upload
 * una copertina durante una vera collisione di slug.
 *
 * La collisione viene simulata inserendo, dentro il listener Article::creating(),
 * una riga concorrente con lo stesso slug appena prima che Eloquent esegua la
 * INSERT reale — la stessa finestra SELECT-poi-INSERT che una vera concorrenza
 * multi-processo produrrebbe, senza bisogno di due processi reali.
 */
class ArticleStoreSlugConcurrencyAndCoverRollbackTest extends TestCase
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

    private function articlePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Articolo con copertina',
            'excerpt' => 'Sommario di prova',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
        ], $overrides);
    }

    private function insertCompetingArticleRow(int $userId, string $title, string $slug, string $category): void
    {
        DB::table('articles')->insert([
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => 'Riga concorrente inserita per simulare una vera race sullo slug.',
            'body' => '<p>Riga concorrente.</p>',
            'category' => $category,
            'status' => Article::STATUS_DRAFT,
            'featured' => false,
            'read_minutes' => 1,
            'views' => 0,
            'verification_status' => 'unverified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Invarianti 1 + 2 + 5 combinate: una collisione reale sullo slug (non solo
    // simulata a livello di SELECT) deve ancora attivare il retry di #221 (mai un
    // 500), e la copertina appena caricata nella stessa richiesta — che alla
    // fine VIENE creata con successo dopo il retry — non deve mai essere
    // ripulita: la protezione di #224 scatta solo su un fallimento definitivo,
    // non su un retry riuscito.
    public function test_a_real_slug_collision_retries_transparently_and_keeps_the_uploaded_cover(): void
    {
        $editor = $this->editor();
        $newCover = UploadedFile::fake()->image('nuova-store.jpg', 800, 600);

        $fired = false;
        Article::creating(function (Article $model) use (&$fired, $editor) {
            if (! $fired) {
                $fired = true;
                $this->insertCompetingArticleRow($editor->id, $model->title, $model->slug, $model->category);
            }
        });

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'title' => 'Titolo in collisione reale',
            'cover_image_upload' => $newCover,
        ]));

        $response->assertRedirect(route('admin.articles'));
        $this->assertNotSame(500, $response->getStatusCode(), 'Una collisione reale sullo slug ha prodotto un 500 non gestito.');

        $realArticle = Article::where('title', 'Titolo in collisione reale')
            ->whereNotNull('cover_image')
            ->first();

        $this->assertNotNull($realArticle, 'Il retry dopo la collisione avrebbe dovuto comunque creare il vero articolo.');
        $this->assertNotSame('titolo-in-collisione-reale', $realArticle->slug, 'Lo slug deve essere stato rigenerato dal retry, non riusare quello in collisione.');

        // La copertina della richiesta riuscita (dopo retry) NON deve essere
        // stata ripulita: sopravvive live, referenziata, e con la sua riga
        // Media, esattamente come un upload andato a buon fine.
        $this->assertFileExists(public_path('assets/img/'.$realArticle->cover_image));
        $this->assertNotNull(
            Media::where('disk_name', $realArticle->cover_image)->first(),
            'La copertina di un articolo creato con successo dopo il retry deve restare registrata in Libreria media.'
        );
    }

    // Invarianti 2 + 3 + 4: se anche il retry (l'unico concesso da #221) urta di
    // nuovo contro il vincolo UNIQUE, l'eccezione deve propagare oltre il blocco
    // interno — e a quel punto la protezione di #224 deve ripulire la copertina
    // appena caricata in QUESTA richiesta, senza toccare nient'altro.
    public function test_when_the_single_retry_also_collides_the_new_cover_is_rolled_back(): void
    {
        $editor = $this->editor();
        $newCover = UploadedFile::fake()->image('fallita-store.jpg', 800, 600);

        Article::creating(function (Article $model) use ($editor) {
            $this->insertCompetingArticleRow($editor->id, $model->title, $model->slug, $model->category);
        });

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'title' => 'Titolo sempre in collisione',
            'cover_image_upload' => $newCover,
        ]));

        // La doppia collisione (prima INSERT + retry) supera il singolo retry
        // che #221 concede deliberatamente: qui un errore che raggiunge
        // l'handler globale e' l'esito ATTESO (mai un retry-loop illimitato),
        // cio' che conta e' che il rollback della copertina sia comunque
        // avvenuto prima che l'eccezione risalisse.
        $response->assertServerError();

        $this->assertSame(
            2,
            Article::where('title', 'Titolo sempre in collisione')->count(),
            'Solo le due righe concorrenti finte (una per tentativo) devono esistere: nessun vero articolo deve essere stato creato.'
        );
        $this->assertSame(
            0,
            Article::where('title', 'Titolo sempre in collisione')->whereNotNull('cover_image')->count(),
            'Nessuna riga con questo titolo deve avere una copertina: il vero articolo non e\' mai stato creato con successo.'
        );

        // Senza la pulizia, il file e la riga Media della copertina caricata in
        // questa richiesta sopravvivrebbero orfani, live e pubblicamente
        // raggiungibili, senza che alcun articolo li referenzi mai.
        $this->assertSame(0, Media::where('filename', 'fallita-store.jpg')->count());
        $this->assertSame([], glob(public_path('assets/img/*.webp')) ?: []);
    }

    // Invariante 3: una copertina preesistente e valida (scelta dalla libreria
    // media, non un nuovo upload) non deve mai essere toccata dal rollback,
    // nemmeno quando la stessa richiesta urta comunque contro una collisione
    // di slug che poi fallisce definitivamente.
    public function test_an_existing_library_cover_survives_a_definitive_slug_collision_failure(): void
    {
        $editor = $this->editor();

        $libraryMedia = Media::create([
            'user_id' => $editor->id,
            'filename' => 'esistente.jpg',
            'disk_name' => 'esistente-'.uniqid('', true).'.webp',
            'mime_type' => 'image/webp',
            'size' => 1234,
        ]);

        $absoluteLibraryPath = public_path('assets/img/'.$libraryMedia->disk_name);
        @mkdir(dirname($absoluteLibraryPath), 0777, true);
        file_put_contents($absoluteLibraryPath, 'contenuto fittizio');

        Article::creating(function (Article $model) use ($editor) {
            $this->insertCompetingArticleRow($editor->id, $model->title, $model->slug, $model->category);
        });

        // atteso: nessuna copertina nuova e' stata caricata in questa
        // richiesta, quindi $newCoverWasUploaded resta false e il ramo di
        // pulizia di #224 non deve nemmeno tentare di toccare questo asset —
        // indipendentemente dal fatto che la doppia collisione sullo slug
        // faccia comunque fallire definitivamente la richiesta.
        $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'title' => 'Titolo con copertina esistente in collisione',
            'cover_image' => $libraryMedia->disk_name,
        ]));

        $this->assertFileExists($absoluteLibraryPath, 'Una copertina preesistente scelta dalla libreria non deve mai essere cancellata da un fallimento di Article::create().');
        $this->assertNotNull(Media::find($libraryMedia->id), 'La riga Media di una copertina preesistente non deve mai essere ritirata da un fallimento non correlato.');
    }
}
