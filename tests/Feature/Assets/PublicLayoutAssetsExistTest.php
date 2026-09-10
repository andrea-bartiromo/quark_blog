<?php

namespace Tests\Feature\Assets;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Kairus Prompt 267: ogni asset CSS/JS locale referenziato via
 * VersionedAsset::url() da una vista Blade deve esistere davvero sotto
 * public/ nel repository — VersionedAsset::url() stessa non fallisce mai
 * su un file mancante (ricade su "?v=1", vedi VersionedAssetTest), quindi
 * un riferimento morto non produce un errore applicativo: produce un 404
 * silenzioso sul sito reale, rilevabile solo confrontando ogni
 * riferimento con il filesystem del repository, come fa questo test.
 *
 * Scansiona TUTTE le viste Blade (non solo i layout principali) per non
 * dipendere da un elenco mantenuto a mano che si disallineerebbe nel
 * tempo.
 *
 * Fase di correzione (programma Kairus, punto 4): estrazione e filtro
 * sono isolati in due metodi proprio per poterli testare contro
 * frammenti costruiti ad hoc, senza toccare le viste reali — i test sotto
 * verificano esplicitamente che il controllo non produca falsi positivi
 * su classi di riferimento che NON sono asset locali statici: URL
 * dinamici (variabile PHP), URL remoti passati come stringa letterale,
 * chiamate route()/@vite() (funzione/direttiva diversa, mai
 * intercettata), e contenuto puramente testuale/di esempio che nomina
 * "VersionedAsset::url(...)" senza essere codice PHP eseguito (limite
 * noto, documentato esplicitamente invece di lasciarlo implicito).
 */
class PublicLayoutAssetsExistTest extends TestCase
{
    /**
     * @return list<string> path grezzi, cosi' come compaiono fra apici
     *                      singoli nella chiamata — nessun filtro qui.
     */
    private static function extractVersionedAssetReferences(string $contents): array
    {
        $references = [];

        // Deliberatamente solo stringa letterale fra apici singoli, senza
        // interpolazione: e' l'unico caso in cui il path e' scritto in
        // modo statico nel sorgente. Qualunque altra forma (variabile,
        // doppi apici con interpolazione, concatenazione) non produce un
        // match e viene percio' ignorata piuttosto che valutata in modo
        // scorretto.
        if (preg_match_all("/VersionedAsset::url\('([^']+)'\)/", $contents, $matches)) {
            foreach ($matches[1] as $relativePath) {
                $references[] = $relativePath;
            }
        }

        return $references;
    }

    /**
     * true solo per un path locale relativo verificabile contro
     * public_path() — false per qualunque cosa che non lo sia (URL
     * assoluto/remoto, protocol-relative, data URI), cosi' un uso
     * improprio di VersionedAsset::url() con un URL remoto (la funzione è
     * pensata solo per asset locali) non produce un falso "asset
     * mancante": semplicemente non e' un percorso di cui questo test ha
     * senso verificare l'esistenza sul filesystem del repository.
     */
    private static function isCheckableLocalPath(string $path): bool
    {
        return ! preg_match('#^(https?:)?//|^data:#i', $path);
    }

