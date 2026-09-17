<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TuringEditorialAssetsTest extends TestCase
{
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
     * Cantiere 64 (programma "100 cantieri Kairus"): `resources/views/turing.blade.php`
     * è il file inutilizzato scoperto e mai referenziato da alcun
     * controller (Cantiere 66, TuringChapterIndexNoJavaScriptTest) — questo
     * test verificava da sempre l'assenza di asset legacy nel file SBAGLIATO,
     * mai in quello realmente renderizzato (turing/index.blade.php).
     * Corretto, e ampliato agli altri 3 capitoli reali (legacy/computation/
     * intelligence) prima non coperti da questa verifica.
     */
    public function test_turing_hardcoded_references_use_current_webp_assets(): void
    {
        $files = [
            app_path('Http/Controllers/TuringPageController.php'),
            resource_path('views/turing/index.blade.php'),
            resource_path('views/turing/enigma.blade.php'),
            resource_path('views/turing/legacy.blade.php'),
            resource_path('views/turing/computation.blade.php'),
            resource_path('views/turing/intelligence.blade.php'),
            resource_path('views/turing/ai.blade.php'),
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

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            $this->assertIsString($contents);

            foreach ($legacyAssets as $asset) {
                $this->assertStringNotContainsString($asset, $contents, "Legacy Turing asset [{$asset}] remains in {$file}.");
            }
        }
    }

    public function test_turing_asset_paths_are_unique(): void
    {
        $assets = collect(self::turingAssets())->flatten()->all();

        $this->assertCount(count($assets), array_unique($assets));
    }

    /**
     * Cantiere 64 (programma "100 cantieri Kairus"): fino a questo
     * cantiere, i 12 asset editoriali dedicati al solo capitolo Enigma
     * (public/images/turing/enigma/... — percorso e formato PNG diversi
     * dai 13 asset WebP hub/pannello sopra, che vivono in
     * public/assets/img/turing/...) non avevano ALCUNA verifica
     * automatica: legacy/computation/intelligence riusano solo i pannelli
     * dell'hub già coperti, ma Enigma ha un proprio apparato iconografico
     * distinto (verificato con ispezione diretta di enigma.blade.php),
     * mai reso "verificabile" prima d'ora.
     */
    public static function enigmaEditorialAssets(): array
    {
        return [
            'hero fallback' => ['images/turing/enigma/hero-enigma.png'],
            'anatomy cutaway fallback' => ['images/turing/enigma/cutaway-enigma.png'],
            'daily key settings' => ['images/turing/enigma/daily-key-settings.png'],
            'machine anatomy' => ['images/turing/enigma/editorial/02_enigma-machine-anatomy.png'],
            'rotor exploded view' => ['images/turing/enigma/editorial/03_rotor-exploded-view.png'],
            'electrical signal path' => ['images/turing/enigma/editorial/04_electrical-signal-path.png'],
            'bletchley park operations room' => ['images/turing/enigma/editorial/05_bletchley-park-operations-room.png'],
            'bombe machine' => ['images/turing/enigma/editorial/09_bombe-machine.png'],
            'bombe detail' => ['images/turing/enigma/editorial/10_bombe-detail.png'],
            'german operator' => ['images/turing/enigma/german-operator.png'],
            'plugboard' => ['images/turing/enigma/plugboard.png'],
            'hut 8 exterior' => ['images/turing/enigma/editorial/11_hut-8-exterior.png'],
        ];
    }

    #[DataProvider('enigmaEditorialAssets')]
    public function test_enigma_editorial_assets_exist_as_real_png_files(string $asset): void
    {
        $this->assertStringEndsWith('.png', $asset);

        $path = public_path($asset);

        $this->assertFileExists($path, "Enigma editorial asset [{$asset}] must exist.");

        $image = getimagesize($path);

        $this->assertIsArray($image, "[{$asset}] must be a real, decodable image, not a placeholder file.");
        $this->assertSame('image/png', $image['mime']);
    }

    public function test_enigma_editorial_asset_paths_are_unique(): void
    {
        $assets = collect(self::enigmaEditorialAssets())->flatten()->all();

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
     * sopra e i 10 rimanenti di questo capitolo — è largo almeno 1200px
     * (lo standard tecnico dichiarato in
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
            $this->assertLessThan(1200, $image[0], "[{$asset}] atteso ancora sotto lo standard tecnico di 1200px — se questo test fallisce, l'immagine è stata sostituita: aggiornare test_enigma_editorial_assets_exist_as_real_png_files con un controllo di larghezza minima e rimuovere questo test.");
        }

        $this->markTestSkipped('Gap reale e documentato (vedi docblock): richiede una nuova immagine scelta da un editor umano, non un fix automatico. Restano solo 287×289px.');
    }
}
