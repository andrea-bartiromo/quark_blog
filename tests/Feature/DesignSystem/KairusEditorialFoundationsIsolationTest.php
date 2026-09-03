<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Missione 16 — Kairus Editorial Foundations V1: test di isolamento.
 *
 * Deliberatamente senza alcuna dipendenza da `git diff`/`git log`: i
 * workflow CI di questo repository (tests.yml) fanno un checkout con
 * `actions/checkout@v4` senza `fetch-depth: 0`, quindi nel job che esegue
 * questa suite la storia Git oltre il commit corrente non è garantita. Ogni
 * verifica qui sotto ispeziona solo lo stato reale del working tree e il
 * comportamento effettivo dell'applicazione (rotte registrate), non una
 * cronologia di commit.
 */
class KairusEditorialFoundationsIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le viste vietate elencate nel cantiere ("NON TOCCARE"): se nessuna di
     * queste referenzia il nuovo sistema, i componenti Kairus non sono
     * stati montati da nessuna parte — l'invariante centrale del cantiere
     * ("fondamenta condivise, non ancora migrazione di alcuna pagina").
     */
    private const FORBIDDEN_VIEWS = [
        'home.blade.php',
        'articolo.blade.php',
        'notizie.blade.php',
        'categoria.blade.php',
        'ricerca.blade.php',
        'autore.blade.php',
        'turing.blade.php',
        'components/header.blade.php',
        'components/footer.blade.php',
    ];

    private const FORBIDDEN_DIRECTORIES = [
        'home',
        'articles',
        'content-clusters',
        'turing',
    ];

    public function test_no_new_route_points_to_the_kairus_component_namespace(): void
    {
        foreach (Route::getRoutes() as $route) {
            $actionName = (string) ($route->getActionName() ?? '');

            $this->assertStringNotContainsStringIgnoringCase(
                'kairus',
                $actionName,
                "La rotta {$route->uri()} referenzia il namespace kairus — nessuna nuova rotta è autorizzata in questo cantiere."
            );
        }
    }

    /**
     * "Kairus" (maiuscolo, nome del brand) compare legittimamente in tutto
     * il codice esistente — non è ciò che questi due test cercano. Cercano
     * invece i marcatori specifici del NUOVO sistema: il prefisso di classe
     * "kairus-", il nome del file "editorial-system(.css)" e il namespace
     * dei componenti "components.kairus" / "<x-kairus.".
     */
    private const NEW_SYSTEM_MARKERS = [
        'kairus-',
        'editorial-system',
        'components.kairus',
        '<x-kairus.',
    ];

    public function test_no_forbidden_view_references_the_new_editorial_system(): void
    {
        foreach (self::FORBIDDEN_VIEWS as $relativePath) {
            $path = resource_path('views/'.$relativePath);

            if (! is_file($path)) {
                continue; // Percorso non presente su questa baseline: nulla da proteggere.
            }

            $source = (string) file_get_contents($path);

            foreach (self::NEW_SYSTEM_MARKERS as $marker) {
                $this->assertStringNotContainsString($marker, $source, "{$relativePath} referenzia \"{$marker}\": il nuovo sistema editoriale non è ancora autorizzato qui.");
            }
        }
    }

    public function test_no_forbidden_directory_contains_a_reference_to_the_new_editorial_system(): void
    {
        foreach (self::FORBIDDEN_DIRECTORIES as $dir) {
            $path = resource_path('views/'.$dir);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                foreach (self::NEW_SYSTEM_MARKERS as $marker) {
                    $this->assertStringNotContainsString(
                        $marker,
                        $source,
                        $file->getPathname()." referenzia \"{$marker}\": il nuovo sistema editoriale non è ancora autorizzato in questa directory."
                    );
                }
            }
        }
    }

    public function test_no_migration_file_belongs_to_this_cantiere(): void
    {
        $migrationsPath = database_path('migrations');
        $files = glob($migrationsPath.'/*.php') ?: [];

        $this->assertNotEmpty($files, 'Directory migrations vuota o irraggiungibile.');

        foreach ($files as $file) {
            $this->assertStringNotContainsStringIgnoringCase(
                'kairus',
                basename($file),
                'Trovata una migration riconducibile a questo cantiere: nessuna migration è autorizzata (Kairus Editorial Foundations V1 è solo CSS/componenti Blade).'
            );
        }
    }

    /**
     * Non verifica byte-per-byte (richiederebbe una baseline Git non
     * disponibile in CI): verifica che i 5 CSS pubblici esistenti citati
     * nell'audit (Missione 01) esistano ancora e restino referenziati in
     * head.blade.php esattamente come prima, con la sola aggiunta del
     * nuovo editorial-system.css in coda.
     */
    public function test_existing_public_css_files_still_exist_and_remain_referenced(): void
    {
        $existingCss = [
            'style.css',
            'frontend-hardening.css',
            'public-premium.css',
            'public-unified.css',
            'premium-fixes.css',
        ];

        foreach ($existingCss as $filename) {
            $this->assertFileExists(public_path('css/'.$filename), "css/{$filename} è stato rimosso: non autorizzato.");
        }

        $head = (string) file_get_contents(resource_path('views/layouts/partials/head.blade.php'));

        foreach ($existingCss as $filename) {
            $this->assertStringContainsString(
                "VersionedAsset::url('css/{$filename}')",
                $head,
                "head.blade.php non referenzia più css/{$filename}."
            );
        }

        // Sei riferimenti VersionedAsset::url('css/...') in totale: i 5
        // esistenti più il nuovo editorial-system.css, nessuno rimosso.
        $this->assertSame(6, substr_count($head, "VersionedAsset::url('css/"));

        $this->assertStringContainsString("VersionedAsset::url('css/editorial-system.css')", $head);

        // L'ordine conta (Missione 03): editorial-system.css deve comparire
        // dopo tutti gli altri cinque, mai prima.
        $editorialPosition = strpos($head, "VersionedAsset::url('css/editorial-system.css')");
        foreach ($existingCss as $filename) {
            $existingPosition = strpos($head, "VersionedAsset::url('css/{$filename}')");
            $this->assertLessThan(
                $editorialPosition,
                $existingPosition,
                "css/{$filename} deve essere caricato prima di editorial-system.css."
            );
        }
    }

    public function test_no_new_kairus_component_uses_a_class_selector_without_the_kairus_prefix(): void
    {
        $components = glob(resource_path('views/components/kairus/*.blade.php')) ?: [];

        $this->assertNotEmpty($components, 'Nessun componente trovato in resources/views/components/kairus/.');

        foreach ($components as $path) {
            $source = (string) file_get_contents($path);

            // Ogni letterale class="..." nel markup statico deve contenere
            // solo classi kairus- (o essere vuoto/puramente dinamico via
            // $attributes->class([...]) / @class([...]), già verificato a
            // parte più sotto).
            preg_match_all('/\bclass="([^"]*)"/', $source, $matches);

            foreach ($matches[1] as $classAttribute) {
                $tokens = array_filter(explode(' ', trim($classAttribute)));

                foreach ($tokens as $token) {
                    $this->assertStringStartsWith(
                        'kairus-',
                        $token,
                        basename($path)." usa la classe letterale \"{$token}\" senza prefisso kairus-."
                    );
                }
            }

            // Ogni voce letterale dentro ->class([...]) / @class([...]) deve
            // anch'essa essere prefissata kairus- — scoperto solo dentro
            // quelle due chiamate (mai su @props([...]) o altri array del
            // file, che elencano nomi di prop legittimamente non prefissati
            // come "href" o "title").
            preg_match_all('/(?:->class|@class)\(\[(.*?)\]\)/s', $source, $classCalls);

            foreach ($classCalls[1] as $classCallBody) {
                preg_match_all('/[\'"]([a-zA-Z0-9_-]+)[\'"]/', $classCallBody, $tokenMatches);

                foreach ($tokenMatches[1] as $token) {
                    $this->assertStringStartsWith(
                        'kairus-',
                        $token,
                        basename($path)." referenzia la classe \"{$token}\" senza prefisso kairus- in ->class()/@class()."
                    );
                }
            }
        }
    }

    public function test_editorial_css_defines_only_kairus_prefixed_classes_and_variables(): void
    {
        $css = (string) file_get_contents(public_path('css/editorial-system.css'));

        // Rimuove i commenti a blocco prima di ispezionare i selettori,
        // cosi' le menzioni testuali (es. "body, h1, a, .container, .btn")
        // nella spiegazione del contratto non vengono lette come selettori.
        $withoutComments = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/^\s*(--[a-zA-Z][a-zA-Z0-9_-]*)\s*:/m', $withoutComments, $declaredVars);
        foreach ($declaredVars[1] as $var) {
            $this->assertStringStartsWith('--kairus-', $var, "La variabile {$var} non è prefissata --kairus-.");
        }

        preg_match_all('/(^|[^a-zA-Z0-9_-])\.([a-zA-Z][a-zA-Z0-9_-]*)/m', $withoutComments, $classSelectors);
        foreach ($classSelectors[2] as $class) {
            $this->assertStringStartsWith('kairus-', $class, "Il selettore .{$class} non è prefissato .kairus-.");
        }

        foreach (['body', 'h1', 'h2', 'h3', '.container', '.btn'] as $forbidden) {
            $this->assertDoesNotMatchRegularExpression(
                '/(^|\})\s*'.preg_quote($forbidden, '/').'\s*[,{]/m',
                $withoutComments,
                "editorial-system.css definisce un selettore globale vietato: {$forbidden}."
            );
        }
    }
}
