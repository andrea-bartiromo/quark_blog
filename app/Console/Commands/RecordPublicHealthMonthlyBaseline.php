<?php

namespace App\Console\Commands;

use App\Services\PublicPages\PublicHealthBaselineService;
use App\Services\PublicPages\PublicHealthDashboardService;
use Illuminate\Console\Command;

/**
 * Cantiere 35 (programma 100-cantieri Kairus). Registra, una volta al
 * mese, lo snapshot corrente di PublicHealthDashboardService (Cantiere
 * 30) come baseline per ciascuno dei sei domini reali — permette al
 * dashboard admin di mostrare un confronto "vs mese scorso" invece del
 * solo valore istantaneo. Idempotente: eseguito più volte nello stesso
 * mese aggiorna la riga esistente (vedi PublicHealthBaselineService),
 * mai una riga duplicata.
 */
class RecordPublicHealthMonthlyBaseline extends Command
{
    protected $signature = 'public-health:record-monthly-baseline';

    protected $description = 'Registra la baseline mensile dei sei domini di Salute pubblica (SEO, redirect/canonical, 404, link, media, WCAG).';

    public function handle(PublicHealthDashboardService $dashboard, PublicHealthBaselineService $baselines): int
    {
        $snapshot = $dashboard->snapshot();
        $recorded = $baselines->recordMonth($snapshot['domains']);

        $this->info('Baseline mensile registrata per '.count($recorded).' domini ('.now()->format('Y-m').').');

        return self::SUCCESS;
    }
}
