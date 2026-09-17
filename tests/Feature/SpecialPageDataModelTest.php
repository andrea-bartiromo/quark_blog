<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cantiere 56 (programma "100 cantieri Kairus"). "Modello dati minimo
 * Speciali editoriali": verifica di regressione, non nuovo codice — come
 * già per i Cantieri 66/73/79. Ispezione diretta ha confermato che
 * `special_pages` (migrazione 2026_05_09_170000) e `App\Models\SpecialPage`
 * sono già un modello dati minimo e generico per QUALSIASI Speciale
 * editoriale (slug/title/description/content JSON/is_active), non
 * accoppiato a Turing: nessuna colonna, vincolo o metodo del modello fa
 * riferimento a "turing". Anche il livello media (MediaReferenceService,
 * MediaUsageService, MediaClassificationService) itera già su TUTTI i
 * `SpecialPage`, non solo sullo slug 'turing'.
 *
 * Il gap reale trovato: nessun test esistente lo dimostra con uno slug
 * diverso da 'turing' — ogni test `SpecialPage` in questa codebase (v.
 * TuringSeeder, TuringController, tutti i test Turing esistenti) usa solo
 * quello slug, quindi la genericità del modello resta un'asserzione mai
 * verificata concretamente. Questi test colmano quel gap usando un
 * secondo slug sintetico, chiaramente di test (non uno Speciale
 * editoriale reale: costruirne uno violerebbe il divieto di inventare
 * contenuto editoriale), per provare che il modello funziona a
 * prescindere da Turing.
 */
class SpecialPageDataModelTest extends TestCase
{
    use RefreshDatabase;

    private const SYNTHETIC_SLUG = 'cantiere-56-verifica-genericita';

    public function test_slug_is_unique_at_the_database_level(): void
    {
        SpecialPage::create([
            'slug' => self::SYNTHETIC_SLUG,
            'title' => 'Prima riga',
        ]);

        $this->expectException(QueryException::class);

        SpecialPage::create([
            'slug' => self::SYNTHETIC_SLUG,
            'title' => 'Seconda riga con lo stesso slug',
        ]);
    }

    public function test_content_round_trips_as_an_array_for_a_slug_that_is_not_turing(): void
    {
        $content = [
            'hero' => ['title' => 'Titolo di prova', 'lead' => 'Testo di prova'],
            'cards' => [
                ['label' => 'Uno', 'url' => '/esempio/uno'],
                ['label' => 'Due', 'url' => '/esempio/due'],
            ],
        ];

        SpecialPage::create([
            'slug' => self::SYNTHETIC_SLUG,
            'title' => 'Speciale sintetico',
            'content' => $content,
        ]);

        $fresh = SpecialPage::where('slug', self::SYNTHETIC_SLUG)->first();

        $this->assertIsArray($fresh->content);
        $this->assertSame($content, $fresh->content);
    }

    public function test_content_is_nullable_and_by_slug_handles_it_without_error(): void
    {
        SpecialPage::create([
            'slug' => self::SYNTHETIC_SLUG,
            'title' => 'Speciale senza contenuto',
            'is_active' => true,
        ]);

        $fresh = SpecialPage::where('slug', self::SYNTHETIC_SLUG)->first();
        $this->assertNull($fresh->content);

        $fallback = ['hero' => ['title' => 'Fallback']];
        $this->assertSame(
            $fallback,
            SpecialPage::bySlug(self::SYNTHETIC_SLUG, $fallback),
            'un content nullo non deve alterare il fallback fornito.'
        );
    }

    public function test_is_active_defaults_to_true_at_the_database_level_even_without_the_eloquent_model(): void
    {
        DB::table('special_pages')->insert([
            'slug' => self::SYNTHETIC_SLUG,
            'title' => 'Riga inserita senza specificare is_active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = SpecialPage::where('slug', self::SYNTHETIC_SLUG)->first();

        $this->assertTrue($page->is_active);
    }

    /**
     * Prova di genericità: due Speciali distinti (uno è 'turing', l'altro
     * no) coesistono senza interferenze — bySlug() su uno non è
     * influenzato dalla presenza/contenuto dell'altro.
     */
    public function test_by_slug_resolves_a_second_speciale_independently_from_turing(): void
    {
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'content' => ['hero' => ['title' => 'Alan Turing']],
            'is_active' => true,
        ]);

        SpecialPage::create([
            'slug' => self::SYNTHETIC_SLUG,
            'title' => 'Speciale sintetico',
            'content' => ['hero' => ['title' => 'Titolo sintetico']],
            'is_active' => true,
        ]);

        $resolved = SpecialPage::bySlug(self::SYNTHETIC_SLUG, ['hero' => ['title' => 'Fallback']]);

        $this->assertSame('Titolo sintetico', $resolved['hero']['title']);
    }

    public function test_by_slug_falls_back_when_the_page_is_inactive(): void
    {
        SpecialPage::create([
            'slug' => self::SYNTHETIC_SLUG,
            'title' => 'Speciale disattivato',
            'content' => ['hero' => ['title' => 'Non dovrebbe mai comparire']],
            'is_active' => false,
        ]);

        $fallback = ['hero' => ['title' => 'Fallback']];

        $this->assertSame($fallback, SpecialPage::bySlug(self::SYNTHETIC_SLUG, $fallback));
    }

    public function test_by_slug_falls_back_when_no_row_exists_for_the_slug(): void
    {
        $fallback = ['hero' => ['title' => 'Fallback']];

        $this->assertSame(
            $fallback,
            SpecialPage::bySlug('slug-che-non-esiste-in-nessun-seeder', $fallback)
        );
    }
}
