<?php

namespace App\Services\PublicPages;

use App\Models\PublicHealthBaseline;
use Illuminate\Support\Carbon;

/**
 * Cantiere 35 (programma 100-cantieri Kairus), dipende dal Cantiere 30
 * (PublicHealthDashboardService). Stesso principio guida già stabilito
 * per AuditFindingStatusService: MAI ricalcolare qui i conteggi di un
 * dominio (quello resta compito esclusivo di
 * PublicHealthDashboardService) — questo servizio si limita a
 * persistere, una volta al mese, ciò che lo snapshot ha già calcolato, e
 * a confrontarlo con il mese precedente.
 *
 * "Denominatori separati": ogni confronto (trendFor()) usa SEMPRE e
 * SOLO checked_count/total_count/open_count dello STESSO dominio, mai
 * un totale o un tasso combinato tra domini diversi (universi diversi:
 * pagine per seo/wcag, link per links, media per media, ...). Non esiste
 * in questo servizio alcun metodo che sommi o mescoli conteggi tra
 * domini differenti.
 */
class PublicHealthBaselineService
{
    /**
     * Registra (o aggiorna, se già presente per lo stesso mese) una riga
     * di baseline per ciascuno dei sei domini reali. Idempotente: una
     * seconda esecuzione nello stesso mese aggiorna la riga esistente
     * invece di duplicarla — stesso principio di
     * AuditFindingStatusService::setStatus() (firstOrNew + save).
     *
     * @param  array<string, array<string, mixed>>  $snapshotDomains  lo snapshot['domains'] di PublicHealthDashboardService::snapshot()
     * @return array<string, PublicHealthBaseline>
     */
    public function recordMonth(array $snapshotDomains, ?string $period = null): array
    {
        $period ??= Carbon::now()->format('Y-m');
        $recordedAt = Carbon::now();
        $recorded = [];

        foreach (PublicHealthDashboardService::REAL_DOMAIN_KEYS as $key) {
            $domain = $snapshotDomains[$key];

            $baseline = PublicHealthBaseline::query()->firstOrNew([
                'domain' => $key,
                'period' => $period,
            ]);
            $baseline->finding_count = $domain['finding_count'];
            $baseline->open_count = $domain['open_count'];
            $baseline->dismissed_count = $domain['dismissed_count'];
            $baseline->high_open_count = $domain['high_open_count'];
            $baseline->checked_count = $domain['checked_count'] ?? null;
            $baseline->total_count = $domain['total_count'] ?? null;
            $baseline->recorded_at = $recordedAt;
            $baseline->save();

            $recorded[$key] = $baseline;
        }

        return $recorded;
    }

    /**
     * Confronta i conteggi CORRENTI di un dominio (passati da chi chiama,
     * tipicamente lo snapshot appena calcolato) con l'ultima baseline
     * mensile registrata per lo STESSO dominio in un periodo
     * strettamente precedente a quello corrente — mai il mese corrente
     * stesso (se recordMonth() è già stato eseguito oggi, non deve
     * confrontarsi con se stesso), mai un altro dominio.
     *
     * @param  array<string, mixed>  $currentDomainData  la voce di snapshot['domains'][$domain] corrente
     * @return array<string, mixed>|null null se non esiste ancora nessuna baseline precedente per questo dominio
     */
    public function trendFor(string $domain, array $currentDomainData, ?string $currentPeriod = null): ?array
    {
        $currentPeriod ??= Carbon::now()->format('Y-m');

        $previous = PublicHealthBaseline::query()
            ->where('domain', $domain)
            ->where('period', '<', $currentPeriod)
            ->orderByDesc('period')
            ->first();

        if ($previous === null) {
            return null;
        }

        return [
            'period' => $previous->period,
            'open_count_delta' => $currentDomainData['open_count'] - $previous->open_count,
            'high_open_count_delta' => $currentDomainData['high_open_count'] - $previous->high_open_count,
            'previous_open_count' => $previous->open_count,
            'previous_checked_count' => $previous->checked_count,
            'previous_total_count' => $previous->total_count,
        ];
    }
}
