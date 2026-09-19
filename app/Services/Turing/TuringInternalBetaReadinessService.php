<?php

namespace App\Services\Turing;

use App\Models\TuringChapterSource;
use Illuminate\Support\Facades\Route;

/**
 * Cantiere 69 (programma "100 cantieri Kairus", dipende dai Cantieri 62,
 * 67, 68).
 *
 * Stesso pattern già stabilito da TrustPilotGateReadinessService (Cantiere
 * 42) e CategoryPublicationReadiness (Prompt 3/4): mostra SOLO lo stato di
 * sola lettura di un piccolo numero di condizioni verificabili dal vero
 * stato del codice/database — non le imposta, non le può impostare, nessun
 * metodo qui scrive alcunché. "Beta interna" qui significa: lo Speciale è
 * pronto per essere sottoposto a revisori/tester interni tramite
 * l'anteprima admin già esistente (Cantiere 63), NON per essere reso
 * pubblico — quella è una decisione separata (Cantiere 70, "Piano rilascio
 * Turing", che a sua volta dipende da questo cantiere).
 *
 * Le condizioni, nell'ordine in cui un revisore le incontrerebbe:
 * 1. Anteprima amministrativa disponibile (Cantiere 63) — verificata
 *    controllando che le rotte esistano davvero, non un booleano fisso.
 * 2. Nessun hub/capitolo strutturalmente vuoto anche senza contenuto CMS
 *    (Cantiere 57/62) — verificata controllando che la landing statica
 *    "In arrivo" esista davvero nel codebase, stessa evidenza già accettata
 *    per la riga 62 del tracking (covered-by-existing).
 * 3. Report di completezza disponibile per la revisione interna (Cantiere
 *    67, appena mergiato) — verificata controllando che la rotta esista.
 * 4. Fonti registrate per almeno un capitolo (Cantiere 61) — query diretta
 *    su TuringChapterSource. Nessuna riga viene mai creata automaticamente
 *    da questo programma (si legga il docblock del modello): oggi questa
 *    condizione è onestamente NON soddisfatta in ogni ambiente, non un
 *    difetto di questo cantiere.
 * 5. Owner editoriale che approva il passaggio alla revisione interna —
 *    nessun campo/meccanismo di assegnazione esiste ancora nel sistema:
 *    non "falso", "non ancora determinabile automaticamente" (stessa
 *    distinzione onesta già usata da TrustPilotGateReadinessService),
 *    richiede una decisione umana esplicita fuori da questo cantiere.
 */
class TuringInternalBetaReadinessService
{
    public const STATE_MET = 'met';

    public const STATE_NOT_MET = 'not_met';

    public const STATE_NOT_DETERMINABLE = 'not_determinable';

    /** @return array<int, array{key: string, label: string, state: string, detail: string}> */
    public function assess(): array
    {
        $previewAvailable = Route::has('admin.turing.preview') && Route::has('admin.turing.preview-chapter');
        $comingSoonExists = is_file(resource_path('views/turing/coming-soon.blade.php'));
        $completenessReportAvailable = Route::has('admin.turing.completeness-report');
        $sourcesCount = TuringChapterSource::query()->count();

        return [
            [
                'key' => 'anteprima_disponibile',
                'label' => 'Anteprima amministrativa disponibile',
                'state' => $previewAvailable ? self::STATE_MET : self::STATE_NOT_MET,
                'detail' => $previewAvailable
                    ? 'Admin\TuringController::previewHub()/previewChapter() (Cantiere 63): un revisore autenticato può vedere l\'hub e i 5 capitoli reali senza rendere pubblico lo Speciale.'
                    : 'Le rotte admin.turing.preview/admin.turing.preview-chapter non risultano registrate.',
            ],
            [
                'key' => 'hub_mai_vuoto',
                'label' => 'Nessun hub/capitolo strutturalmente vuoto',
                'state' => $comingSoonExists ? self::STATE_MET : self::STATE_NOT_MET,
                'detail' => $comingSoonExists
                    ? 'Landing statica "In arrivo" (turing.coming-soon) presente nel codebase: stessa tripla protezione già verificata alla riga 62 del tracking (hub CMS-driven con fallback, landing statica, capitoli hardcoded).'
                    : 'resources/views/turing/coming-soon.blade.php non trovato.',
            ],
            [
                'key' => 'report_completezza_disponibile',
                'label' => 'Report completezza disponibile per la revisione',
                'state' => $completenessReportAvailable ? self::STATE_MET : self::STATE_NOT_MET,
                'detail' => $completenessReportAvailable
                    ? 'GET /admin/turing/report-completezza (Cantiere 67): un revisore interno può consultare fonti/copertura concettuale/metriche di navigazione in un unico punto.'
                    : 'La rotta admin.turing.completeness-report non risulta registrata.',
            ],
            [
                'key' => 'fonti_registrate',
                'label' => 'Fonti registrate per almeno un capitolo',
                'state' => $sourcesCount > 0 ? self::STATE_MET : self::STATE_NOT_MET,
                'detail' => $sourcesCount > 0
                    ? "{$sourcesCount} fonte/i registrata/e in almeno un capitolo."
                    : 'Nessuna fonte ancora registrata in alcun capitolo (tabella turing_chapter_sources vuota per costruzione, Cantiere 61): condizione onestamente non soddisfatta.',
            ],
            [
                'key' => 'owner_revisione_assegnato',
                'label' => 'Owner editoriale che approva la revisione interna',
                'state' => self::STATE_NOT_DETERMINABLE,
                'detail' => 'Nessun campo o meccanismo di assegnazione owner esiste ancora nel sistema per la revisione beta interna: non verificabile automaticamente, richiede una decisione umana esplicita (fuori da questo cantiere).',
            ],
        ];
    }

    public function allConditionsMet(): bool
    {
        return collect($this->assess())->every(fn (array $condition) => $condition['state'] === self::STATE_MET);
    }
}
