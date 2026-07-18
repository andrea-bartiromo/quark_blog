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
            'hero' => ['turing-hero.webp'],
            'intro background' => ['turing-intro.webp'],
            'legacy panel' => ['turing-legacy-panel.webp'],
            'enigma background' => ['turing-enigma-background.webp'],
            'enigma panel' => ['turing-enigma-panel.webp'],
            'universal machine background' => ['turing-universal-machine-background.webp'],
            'turing test background' => ['turing-test-background.webp'],
            'modern ai background' => ['turing-ai-background.webp'],
            'modern ai panel' => ['turing-ai-panel.webp'],
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

        $path = public_path('assets/img/' . $asset);

        $this->assertFileExists($path, "Turing asset [{$asset}] must exist.");

        $image = getimagesize($path);

        $this->assertIsArray($image);
        $this->assertSame(1200, $image[0]);
        $this->assertSame(675, $image[1]);
        $this->assertSame('image/webp', $image['mime']);
    }

    public function test_turing_hardcoded_references_use_current_webp_assets(): void
    {
        $files = [
            app_path('Http/Controllers/TuringPageController.php'),
            resource_path('views/turing.blade.php'),
            resource_path('views/turing/enigma.blade.php'),
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
}
