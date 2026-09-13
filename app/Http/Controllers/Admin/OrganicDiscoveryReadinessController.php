<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService;
use App\Services\SearchConsole\SearchConsoleCoverageUrlEligibility;
use Illuminate\View\View;

/**
 * Superficie standalone e read-only (Cantiere 3, programma "Kairus
 * Organic Discovery"): compone gli audit già esistenti in uno stato
 * spiegabile per articolo — bloccato/da migliorare/pronto/misurato — mai
 * un punteggio opaco. L'audit gira solo quando un editor apre questa
 * pagina (stesso principio di ArticleDiscoveryAuditController).
 */
class OrganicDiscoveryReadinessController extends Controller
{
    private const DISPLAY_LIMIT = 100;

    private const STATE_ORDER = [
        OrganicDiscoveryReadinessService::STATE_BLOCKED => 0,
        OrganicDiscoveryReadinessService::STATE_NEEDS_WORK => 1,
        OrganicDiscoveryReadinessService::STATE_READY => 2,
        OrganicDiscoveryReadinessService::STATE_MEASURED => 3,
    ];

    public function __construct(
        private readonly OrganicDiscoveryReadinessService $readiness,
    ) {}

    public function index(): View
    {
        // Una sola esecuzione: riepilogo e tabella derivano dalla stessa
        // Collection, senza rilanciare l'audit per ogni card/stato.
        $rows = $this->readiness->auditAll();

        $counts = collect([
            OrganicDiscoveryReadinessService::STATE_BLOCKED,
            OrganicDiscoveryReadinessService::STATE_NEEDS_WORK,
            OrganicDiscoveryReadinessService::STATE_READY,
            OrganicDiscoveryReadinessService::STATE_MEASURED,
        ])->mapWithKeys(fn (string $state) => [$state => $rows->where('state', $state)->count()]);

        $ordered = $rows
            ->sortBy(fn (array $row) => [self::STATE_ORDER[$row['state']] ?? 99, -count($row['findings']), $row['title']])
            ->values();

        return view('admin.organic-discovery-readiness.index', [
            'counts' => $counts,
            'rows' => $ordered->take(self::DISPLAY_LIMIT),
            'total' => $ordered->count(),
            'truncated' => $ordered->count() > self::DISPLAY_LIMIT,
        ]);
    }

    public function show(Article $article, SearchConsoleCoverageUrlEligibility $eligibility): View
    {
        // forArticle() legge da un Article::published() già filtrato: un
        // articolo bozza o programmato non compare mai qui — fail-closed,
        // mai uno stato calcolato per un contenuto non pubblico.
        $report = $this->readiness->forArticle($article);

        abort_if($report === null, 404);

        // Cross-check più approfondito (canonical/robots/sitemap
        // dichiarati) — deliberatamente eseguito SOLO qui, per un singolo
        // articolo, mai in auditAll() (vedi il docblock della classe).
        $eligibilityAudit = $eligibility->audit(route('articolo', $article->slug));

        return view('admin.organic-discovery-readiness.show', [
            'article' => $article,
            'report' => $report,
            'eligibilityAudit' => $eligibilityAudit,
        ]);
    }
}
