<?php

namespace Tests\Unit\Deploy;

use App\Services\Deploy\PublicAssetDriftDetector;
use Tests\TestCase;

/**
 * Mission 03/04 — Selective Deploy Public Asset Synchronization / Drift
 * Detector.
 *
 * Simula le due document root reali (l'albero applicativo, sempre
 * public_path() di questo checkout di test, e una radice "servita" isolata
 * in una directory temporanea) per provare che il detector rileva
 * esattamente le quattro classi di divergenza richieste dalla missione:
 * ok, mismatch, missing_on_webroot, missing_on_app — mai mutando alcun
 * file su nessuna delle due radici.
 */
class PublicAssetDriftDetectorTest extends TestCase
{
    private const MARKER = 'kairus-test-served-root-';

    private ?string $servedRoot = null;

    protected function tearDown(): void
    {
        if ($this->servedRoot !== null && str_contains($this->servedRoot, self::MARKER)) {
            $this->deleteRecursively($this->servedRoot);
        }

        parent::tearDown();
    }

    private function makeServedRoot(): string
    {
        $this->servedRoot = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true);
        mkdir($this->servedRoot, 0775, true);
        config(['deploy.served_public_root' => $this->servedRoot]);

        return $this->servedRoot;
    }

    private function deleteRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function detector(): PublicAssetDriftDetector
    {
        return app(PublicAssetDriftDetector::class);
    }

    public function test_report_is_disabled_when_no_served_root_is_configured(): void
    {
        config(['deploy.served_public_root' => null]);

        $report = $this->detector()->report();

        $this->assertFalse($report['enabled']);
        $this->assertTrue($this->detector()->isClean());
    }

    public function test_report_is_disabled_when_served_root_resolves_to_the_same_physical_directory(): void
    {
        config(['deploy.served_public_root' => public_path()]);

        $report = $this->detector()->report();

        $this->assertFalse($report['enabled']);
    }

    public function test_a_file_present_and_identical_on_both_roots_is_reported_ok(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');

        try {
            $report = $this->detector()->report();

            $this->assertTrue($report['enabled']);
            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $entry['status']);
            $this->assertSame($entry['app_hash'], $entry['served_hash']);
            $this->assertTrue($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_different_content_on_the_two_roots_is_reported_as_a_mismatch(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:blue}');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_MISMATCH, $entry['status']);
            $this->assertNotSame($entry['app_hash'], $entry['served_hash']);
            $this->assertSame(1, $report['totals'][PublicAssetDriftDetector::STATUS_MISMATCH]);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_present_on_the_app_root_but_missing_on_the_served_root_is_flagged(): void
    {
        $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT, $entry['status']);
            $this->assertNotNull($entry['app_hash']);
            $this->assertNull($entry['served_hash']);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_present_on_the_served_root_but_missing_on_the_app_root_is_flagged(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');

        $report = $this->detector()->report();

        $entry = collect($report['entries'])->firstWhere('path', $probe);
        $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_APP, $entry['status']);
        $this->assertNull($entry['app_hash']);
        $this->assertNotNull($entry['served_hash']);
        $this->assertFalse($this->detector()->isClean());
    }

    public function test_a_directory_target_is_scanned_recursively_on_both_roots(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($probeDir.'/nested'), 0775, true);
        file_put_contents(public_path($probeDir.'/one.js'), 'console.log(1)');
        file_put_contents(public_path($probeDir.'/nested/two.js'), 'console.log(2)');
        mkdir($servedRoot.'/'.$probeDir, 0775, true);
        file_put_contents($servedRoot.'/'.$probeDir.'/one.js', 'console.log(1)');
        // nested/two.js deliberately absent on the served root.

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $byPath[$probeDir.'/one.js']['status']);
            $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT, $byPath[$probeDir.'/nested/two.js']['status']);
        } finally {
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    public function test_a_file_with_matching_content_but_an_unsafe_mode_on_the_served_root_is_flagged(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');
        chmod($servedRoot.'/'.$probe, 0600);

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_UNSAFE_MODE, $entry['status']);
            $this->assertSame($entry['app_hash'], $entry['served_hash'], 'Content still matches — only the mode is unsafe.');
            $this->assertSame(1, $report['totals'][PublicAssetDriftDetector::STATUS_UNSAFE_MODE]);
            $this->assertSame(0, $report['totals'][PublicAssetDriftDetector::STATUS_OK]);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_with_matching_content_but_an_unsafe_mode_on_the_app_root_is_flagged(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        chmod(public_path($probe), 0640);
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_UNSAFE_MODE, $entry['status']);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod(public_path($probe), 0644);
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_with_a_safe_but_more_permissive_mode_stays_ok(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');
        chmod($servedRoot.'/'.$probe, 0664);

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $entry['status']);
            $this->assertTrue($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_mismatch_is_reported_as_mismatch_even_when_its_mode_is_also_unsafe(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:blue}');
        chmod($servedRoot.'/'.$probe, 0600);

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(
                PublicAssetDriftDetector::STATUS_MISMATCH,
                $entry['status'],
                'A real content mismatch must never be masked by an also-unsafe mode.'
            );
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_scanned_directory_with_an_unsafe_mode_is_flagged_as_a_synthetic_entry(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($probeDir), 0775, true);
        file_put_contents(public_path($probeDir.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$probeDir, 0700, true);
        file_put_contents($servedRoot.'/'.$probeDir.'/one.js', 'console.log(1)');

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(PublicAssetDriftDetector::STATUS_UNSAFE_MODE, $byPath[$probeDir]['status']);
            $this->assertNull($byPath[$probeDir]['app_hash']);
            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $byPath[$probeDir.'/one.js']['status'], 'The file itself is still readable and content-identical — only the directory entry is flagged.');
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod($servedRoot.'/'.$probeDir, 0775);
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    /**
     * Revisione Codex su PR #535: la directory nominata direttamente in
     * asset_drift_scan_paths puo' avere un permesso sicuro mentre una sua
     * SOTTOdirectory (mai nominata esplicitamente in config) non lo ha. Il
     * processo che esegue la scansione (proprietario dei file, come un
     * deploy reale) puo' comunque attraversarla e trovare/confrontare
     * "one.js" al suo interno con hash identico su entrambe le radici —
     * senza questo test, quel file risulterebbe "ok" e il gate passerebbe
     * anche se Apache (altro utente) non puo' raggiungere la
     * sottodirectory. Il gate deve rilevarla come sottodirectory,
     * non solo come directory nominata direttamente.
     */
    public function test_a_nested_subdirectory_with_an_unsafe_mode_is_flagged_even_when_the_named_scan_directory_itself_is_safe(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        $nested = $probeDir.'/theme';
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($nested), 0775, true);
        file_put_contents(public_path($nested.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$nested, 0700, true);
        file_put_contents($servedRoot.'/'.$nested.'/one.js', 'console.log(1)');

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(
                PublicAssetDriftDetector::STATUS_UNSAFE_MODE,
                $byPath[$nested]['status'],
                'A nested directory with an unsafe mode must be flagged even though it is never named directly in asset_drift_scan_paths.'
            );
            $this->assertSame(
                PublicAssetDriftDetector::STATUS_OK,
                $byPath[$nested.'/one.js']['status'],
                'The file itself is still readable and content-identical to the scanning process — only the directory entry is flagged.'
            );
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod($servedRoot.'/'.$nested, 0775);
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    public function test_a_scanned_directory_with_a_safe_mode_is_not_reported_at_all(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($probeDir), 0775, true);
        file_put_contents(public_path($probeDir.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$probeDir, 0775, true);
        file_put_contents($servedRoot.'/'.$probeDir.'/one.js', 'console.log(1)');

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertArrayNotHasKey($probeDir, $byPath, 'A safely-permissioned directory must not itself appear as an entry.');
            $this->assertSame(0, $report['totals'][PublicAssetDriftDetector::STATUS_UNSAFE_MODE]);
            $this->assertTrue($this->detector()->isClean());
        } finally {
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    /**
     * Prompt 269 (programma Kairus 251-400), corretto dopo verifica
     * empirica reale (vedi nota sotto): i test esistenti sopra usano
     * sempre 0700 per simulare una directory "non sicura" — un permesso
     * che il PROCESSO DI SCANSIONE (proprietario dei file, come un deploy
     * reale o questo stesso test) puo' comunque attraversare, perche' 0700
     * concede rwx al proprietario.
     *
     * Il bit che conta per opendir()/RecursiveDirectoryIterator e' READ,
     * non x: una directory senza bit r (0000/0100/0200/0300) fa fallire
     * la COSTRUZIONE dell'iteratore con una UnexpectedValueException PHP —
     * verificato empiricamente, non solo per lettura di codice, in un
     * ambiente realmente non privilegiato creato in questa sandbox (utente
     * dedicato via `useradd`, mai root): con il codice precedente questo
     * script minimale CRASHA in modo identico a scanDirectory()/
     * unsafeDirectoryModes() prima del fix:
     *
     *   $ su nonroot -c 'php reproduce.php unfixed'
     *   UNCAUGHT EXCEPTION: UnexpectedValueException: ...
     *   Failed to open directory: Permission denied
     *   RESULT: CRASHED
     *
     * mentre con lo stesso codice ma il try/catch di questo fix, lo stesso
     * utente non privilegiato ottiene un risultato pulito:
     *
     *   $ su nonroot -c 'php reproduce.php fixed'
     *   CAUGHT: UnexpectedValueException: ...
     *   RESULT: NO_CRASH
     *
     * (root bypassa sempre i controlli DAC — lo stesso script eseguito da
     * root non crasha mai, a qualunque permesso, per questo la verifica
     * sopra e' stata rifatta con un utente reale, non dedotta dal solo
     * comportamento in questa sandbox). Questo test PHPUnit resta
     * comunque eseguito come root in questa sandbox (nessun modo di
     * lanciare l'intera suite Laravel come utente diverso senza
     * ri-permissionare l'intero albero del progetto): la sua asserzione
     * regge identica in entrambi i contesti (root: nessuna eccezione da
     * catturare, il flag arriva comunque dal controllo di modo; CI reale/
     * non-root: l'eccezione e' generata davvero e catturata dal fix), ma
     * la PROVA che il fix cattura davvero un'eccezione reale e' quella
     * riportata sopra, ottenuta fuori da PHPUnit con un utente non-root
     * vero. Verificare anche in CI (runner GitHub Actions, non root per
     * default) resta il modo piu' diretto per confermarlo nel contesto
     * reale di `deploy:asset-drift`.
     */
    public function test_a_directory_with_no_read_permission_does_not_crash_the_report_and_is_still_flagged_unsafe(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($probeDir), 0775, true);
        file_put_contents(public_path($probeDir.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$probeDir, 0775, true);
        file_put_contents($servedRoot.'/'.$probeDir.'/one.js', 'console.log(1)');
        // 0300 (-wx------): niente bit r, nemmeno per il proprietario —
        // il bit che fa davvero fallire opendir(), verificato
        // empiricamente (vedi commento sopra). 0600/0700 NON riproducono
        // questo crash: vedi il test successivo.
        chmod($servedRoot.'/'.$probeDir, 0300);

        try {
            $report = $this->detector()->report();

            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(
                PublicAssetDriftDetector::STATUS_UNSAFE_MODE,
                $byPath[$probeDir]['status'],
                'A directory with no read permission at all must still be flagged, whether or not the scanning process itself could open it.'
            );
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod($servedRoot.'/'.$probeDir, 0775);
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    /**
     * Prompt 269, seconda parte — CORRETTO dopo verifica reale non-root
     * (vedi nota della fase di correzione): 0600 (rw-------, il permesso
     * letteralmente descritto da "non attraversabile" — legge la
     * directory ma non puo' entrarci) e' un caso DIVERSO da quello sopra.
     * Ipotesi iniziale sbagliata: che il file interno diventasse del
     * tutto invisibile al report anche nello scenario realistico (un solo
     * lato compromesso, es. una `cp -a` da backup che tocca solo la
     * radice servita). Verificato con un utente non-root reale che NON e'
     * cosi': `is_file()` sul percorso completo del file fallisce chiuso
     * (richiede il bit x sulla directory padre per essere raggiunto), e
     * il file discovered dall'altro lato (leggibile) risulta quindi
     * `missing_on_webroot` — un segnale corretto e già attuabile, non un
     * buco. Questo test lo prova esplicitamente. Il caso di VERA
     * invisibilita' (nessuno dei due lati riesce a enumerare il file) si
     * verifica solo se ENTRAMBE le radici hanno la stessa sottodirectory
     * a 0600 contemporaneamente — scenario meno realistico, coperto dal
     * test successivo.
     */
    public function test_a_directory_unsafe_on_only_one_root_still_flags_its_content_as_missing_not_invisible(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        $nested = $probeDir.'/theme';
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($nested), 0775, true);
        file_put_contents(public_path($nested.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$nested, 0775, true);
        file_put_contents($servedRoot.'/'.$nested.'/one.js', 'console.log(1)');
        // Solo il lato servito: lo scenario realistico di una `cp -a` da
        // backup che preserva un permesso troppo restrittivo su una sola
        // radice.
        chmod($servedRoot.'/'.$nested, 0600);

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(
                PublicAssetDriftDetector::STATUS_UNSAFE_MODE,
                $byPath[$nested]['status'],
                'The directory entry itself, discovered from its still-traversable parent, must be flagged.'
            );

            // Verificato con un utente non-root reale (vedi nota sopra):
            // il file resta scoperto via il lato leggibile (app) e
            // risulta missing_on_webroot, MAI silenziosamente assente dal
            // report — anche se in questa sandbox, eseguita come root, il
            // lato servito resta comunque leggibile (bypass DAC) e lo
            // status osservabile qui e' percio' OK, non missing: la
            // differenza e' attesa e non contraddice la prova non-root.
            if (function_exists('posix_getuid') && posix_getuid() === 0) {
                $this->assertArrayHasKey($nested.'/one.js', $byPath);

                return;
            }

            $this->assertSame(
                PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT,
                $byPath[$nested.'/one.js']['status'],
                'Verified with a real non-root user: is_file() on the served side fails closed (needs +x on the parent to be reached at all), so the file discovered via the still-readable app side is correctly reported missing_on_webroot — never silently dropped from the report.'
            );
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod($servedRoot.'/'.$nested, 0775);
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    /**
     * Prompt 269, terza parte: la vera invisibilita' di contenuto — nessun
     * lato riesce a enumerare il file, quindi non compare nel report con
     * NESSUNO status, nemmeno "missing" — si verifica solo quando
     * ENTRAMBE le radici hanno la stessa sottodirectory a 0600
     * contemporaneamente. Verificato con un utente non-root reale:
     *
     *   DEBUG (entrambi i lati a 0600): {"status":"NOT_FOUND"}
     *
     * Non risolvibile dal try/catch di questo fix (non lancia mai nulla
     * da catturare): limite strutturale accettato, non un bug. Il segnale
     * che resta e' che la DIRECTORY stessa risulta comunque
     * STATUS_UNSAFE_MODE tramite il solo controllo di modo (non richiede
     * traversal) — un operatore sa quindi che va ispezionata a mano.
     */
    public function test_a_directory_unsafe_on_both_roots_makes_its_content_genuinely_invisible_but_the_directory_itself_stays_flagged(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        $nested = $probeDir.'/theme';
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($nested), 0775, true);
        file_put_contents(public_path($nested.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$nested, 0775, true);
        file_put_contents($servedRoot.'/'.$nested.'/one.js', 'console.log(1)');
        chmod($servedRoot.'/'.$nested, 0600);
        chmod(public_path($nested), 0600);

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(
                PublicAssetDriftDetector::STATUS_UNSAFE_MODE,
                $byPath[$nested]['status'],
                'The directory entry itself must still be flagged even when its content cannot be enumerated on either side — this comes from fileperms(), not from a traversal attempt, so it holds for root and non-root alike.'
            );
            $this->assertFalse($this->detector()->isClean());

            // Come sopra: in questa sandbox root bypassa il DAC, quindi
            // il file resta comunque visibile qui. La prova della vera
            // invisibilita' per un processo non privilegiato e' stata
            // ottenuta con un utente non-root reale fuori da questa
            // sandbox root (vedi il DEBUG log nel commento della funzione).
            if (function_exists('posix_getuid') && posix_getuid() === 0) {
                $this->assertArrayHasKey($nested.'/one.js', $byPath);

                return;
            }

            $this->assertArrayNotHasKey(
                $nested.'/one.js',
                $byPath,
                'Documenting the actual (accepted) limitation: when BOTH roots share the same non-traversable subdirectory, its content is invisible to per-file comparison — not silently marked ok, simply absent from the report, with the directory-level flag as the only remaining signal.'
            );
        } finally {
            chmod($servedRoot.'/'.$nested, 0775);
            chmod(public_path($nested), 0775);
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    public function test_a_file_empty_on_both_roots_is_flagged_as_empty_rather_than_ok(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), '');
        file_put_contents($servedRoot.'/'.$probe, '');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_EMPTY_FILE, $entry['status']);
            $this->assertSame(1, $report['totals'][PublicAssetDriftDetector::STATUS_EMPTY_FILE]);
            $this->assertSame(0, $report['totals'][PublicAssetDriftDetector::STATUS_OK]);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_empty_on_only_one_root_is_reported_as_a_mismatch_not_empty(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, '');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(
                PublicAssetDriftDetector::STATUS_MISMATCH,
                $entry['status'],
                'Different content (one side truncated to empty, the other not) is a mismatch, not the "empty on both sides" case.'
            );
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_the_report_never_writes_to_either_root(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'original');

        try {
            $beforeAppHash = hash_file('sha256', public_path($probe));
            $this->detector()->report();
            $afterAppHash = hash_file('sha256', public_path($probe));

            $this->assertSame($beforeAppHash, $afterAppHash);
            $this->assertFalse(is_file($servedRoot.'/'.$probe), 'report() must never create a file on the served root it does not already find there.');
        } finally {
            @unlink(public_path($probe));
        }
    }

    /**
     * Nome file univoco per test: prima di questa missione tutti i metodi
     * di questa classe scrivevano lo stesso "probe.css" letterale nel vero
     * public_path() applicativo (necessario per design — vedi la docblock
     * di classe — non e' isolabile in una directory temporanea). Un nome
     * univoco elimina l'unico rischio residuo reale: un errore fatale che
     * interrompe un test a meta' non puo' piu' lasciare un file il cui nome
     * un test COMPLETAMENTE DIVERSO in questo stesso file si aspetta assente.
     */
    private function probeFile(): string
    {
        return 'probe-'.uniqid('', true).'.css';
    }

    private function probeDir(): string
    {
        return 'probe-dir-'.uniqid('', true);
    }
}
