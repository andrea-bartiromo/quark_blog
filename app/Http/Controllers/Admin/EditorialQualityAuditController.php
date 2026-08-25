<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\EditorialQuality\EditorialQualityAuditService;
use App\Services\EditorialQuality\EditorialQualityReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Missione 35 (secondo batch autonomo KAIRUS, Fase E — Editorial Quality &
 * Readiness): "article completeness audit".
 *
 * EditorialQualityAuditService e EditorialQualityChecker esistono già
 * (Editorial Quality Gate V1, batch precedente) e sono già raggiungibili
 * in due modi: il comando artisan `content:quality-audit` (solo CLI) e la
 * card per-articolo già presente nella pagina di modifica di ogni
 * articolo (App\Http\Controllers\Admin\ArticleController::edit(),
 * resources/views/partials/editorial-quality-gate.blade.php). Mancava una
 * vista SITEWIDE nell'admin: l'unico modo per vedere il quadro d'insieme
 * su tutti gli articoli era il comando CLI. Questo controller aggiunge
 * solo quella superficie, riusando integralmente
 * EditorialQualityAuditService::audit() — nessuna nuova regola di
 * dominio, nessun ricalcolo dei controlli già espressi da
 * EditorialQualityChecker.
 *
 * Missione 44 (Fase E — Editorial Quality & Readiness): "quality
 * drill-down". Il servizio calcola già "Problemi più frequenti"
 * (EditorialQualityAuditService::mostFrequentIssues(), con tanto di
 * `code` machine-readable), ma la vista lo mostrava solo come un elenco
 * statico — nessun modo di andare da "12 articoli senza fonte primaria"
 * ai 12 articoli effettivi senza scorrere a mano l'intera tabella. Il
 * filtro `problema` qui sotto riusa lo stesso `code` già calcolato, mai
 * una nuova classificazione: filtra semplicemente gli `entries` già
 * prodotti da `audit()` per quelli il cui report contiene quel codice tra
 * i propri `issues()`.
 */
class EditorialQualityAuditController extends Controller
{
    private const VALID_STATUSES = [
        Article::STATUS_DRAFT,
        Article::STATUS_REVIEW,
        Article::STATUS_SCHEDULED,
        Article::STATUS_PUBLISHED,
    ];

    public function __construct(
        private readonly EditorialQualityAuditService $auditService,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->input('stato');

        if (! is_string($status) || ! in_array($status, self::VALID_STATUSES, true)) {
            $status = null;
        }

        $summary = $this->auditService->audit(null, $status);

        // Stessa lista "ARTICOLI DA VERIFICARE" già prodotta dal comando
        // CLI (EditorialQualityAuditCommand::renderTextReport()): solo i
        // livelli non-READY, mai un elenco completo che nasconderebbe il
        // segnale in mezzo al rumore degli articoli già a posto.
        $flagged = collect($summary->entries)
            ->reject(fn (array $entry) => $entry['report']->level() === EditorialQualityReport::LEVEL_READY)
            ->values();

        // Un codice valido è solo uno che compare davvero tra i problemi
        // più frequenti di QUESTO audit (già scoped da $status) — mai un
        // filtro accettato alla cieca su una stringa arbitraria.
        $issueCode = $request->input('problema');
        $knownCodes = array_column($summary->mostFrequentIssues, 'code');

        if (! is_string($issueCode) || ! in_array($issueCode, $knownCodes, true)) {
            $issueCode = null;
        }

        $selectedIssueLabel = null;

        if ($issueCode !== null) {
            $selectedIssueLabel = collect($summary->mostFrequentIssues)->firstWhere('code', $issueCode)['label'];
            $flagged = $flagged
                ->filter(fn (array $entry) => collect($entry['report']->issues())->contains(fn ($issue) => $issue->code === $issueCode))
                ->values();
        }

        return view('admin.editorial-quality.index', [
            'summary' => $summary,
            'flagged' => $flagged,
            'selectedStatus' => $status,
            'statusOptions' => self::VALID_STATUSES,
            'selectedIssueCode' => $issueCode,
            'selectedIssueLabel' => $selectedIssueLabel,
        ]);
    }
}
