<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TuringEditorialAssetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function turingAssets(): array
    {
        return [
            'hero' => ['turing/hero/turing-hero.webp'],
            'intro background' => ['turing/hero/turing-intro.webp'],
            'legacy panel' => ['turing/backgrounds/turing-legacy-panel.webp'],
            'enigma background' => ['turing/enigma/turing-enigma-background.webp'],
            'enigma panel' => ['turing/enigma/turing-enigma-panel.webp'],
            'universal machine background' => ['turing/backgrounds/turing-universal-machine-background.webp'],
            'turing test background' => ['turing/backgrounds/turing-test-background.webp'],
            'modern ai background' => ['turing/backgrounds/turing-ai-background.webp'],
            'modern ai panel' => ['turing/backgrounds/turing-ai-panel.webp'],
            'enigma block' => ['turing/enigma.webp'],
            'universal machine block' => ['turing/universal-machine.webp'],
            'turing test block' => ['turing/turing-test.webp'],
            'modern ai block' => ['turing/modern-ai.webp'],
        ];
    }

    #[DataProvider('turingAssets')]
    public function test_turing_editorial_assets_exist_as_real_webp_files(string $asset): void
    {
        $this->assertStringEndsWith('.webp', $asset);

        $path = public_path('assets/img/'.$asset);

        $this->assertFileExists($path, "Turing asset [{$asset}] must exist.");

        $image = getimagesize($path);

        $this->assertIsArray($image);
        $this->assertSame(1200, $image[0]);
        $this->assertSame(675, $image[1]);
        $this->assertSame('image/webp', $image['mime']);
    }

    /**
     * Cantiere 64 (programma "100 cantieri Kairus"), corretto due volte:
     *
     * (1) `resources/views/turing.blade.php` è il file inutilizzato
     * scoperto e mai referenziato da alcun controller (Cantiere 66,
     * TuringChapterIndexNoJavaScriptTest) — questo test verificava da
     * sempre l'assenza di asset legacy nel file SBAGLIATO, mai in quello
     * realmente renderizzato.
     *
     * (2) Codex (PR #630, P2): anche puntando al file giusto
     * (turing/index.blade.php), leggerne il solo sorgente grezzo non
     * basta — quel file delega quasi tutto il proprio markup a 7
     * `@include('turing.partials.*')`, il cui contenuto reale non compare
     * nel file letto direttamente. Un asset legacy introdotto in una di
     * quelle partial (hero/intro/editorial-blocks/legacy-section/...)
     * avrebbe superato la verifica pur comparendo davvero su `/turing`.
     * Corretto renderizzando la vera risposta HTTP di ogni pagina reale
     * (hub + tutti e 5 i capitoli) invece di leggere sorgenti Blade —
     * l'unico modo di essere certi di ciò che finisce davvero nell'HTML,
     * indipendentemente da quale file/partial lo introduce.
     */
    public function test_turing_hardcoded_references_use_current_webp_assets(): void
    {
        config(['turing.chapters_public' => true]);

        $pages = [
            'hub' => $this->get(route('turing'))->getContent(),
            'enigma' => $this->get(route('turing.enigma'))->getContent(),
            'legacy' => $this->get(route('turing.legacy'))->getContent(),
            'computation' => $this->get(route('turing.computation'))->getContent(),
            'intelligence' => $this->get(route('turing.intelligence'))->getContent(),
            'ai' => $this->get(route('turing.ai'))->getContent(),
        ];

        $legacyAssets = [
            'turing-hero-bg.jpg',
            'turing-intro-bg.jpg',
            'turing-legacy-panel.jpg',
            'turing-enigma-bg.jpg',
            'turing-enigma-panel.jpg',
            'turing-universal-machine-bg.jpg',
            'turing-test-bg.jpg',
            'turing-ai-bg.jpg',
            'turing-ai-panel.jpg',
            'turing/enigma.jpg',
            'turing/macchina-universale.jpg',
            'turing/test-turing.png',
            'turing/ai-moderna.jpg',
        ];

        foreach ($pages as $page => $html) {
            foreach ($legacyAssets as $asset) {
                $this->assertStringNotContainsString($asset, $html, "Legacy Turing asset [{$asset}] appears on the rendered '{$page}' page.");
            }
        }
    }

    public function test_turing_asset_paths_are_unique(): void
    {
        $assets = collect(self::turingAssets())->flatten()->all();

        $this->assertCount(count($assets), array_unique($assets));
    }

    /**
     * Cantiere 64 (programma "100 cantieri Kairus") — finding reale,
     * documentato qui invece di "corretto" silenziosamente: scegliere
     * un'immagine sostitutiva è una decisione editoriale/visiva, fuori
     * dal perimetro di scrittura automatica di questo programma (nessuna
     * invenzione di contenuto).
     *
     * Ogni altro asset editoriale reale dello Speciale — i 13 hub/pannello
     * sopra e i 10 rimanenti di questo capitolo (elencati come unica
     * fonte di verità in TuringEnigmaPageTest::enigmaAssets(), Codex PR
     * #630 P2 — mai una seconda lista duplicata qui) — è largo almeno
     * 1200px (lo standard tecnico dichiarato in
     * docs/04_Turing_Visual/Registro_Asset_Turing_v1.0.md). Questi due
     * soli sono 287×289px: `resolveAsset()` in enigma.blade.php li usa
     * come fallback per `.enigma-hero` (min-height: 86vh, background-size:
     * cover — public/css/turing-enigma.css) e per la figura "Anatomia
     * della macchina" — nessun override CMS è configurato per questi campi
     * in TuringSeeder, quindi questo è lo stato REALMENTE servito oggi, non
     * un caso limite teorico: un'immagine di 287px viene ingrandita per
     * coprire un banner quasi a schermo intero, con una perdita di
     * qualità visibile.
     */
    public function test_known_gap_enigma_hero_and_anatomy_fallbacks_are_undersized_for_their_cover_usage(): void
    {
        $undersized = [
            'images/turing/enigma/hero-enigma.png',
            'images/turing/enigma/cutaway-enigma.png',
        ];

        foreach ($undersized as $asset) {
            $image = getimagesize(public_path($asset));
            $this->assertLessThan(1200, $image[0], "[{$asset}] atteso ancora sotto lo standard tecnico di 1200px — se questo test fallisce, l'immagine è stata sostituita: aggiornare TuringEnigmaPageTest::test_enigma_image_asset_is_a_real_decodable_png() con un controllo di larghezza minima e rimuovere questo test.");
        }

        $this->markTestSkipped('Gap reale e documentato (vedi docblock): richiede una nuova immagine scelta da un editor umano, non un fix automatico. Restano solo 287×289px.');
    }
}
