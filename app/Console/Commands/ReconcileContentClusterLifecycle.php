<?php

/**
 * Kairus — Percorsi: completamento automatico del lifecycle
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2026 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\ContentCluster;
use App\Services\ContentClusters\ContentClusterLifecycleReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileContentClusterLifecycle extends Command
{
    protected $signature = 'percorsi:reconcile-lifecycle';

    protected $description = 'Promuove automaticamente i Percorsi "in aggiornamento" a "concluso" quando ogni tappa configurata è entrata nel prefisso pubblico continuo';

    public function handle(ContentClusterLifecycleReconciler $reconciler): int
    {
        $updatingIds = ContentCluster::query()
            ->where('lifecycle_status', ContentCluster::LIFECYCLE_UPDATING)
            ->pluck('id');

        if ($updatingIds->isEmpty()) {
            $this->info('Nessun Percorso "in aggiornamento" da valutare.');

            return Command::SUCCESS;
        }

        $changed = 0;
        $unchanged = 0;
        $failed = 0;

        foreach ($updatingIds as $id) {
            try {
                $cluster = ContentCluster::query()->find($id);

                if (! $cluster) {
                    continue;
                }

                $result = $reconciler->reconcile($cluster);

                if (! $result->changed) {
                    $unchanged++;

                    continue;
                }

                $changed++;

                // user_id nullo: ActivityLog e la vista admin.activity già
                // mostrano "Sistema" per le azioni senza utente autenticato
                // (stesso pattern di PublishScheduledArticles).
                ActivityLog::record(
                    'Percorso concluso automaticamente (tutte le tappe configurate sono pubbliche)',
                    'content_cluster',
                    $cluster->id,
                    $cluster->name
                );

                Log::info('Percorso promosso automaticamente a concluso', [
                    'content_cluster_id' => $cluster->id,
                    'name' => $cluster->name,
                    'public_prefix_length' => $result->publicPrefixLength,
                    'total_membership_count' => $result->totalMembershipCount,
                ]);
            } catch (Throwable $exception) {
                $failed++;
                report($exception);

                Log::error('Errore durante la riconciliazione automatica del lifecycle di un Percorso', [
                    'content_cluster_id' => $id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Conclusi automaticamente: {$changed} — Invariati: {$unchanged} — Falliti: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
