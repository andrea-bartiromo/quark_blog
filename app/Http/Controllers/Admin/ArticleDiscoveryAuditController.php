<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Discovery\ArticleDiscoveryAuditService;
use Illuminate\View\View;

/**
 * Superficie standalone e read-only per l'audit discovery. Deliberatamente
 * non integrata in Operazioni editoriali: l'audit scansiona corpus e link
 * interni, quindi va eseguito solo quando un editor apre questa pagina.
 */
class ArticleDiscoveryAuditController extends Controller
{
    private const DISPLAY_LIMIT = 100;

    public function __construct(
        private readonly ArticleDiscoveryAuditService $auditService,
    ) {}

    public function index(): View
    {
        // Una sola esecuzione: riepilogo e tabella derivano dalla stessa
        // Collection, senza rilanciare l'audit per ogni classe/card.
        $rows = $this->auditService->audit();
        $counts = collect(['ZERO_PATHS', 'ONE_PATH', 'MULTIPLE_PATHS'])
            ->mapWithKeys(fn (string $class) => [$class => $rows->where('discovery_class', $class)->count()]);
        $ordered = $rows
            ->sortBy(fn (array $row) => [$row['discovery_path_count'], -count($row['risks']), $row['title'], $row['article_id']])
            ->values();

        return view('admin.article-discovery-audit.index', [
            'counts' => $counts,
            'rows' => $ordered->take(self::DISPLAY_LIMIT),
            'total' => $ordered->count(),
            'truncated' => $ordered->count() > self::DISPLAY_LIMIT,
        ]);
    }
}
