<?php

/**
 * Kairus — Percorsi: osservabilità del lifecycle automatico
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2026 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Services\ContentClusters;

use App\Models\ActivityLog;
use App\Models\ContentCluster;
use Illuminate\Support\Carbon;

/**
 * Mission 09 — Percorsi Automation Observability. Surfaces what is
 * already queryable today (lifecycle_status counts on ContentCluster,
 * the most recent ActivityLog row written by
 * percorsi:reconcile-lifecycle) with no new migration/table.
 *
 * Deliberate scope limit: ReconcileContentClusterLifecycle only writes
 * an ActivityLog row when it actually promotes a Percorso — a no-op run
 * (nothing to promote) leaves no trace here. This summary therefore
 * answers "when did automation last change something," not "is the
 * scheduler currently running" — a healthy-but-idle scheduler and a
 * dead one look identical through this lens. Giving the scheduler a
 * genuine heartbeat signal would mean changing what the command records
 * on its no-op path, which is a real behavior decision left to a future,
 * explicitly-scoped mission rather than folded in here.
 */
class PercorsiAutomationObservability
{
    private const PROMOTION_ACTION = 'Percorso concluso automaticamente (tutte le tappe configurate sono pubbliche)';

    /**
     * @return array{
     *     updating: int,
     *     complete: int,
     *     last_promotion: ?array{cluster_name: ?string, at: Carbon},
     * }
     */
    public function summary(): array
    {
        $counts = ContentCluster::query()
            ->selectRaw('lifecycle_status, count(*) as aggregate')
            ->groupBy('lifecycle_status')
            ->pluck('aggregate', 'lifecycle_status');

        $lastPromotion = ActivityLog::query()
            ->where('subject_type', 'content_cluster')
            ->where('action', self::PROMOTION_ACTION)
            ->latest('created_at')
            ->first();

        return [
            'updating' => (int) ($counts[ContentCluster::LIFECYCLE_UPDATING] ?? 0),
            'complete' => (int) ($counts[ContentCluster::LIFECYCLE_COMPLETE] ?? 0),
            'last_promotion' => $lastPromotion ? [
                'cluster_name' => $lastPromotion->subject_title,
                'at' => $lastPromotion->created_at,
            ] : null,
        ];
    }
}
