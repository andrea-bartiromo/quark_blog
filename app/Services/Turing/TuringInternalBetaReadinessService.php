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
 * 2. I template REALMENTE renderizzati dall'anteprima esistono (Cantiere
 *    57/62) — Codex, PR #633, P2: la prima versione controllava
 *    l'esistenza della landing statica "In arrivo" (turing.coming-soon),
 *    ma previewHub()/previewChapter() non la renderizzano mai — bypassano
 *    il gate e mostrano sempre turing.index + turing.$chapter reali
 *    (Cantiere 63). Verificare la landing pubblica non dice nulla su cosa
 *    un revisore in anteprima vedrebbe davvero. Corretto controllando
 *    l'esistenza dei file realmente usati da quelle due rotte.
 * 3. Report di completezza disponibile per la revisione interna (Cantiere
 *    67, appena mergiato) — verificata controllando che la rotta esista.
 * 4. Fonti registrate per almeno un capitolo REALE (Cantiere 61) — Codex,
 *    PR #633, P2: la colonna `chapter` non ha alcun vincolo FK/enum a
 *    livello DB (si legga il docblock del modello); la validazione `in:`
 *    in TuringChapterSourceController::store() impedisce la creazione di
 *    righe con un capitolo non valido, ma non protegge da un valore reso
 *    stale da una futura modifica della lista canonica. Corretto
 *    filtrando la query sugli stessi 5 capitoli reali già usati da
 *    TuringChapterSourceController e dal report di completezza (Cantiere
 *    67), mai una lista duplicata. Nessuna riga viene mai creata
 *    automaticamente da questo programma (si legga il docblock del
 *    modello): oggi questa condizione è onestamente NON soddisfatta in
 *    ogni ambiente, non un difetto di questo cantiere.
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
        $previewTemplatesExist = $this->previewTemplatesExist();
        $completenessReportAvailable = Route::has('admin.turing.completeness-report');
        $sourcesCount = TuringChapterSource::query()->whereIn('chapter', $this->realChapters())->count();

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
                'state' => $previewTemplatesExist ? self::STATE_MET : self::STATE_NOT_MET,
                'detail' => $previewTemplatesExist
                    ? 'I template realmente renderizzati dall\'anteprima (turing/index.blade.php + un template per ciascuno dei 5 capitoli reali) esistono tutti nel codebase.'
                    : 'Almeno uno dei template renderizzati dall\'anteprima (turing/index.blade.php o uno dei 5 capitoli reali) non è stato trovato.',
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
                    ? "{$sourcesCount} fonte/i registrata/e in almeno un capitolo reale."
                    : 'Nessuna fonte ancora registrata in alcun capitolo reale (tabella turing_chapter_sources vuota per costruzione, Cantiere 61): condizione onestamente non soddisfatta.',
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

    private function previewTemplatesExist(): bool
    {
        if (! is_file(resource_path('views/turing/index.blade.php'))) {
            return false;
        }

        foreach ($this->realChapters() as $chapter) {
            if (! is_file(resource_path("views/turing/{$chapter}.blade.php"))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Stessa fonte di verità già usata da Admin\TuringController,
     * Admin\TuringChapterSourceController e TuringCompletenessReportService
     * (mai una seconda lista duplicata dei 5 capitoli reali).
     *
     * @return list<string>
     */
    private function realChapters(): array
    {
        return array_values(array_filter(
            TuringNavigationMetricsService::CHAPTERS,
            fn (string $chapter) => $chapter !== 'hub'
        ));
    }
}
