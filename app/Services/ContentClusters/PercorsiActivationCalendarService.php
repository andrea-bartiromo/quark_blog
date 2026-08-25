<?php

/**
 * Kairus — Percorsi: panoramica calendario di attivazione
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2026 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Services\ContentClusters;

use App\Models\ContentCluster;
use Illuminate\Support\Carbon;

/**
 * Missione 12 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): panoramica admin delle date di attivazione schedulata dei
 * Percorsi. Nessuna nuova colonna/migrazione: riusa esattamente la stessa
 * policy già definita e documentata da
 * ContentCluster::scopePubliclyVisible()/scopeInactive() (Percorsi
 * Scheduling V1) — questa classe aggiunge solo un punto di aggregazione
 * per l'intera tabella, indipendente dalla paginazione dell'indice admin,
 * seguendo lo stesso pattern già in uso da PercorsiAutomationObservability.
 *
 * Persistenza sempre in UTC (publish_at, colonna datetime nativa); la
 * conversione a Europe/Rome avviene solo qui, all'ultimo momento prima
 * della presentazione, tramite ContentCluster::publishAtForEditors() —
 * mai un nuovo punto di conversione di fuso orario.
 */
class PercorsiActivationCalendarService
{
    /**
     * @return array{
     *     active_now: int,
     *     scheduled: int,
     *     inactive: int,
     *     next_activation: ?array{cluster_name: string, slug: string, at: Carbon},
     * }
     */
    public function summary(): array
    {
        $activeNow = (clone ContentCluster::query())->publiclyVisible()->count();
        $inactive = (clone ContentCluster::query())->inactive()->count();

        $scheduledQuery = ContentCluster::query()
            ->where('is_active', true)
            ->where('publish_at', '>', now());

        $scheduled = (clone $scheduledQuery)->count();

        $next = (clone $scheduledQuery)
            ->orderBy('publish_at')
            ->first(['id', 'name', 'slug', 'publish_at']);

        return [
            'active_now' => $activeNow,
            'scheduled' => $scheduled,
            'inactive' => $inactive,
            'next_activation' => $next ? [
                'cluster_name' => $next->name,
                'slug' => $next->slug,
                'at' => $next->publishAtForEditors(),
            ] : null,
        ];
    }
}
