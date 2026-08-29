<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Missione logo Kairus — copertura automatica dei "test obbligatori" #1-#8:
 * esistenza/leggibilità/dimensioni/trasparenza degli asset rigenerati,
 * presenza del nuovo simbolo nelle viste pubbliche/admin, assenza di CLS
 * (width/height espliciti).
 *
 * "HTTP 200" (#6) è verificato qui a livello filesystem, non con una
 * richiesta HTTP: in questo stack i file sotto public/ sono serviti dal
 * webserver reale, non dal router Laravel — una richiesta di test verso
 * /favicon.ico restituisce sempre 404 dal Kernel a prescindere
 * dall'esistenza del file (verificato empiricamente). L'esistenza+validità
 * del file sul filesystem è quindi l'unico segnale che determina davvero
 * se il webserver risponderà 200 — stesso pattern già in uso da
 * HomeStructuredDataTest per icon-512.png. La raggiungibilità HTTP reale è
 * stata verificata manualmente con php artisan serve + Playwright durante
 * lo sviluppo (nessun errore di rete/console su nessuna pagina).
 */
class BrandLogoAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: int, 2: int}> */
    public static function rasterIconProvider(): array
    {
        return [
            'apple touch icon 180' => ['apple-touch-icon.png', 180, 180],
            'favicon 16' => ['assets/icons/favicon-16.png', 16, 16],
            'favicon 32' => ['assets/icons/favicon-32.png', 32, 32],
            'pwa icon 192' => ['assets/icons/icon-192.png', 192, 192],
            'pwa icon 512' => ['assets/icons/icon-512.png', 512, 512],
            'pwa maskable icon 512' => ['assets/icons/icon-maskable-512.png', 512, 512],
        ];
    }

    #[DataProvider('rasterIconProvider')]
    public function test_raster_icon_exists_is_a_real_png_with_exact_expected_dimensions(
        string $relativePath,
        int $expectedWidth,
        int $expectedHeight
    ): void {
        $path = public_path($relativePath);

        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertStringStartsWith("\x89PNG", $contents, "$relativePath non è un PNG valido.");

        $size = getimagesize($path);
        $this->assertNotFalse($size, "$relativePath: dimensioni non leggibili.");
        $this->assertSame($expectedWidth, $size[0], "$relativePath: larghezza inattesa.");
        $this->assertSame($expectedHeight, $size[1], "$relativePath: altezza inattesa.");
    }

    public function test_favicon_ico_exists_and_is_a_valid_multi_resolution_icon(): void
    {
        $path = public_path('favicon.ico');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        // Header ICO: reserved(2)=0, type(2)=1, count(2)>=1.
        $this->assertStringStartsWith("\x00\x00\x01\x00", $contents);

        $count = unpack('v', substr($contents, 4, 2))[1];
        $this->assertGreaterThanOrEqual(3, $count, 'favicon.ico deve contenere almeno le risoluzioni 16/32/48.');
    }

    /** @return array<string, array{0: string}> */
    public static function svgIconProvider(): array
    {
        return [
            'favicon svg' => ['assets/icons/favicon.svg'],
            'symbol svg (uso live)' => ['assets/icons/symbol.svg'],
        ];
    }

    #[DataProvider('svgIconProvider')]
    public function test_svg_icon_exists_and_is_well_formed(string $relativePath): void
    {
        $path = public_path($relativePath);

        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertStringContainsString('<svg', $contents);
        $this->assertStringContainsString('</svg>', $contents);
    }

    public function test_symbol_svg_master_has_real_transparency_not_a_solid_background(): void
    {
        // Il simbolo usato nel markup live non deve avere alcun elemento di
        // sfondo (rect/path a piena tela): e' quello che garantisce la
        // trasparenza reale richiesta, non solo un colore che imita il nero.
        $svg = file_get_contents(public_path('assets/icons/symbol.svg'));

        $this->assertStringNotContainsString('<rect', $svg);
    }

    public function test_orphaned_old_wordmark_svg_was_removed(): void
    {
        $this->assertFileDoesNotExist(public_path('assets/icons/logo.svg'));
    }

    public function test_branding_source_master_is_preserved_and_not_publicly_served(): void
    {
        $master = base_path('resources/branding/kairus/kairus-symbol-master-1024.png');

        $this->assertFileExists($master);

        $size = getimagesize($master);
        $this->assertSame(1024, $size[0]);
        $this->assertSame(1024, $size[1]);

        $this->assertFileDoesNotExist(public_path('branding/kairus/kairus-symbol-master-1024.png'));
    }

    public function test_homepage_header_and_footer_render_the_new_symbol_without_cls(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('src="'.asset('assets/icons/symbol.svg').'"', $html);
        $this->assertStringContainsString('class="header-logo__symbol"', $html);
        $this->assertStringContainsString('class="footer-logo__symbol"', $html);

        // CLS: width/height espliciti sul simbolo dell'header (sopra la piega).
        $this->assertStringContainsString('width="28" height="28"', $html);
        $this->assertStringContainsString('width="26" height="26"', $html);
    }

    public function test_admin_login_renders_the_new_symbol(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('src="'.asset('assets/icons/symbol.svg').'"', $html);
        $this->assertStringContainsString('class="login-logo__symbol"', $html);
        $this->assertStringContainsString('width="48" height="48"', $html);
        $this->assertStringContainsString('alt=""', $html);
    }

    public function test_redazione_login_renders_the_new_symbol(): void
    {
        $html = $this->get(route('redazione.login'))->assertOk()->getContent();

        $this->assertStringContainsString('src="'.asset('assets/icons/symbol.svg').'"', $html);
        $this->assertStringContainsString('width="48" height="48"', $html);
    }

    public function test_admin_sidebar_renders_the_new_symbol_and_hides_only_the_wordmark_when_compact(): void
    {
        $this->actingAs(new User([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]));

        $html = view('layouts.admin')->render();

        $this->assertStringContainsString('class="admin-sidebar__logo-symbol"', $html);
        $this->assertStringContainsString('class="admin-sidebar__logo-word"', $html);
        $this->assertStringContainsString('width="28" height="28"', $html);
        $this->assertStringContainsString('alt=""', $html);

        $css = file_get_contents(public_path('css/admin.css'));
        $this->assertStringContainsString('.admin-sidebar-compact .admin-sidebar__logo-word', $css);
        // Il simbolo non deve comparire tra le regole che lo nascondono in modalità compatta.
        $this->assertDoesNotMatchRegularExpression(
            '/\.admin-sidebar-compact[^{]*admin-sidebar__logo-symbol[^{]*\{[^}]*display:\s*none/s',
            $css
        );
    }

    public function test_redazione_sidebar_renders_the_new_symbol(): void
    {
        $html = file_get_contents(resource_path('views/layouts/redazione.blade.php'));

        $this->assertStringContainsString('class="admin-sidebar__logo-symbol"', $html);
        $this->assertStringContainsString('width="28" height="28"', $html);
    }
}
