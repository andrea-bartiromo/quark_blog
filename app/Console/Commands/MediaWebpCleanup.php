<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaWebpCleanupService;
use Illuminate\Console\Command;

/**
 * Elenca ESCLUSIVAMENTE i candidati alla futura rimozione degli originali
 * JPG/PNG gia' migrati a WebP da media:convert-webp — sola lettura, nessun
 * flag di esecuzione in questa versione (FASE 12 della missione: progettato,
 * mai eseguito automaticamente). Vedi MediaWebpCleanupService per i criteri
 * esatti e docs/EDITORIAL_MEDIA_WEBP.md §6 per la procedura completa.
 */
class MediaWebpCleanup extends Command
{
    protected $signature = 'media:webp-cleanup
        {--dry-run : Accettato per compatibilita\' con la sintassi indicata dalla missione — questo comando e\' SEMPRE di sola lettura, non esiste una modalita\' di esecuzione}
        {--json : Restituisce il risultato in formato JSON invece del report testuale}
        {--path= : Limita la scansione a una sottodirectory di public/assets/img (es. categories)}
        {--min-age-days= : Sovrascrive la soglia minima di giorni dalla migrazione (default: config(\'media.webp_cleanup_min_age_days\'))}';

    protected $description = 'Elenca i soli candidati alla futura rimozione degli originali gia\' migrati a WebP (sola lettura, nessuna cancellazione)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        FASE 12 della missione WebP: PROGETTA ma non esegue mai una
        cancellazione. Un file compare nell'elenco "candidati" solo se
        TUTTE queste condizioni sono vere:

        1. e' un JPG/PNG sotto public/assets/img;
        2. esiste un Media il cui disk_name e' gia' la sua controparte
           .webp (e' stato davvero migrato da media:convert-webp);
        3. quel file .webp esiste realmente su disco ed e' DAVVERO
           un'immagine WebP valida, non solo un'immagine decodificabile
           di qualunque formato (mai un candidato se il sostituto e'
           mancante, corrotto, o in un formato diverso da WebP);
        4. se MEDIA_PUBLIC_ROOT e' configurato, il WebP esiste anche
           nella radice pubblica secondaria (potrebbe essere quella
           realmente servita al pubblico);
        5. nessun Media punta ancora al file originale stesso;
        6. non e' nella lista protetta (config/media.php);
        7. non e' sotto turing/ (nessun record Media da cui dedurre l'uso);
        8. nessun campo strutturato (copertina, banner, foto profilo,
           immagine categoria, pagina speciale) menziona ancora il nome
           dell'originale, anche senza un Media proprietario (un
           confronto per solo nome file tra originale e webp non prova
           che il secondo sia stato generato dal primo);
        9. nessuna menzione in testo libero (article.body, ad.html_code);
        10. nessuna menzione letterale nel codice sorgente versionato
            (resources/views, resources/css, resources/js, public/css,
            public/js);
        11. e' trascorso il periodo di osservazione minimo dalla
            migrazione (--min-age-days, default configurabile).

        La conferma di un BACKUP LOCALE resta sempre un passo umano
        esplicito: questo comando non puo' verificarlo (nessuna
        integrazione di backup esiste nel progetto) e lo ricorda sempre
        nell'output, non lo assume mai.

        NON ESISTE un flag per cancellare. Questa versione del comando
        e' volutamente sola-lettura al 100%: la rimozione effettiva degli
        originali e' una decisione editoriale distinta, futura, mai presa
        automaticamente da questo strumento.

        Opzioni
        -------
        --json            Output JSON invece del report testuale
        --path=           Limita a una sottocartella di assets/img
        --min-age-days=   Sovrascrive la soglia di osservazione minima

        Procedura consigliata (quando si decidera' di implementare la
        rimozione effettiva)
        --------------------------------------------------------------
        1. Eseguire questo comando e rivedere umanamente ogni candidato.
        2. Confermare un backup locale reale di public/assets/img.
        3. Implementare deliberatamente un comando/flag di cancellazione
           separato, con lo stesso livello di test e revisione delle
           altre fasi di questa missione — mai come estensione rapida di
           questo comando di sola lettura.
        HELP;

    public function handle(MediaWebpCleanupService $service): int
    {
        $report = $service->scan([
            'path' => $this->option('path'),
            'minAgeDays' => $this->option('min-age-days') !== null ? (int) $this->option('min-age-days') : null,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderTextReport($report);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderTextReport(array $report): void
    {
        $human = fn (int $bytes) => Media::humanFileSize($bytes);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>CLEANUP WEBP — CANDIDATI ALLA RIMOZIONE DEGLI ORIGINALI</>');
        $this->line('(sola lettura — nessun file viene mai cancellato da questo comando)');
        $this->newLine();

        $this->line('Originali JPG/PNG analizzati: '.$report['scanned_count']);
        $this->line('Periodo di osservazione minimo: '.$report['min_age_days'].' giorni');

        $candidates = $report['candidates'];
        $this->newLine();
        $this->line('<fg=green;options=bold>Candidati: '.$candidates['count'].'</> (spazio potenzialmente recuperabile: '.$human($candidates['total_bytes']).')');

        foreach ($candidates['files'] as $file) {
            $this->line('  '.$human($file['size_bytes']).' — '.$file['relative_path'].' (sostituito da '.$file['webp_disk_name'].', migrato '.$file['age_days'].' giorni fa)');
        }

        $this->newLine();
        $this->line('<fg=yellow;options=bold>Esclusi</>');
        $labels = [
            'not_migrated' => 'Non ancora migrati (nessun Media punta al .webp)',
            'still_referenced_by_media' => 'Un Media punta ancora all\'originale stesso',
            'protected' => 'Riferimenti statici protetti',
            'turing_unmanaged' => 'Turing (nessun record Media)',
            'webp_missing_or_invalid' => 'WebP sostitutivo mancante o non valido (richiede indagine)',
            'webp_missing_in_secondary_root' => 'WebP assente nella radice pubblica secondaria (MEDIA_PUBLIC_ROOT)',
            'structured_reference_without_media' => 'Riferimento strutturato residuo senza un Media proprietario',
            'free_text_reference' => 'Menzionati in testo libero (body articolo / html annuncio)',
            'static_source_reference' => 'Menzionati nel codice sorgente (Blade/CSS/JS)',
            'observation_period_not_elapsed' => 'Periodo di osservazione non ancora trascorso',
        ];
        foreach ($report['excluded'] as $bucket => $data) {
            $this->line(sprintf('  %-55s %5d file', $labels[$bucket] ?? $bucket, $data['count']));
        }

        if ($candidates['count'] > 0) {
            $this->newLine();
            $this->warn('Promemoria: la conferma di un backup locale reale resta un passo umano, mai verificato automaticamente da questo comando. Nessuna cancellazione va eseguita senza di essa.');
        }

        $this->newLine();
    }
}
