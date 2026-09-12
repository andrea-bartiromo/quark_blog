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
 * Verifica solo la PRESENZA dei pattern attesi su righe ATTIVE (sola
 * lettura, mai scrittura): non interpreta la sintassi di mod_rewrite, non
 * garantisce che le regole producano il comportamento corretto una volta
 * caricate da Apache — quella garanzia resta con la verifica via curl già
 * documentata nel runbook, eseguibile solo contro l'host reale.
 */
class FrontControllerHtaccessAudit
{
    /**
     * Ogni voce: un'etichetta leggibile e un pattern la cui assenza in
     * public/.htaccess (dopo aver scartato i commenti — vedi
     * stripComments()) indica una direttiva critica persa. I pattern sono
     * sottostringhe letterali (non regex): bastano a rilevare una
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
        // Finding Codex (P1, PR #563): il tag generico <FilesMatch da solo
        // è soddisfatto anche dai due blocchi di cache statica più avanti
        // nel file — rimuovere QUESTO blocco (estensioni sensibili +
        // Deny from all) lascerebbe comunque il marker "presente" altrove.
        // L'espressione delle estensioni è invece specifica di questo
        // unico blocco.
        'Blocco file sensibili aggiuntivi (estensioni .sql/.bak/ecc.)' => '\.(env|log|sqlite|sh|bak|config|dist|fla|inc|ini|log|psd|sh|sql|swp|tar|gz)$',
        'Front controller (rewrite verso index.php)' => 'RewriteRule ^ index.php [L]',
        // Finding Codex (P2, PR #563): senza questa condizione, il rewrite
        // sopra farebbe passare da Laravel anche i file statici già
        // esistenti (CSS, JS, immagini) — la condizione !-d da sola non è
        // distintiva (ricorre identica anche nel blocco "Redirect
        // Trailing Slashes" più sopra nello stesso file), quindi non
        // aggiungerebbe protezione reale; !-f invece compare solo qui.
        'Front controller: i file statici esistenti non passano da index.php (!-f)' => 'RewriteCond %{REQUEST_FILENAME} !-f',
        'Header X-Content-Type-Options' => 'X-Content-Type-Options',
        'Header X-Frame-Options' => 'X-Frame-Options',
        // Finding Codex (P2, PR #563): il runbook elenca Referrer-Policy
        // insieme agli altri due header di sicurezza, ma mancava qui.
        'Header Referrer-Policy' => 'Referrer-Policy',
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

        $contents = self::stripComments((string) file_get_contents($path));

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

    /**
     * Finding Codex (P1, PR #563): una direttiva disattivata commentandola
     * (es. "# RewriteRule ^\.env$ - [F,L]") lascia comunque il testo del
     * marker nel file grezzo — Apache la ignora, ma il controllo per
     * sottostringa no. Scarta tutto ciò che segue un "#" su ogni riga
     * prima di cercare i marker: nessuna direttiva di questo file usa "#"
     * per altro (nessun valore contiene "#").
     */
    private static function stripComments(string $contents): string
    {
        return implode("\n", array_map(
            static fn (string $line): string => explode('#', $line, 2)[0],
            explode("\n", $contents)
        ));
    }
}
