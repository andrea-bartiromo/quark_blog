<?php

namespace App\Services\Deploy;

/**
 * Cantiere 16 (programma 100-cantieri Kairus, dipende dal Cantiere 15 —
 * vedi docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md). Quel runbook documenta
 * `public/.htaccess` come l'unica parte del livello Apache/cPanel che
 * QUESTO repository può verificare in CI, perché è l'unico file
 * git-tracked: `~/public_html/index.php` e `~/public_html/.htaccess`
 * (le copie realmente servite da Apache) vivono fuori da questo
 * repository e restano fuori dalla portata di qualunque test qui dentro
 * — nessuna modifica cambia quello.
 *
 * Questo servizio verifica che `public/.htaccess`, così come si trova in
 * QUESTA release, contenga ancora ogni direttiva critica descritta nel
 * runbook — cosicché una modifica futura che ne rimuova o alteri una per
 * errore (es. una riscrittura del file, un merge conflittuale risolto
 * male) venga segnalata qui, prima che qualcuno la copi manualmente in
 * `public_html` seguendo il runbook stesso.
 *
 * Verifica solo la PRESENZA dei pattern attesi (sola lettura, mai
 * scrittura): non interpreta la sintassi di mod_rewrite, non garantisce
 * che le regole producano il comportamento corretto una volta caricate
 * da Apache — quella garanzia resta con la verifica via curl già
 * documentata nel runbook, eseguibile solo contro l'host reale.
 */
class FrontControllerHtaccessAudit
{
    /**
     * Ogni voce: un'etichetta leggibile e un pattern la cui assenza in
     * public/.htaccess indica una direttiva critica persa. I pattern
     * sono sottostringhe letterali (non regex): bastano a rilevare una
     * rimozione o una riscrittura macroscopica senza accoppiarsi alla
     * formattazione esatta (indentazione, spazi) del file.
     *
     * @var array<string, string>
     */
    private const REQUIRED_MARKERS = [
        'Redirect verso host/protocollo canonico (kairus.it, https)' => 'https://kairus.it%{REQUEST_URI}',
        'Blocco accesso a .env' => 'RewriteRule ^\.env$ - [F,L]',
        'Blocco accesso a .git' => 'RewriteRule ^\.git - [F,L]',
        'Blocco accesso a storage/' => 'RewriteRule ^storage/ - [F,L]',
        'Blocco accesso a bootstrap/cache/' => 'RewriteRule ^bootstrap/cache/ - [F,L]',
        'Blocco file sensibili aggiuntivi (FilesMatch)' => '<FilesMatch',
        'Front controller (rewrite verso index.php)' => 'RewriteRule ^ index.php [L]',
        'Header X-Content-Type-Options' => 'X-Content-Type-Options',
        'Header X-Frame-Options' => 'X-Frame-Options',
    ];

    /**
     * @param  ?string  $path  Percorso da verificare — default
     *                         public_path('.htaccess') di questa release.
     *                         Parametrizzabile solo per i test: nessun
     *                         chiamante di produzione lo passa mai.
     * @return array{
     *     ok: bool,
     *     path: string,
     *     exists: bool,
     *     missing: list<string>
     * }
     */
    public function report(?string $path = null): array
    {
        $path ??= public_path('.htaccess');

        if (! is_file($path)) {
            return [
                'ok' => false,
                'path' => $path,
                'exists' => false,
                'missing' => array_keys(self::REQUIRED_MARKERS),
            ];
        }

        $contents = (string) file_get_contents($path);

        $missing = [];
        foreach (self::REQUIRED_MARKERS as $label => $marker) {
            if (! str_contains($contents, $marker)) {
                $missing[] = $label;
            }
        }

        return [
            'ok' => $missing === [],
            'path' => $path,
            'exists' => true,
            'missing' => $missing,
        ];
    }
}