    public function test_every_versioned_asset_referenced_by_a_blade_view_exists_under_public(): void
    {
        $references = [];

        $finder = (new Finder)
            ->files()
            ->in(resource_path('views'))
            ->name('*.blade.php');

        foreach ($finder as $file) {
            foreach (self::extractVersionedAssetReferences($file->getContents()) as $relativePath) {
                if (! self::isCheckableLocalPath($relativePath)) {
                    continue;
                }

                $references[$relativePath][] = $file->getRelativePathname();
            }
        }

        $this->assertNotEmpty($references, 'Nessun riferimento VersionedAsset::url() trovato: il test non starebbe verificando nulla.');

        $missing = [];

        foreach ($references as $relativePath => $viewsUsingIt) {
            if (! is_file(public_path($relativePath))) {
                $missing[] = "$relativePath (referenziato da: ".implode(', ', array_unique($viewsUsingIt)).')';
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Asset referenziati via VersionedAsset::url() ma assenti da public/:\n".implode("\n", $missing)
        );
    }

    public function test_a_dynamic_url_built_from_a_php_variable_is_not_extracted_as_a_static_reference(): void
    {
        $references = self::extractVersionedAssetReferences(
            <<<'BLADE'
            <link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url($theme.'.css') }}">
            <link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url($dynamicPath) }}">
            BLADE
        );

        $this->assertSame([], $references, 'A call whose argument is a PHP variable (no literal single-quoted string) must never be treated as a checkable static path.');
    }

    public function test_a_double_quoted_string_with_interpolation_is_not_extracted_as_a_static_reference(): void
    {
        $references = self::extractVersionedAssetReferences(
            '<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url("css/{$theme}.css") }}">'
        );

        $this->assertSame([], $references, 'A double-quoted argument with interpolation is not a static path and must not be extracted (the pattern only matches single-quoted literals).');
    }

    public function test_a_remote_url_passed_as_a_literal_string_is_never_checked_against_public_path(): void
    {
        $remote = 'https://cdn.example.com/vendor-that-does-not-exist-locally.css';
        $references = self::extractVersionedAssetReferences(
            "<link rel=\"stylesheet\" href=\"{{ \\App\\Support\\VersionedAsset::url('$remote') }}\">"
        );

        // La stringa letterale VIENE estratta (e' comunque fra apici
        // singoli) — il confine corretto non e' "non intercettarla mai",
        // ma "non trattarla come un path locale da verificare". E'
        // isCheckableLocalPath(), usato dal test reale sopra per filtrare
        // prima del controllo su public_path(), a garantirlo.
        $this->assertContains($remote, $references, 'The literal string is still mechanically extracted...');
        $this->assertFalse(
            self::isCheckableLocalPath($remote),
            '...but must be recognised as not a checkable local path, so the real test above skips it entirely instead of reporting a false "missing asset".'
        );
    }

    public function test_a_protocol_relative_url_is_never_checked_against_public_path(): void
    {
        $this->assertFalse(self::isCheckableLocalPath('//cdn.example.com/vendor.css'));
    }

    public function test_a_data_uri_is_never_checked_against_public_path(): void
    {
        $this->assertFalse(self::isCheckableLocalPath('data:text/css;base64,Ym9keXt9'));
    }

    public function test_an_ordinary_relative_asset_path_stays_checkable(): void
    {
        $this->assertTrue(self::isCheckableLocalPath('css/style.css'));
    }

    public function test_a_laravel_route_helper_call_is_never_matched_by_the_versioned_asset_pattern(): void
    {
        $references = self::extractVersionedAssetReferences(
            <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('articolo.show', ['slug' => 'esempio-non-esistente']) }}">Articolo</a>
            BLADE
        );

        $this->assertSame([], $references, 'route() calls must never be matched: they are a completely different helper, resolved by the router, not by public_path().');
    }

    public function test_a_vite_directive_is_never_matched_by_the_versioned_asset_pattern(): void
    {
        $references = self::extractVersionedAssetReferences(
            "@vite(['resources/css/app-non-existent.css', 'resources/js/app-non-existent.js'])"
        );

        $this->assertSame([], $references, '@vite() ships/resolves its own manifest-based assets at build time — it is not VersionedAsset::url() and must never be matched by this pattern.');
    }

    public function test_plain_prose_or_a_code_sample_merely_mentioning_the_function_name_is_still_extracted_known_limitation(): void
    {
        // Una pagina di documentazione/aiuto in-app che MOSTRA un esempio
        // di codice come testo (non lo esegue) puo' legittimamente
        // contenere la stringa esatta "VersionedAsset::url('...')" con un
        // path di fantasia, mai inteso come un vero asset da verificare.
        // Limite noto e accettato, fissato qui esplicitamente invece di
        // lasciarlo implicito: l'estrazione lavora sul contenuto grezzo
        // del file, non su un parser PHP/Blade, quindi NON puo'
        // distinguere codice reale da testo che lo cita — un simile path
        // di esempio VERREBBE comunque estratto e segnalato come mancante
        // dal test reale. Chi scrive una pagina di aiuto deve percio'
        // evitare di citare la sintassi esatta della chiamata con un path
        // di fantasia, oppure usare un path che esista davvero.
        $references = self::extractVersionedAssetReferences(
            "<p>Esempio: <code>VersionedAsset::url('css/esempio-di-fantasia-mai-esistito.css')</code></p>"
        );

        $this->assertContains(
            'css/esempio-di-fantasia-mai-esistito.css',
            $references,
            'Known limitation, documented on purpose: plain text mentioning the exact function call syntax IS extracted like real code, because this checker works on raw file content, not a PHP/Blade parser.'
        );
    }
}
