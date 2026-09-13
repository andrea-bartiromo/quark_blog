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
     * invece di duplicarla.
     *
     * Codex (PR #582, P2): firstOrNew()+save() per riga non è atomico —
     * lo scheduler (routes/console.php) protegge solo le run schedulate
     * tra loro (withoutOverlapping()), non un'invocazione manuale diretta
     * del comando che si sovrapponga a una schedulata. Due processi che
     * trovano entrambi "non esiste ancora" per lo stesso domain/period
     * tenterebbero entrambi un INSERT, e il secondo violerebbe il
     * vincolo di unicità. upsert() (stesso pattern già in uso in
     * ContentClusterSuggestionService) è un'unica query atomica lato
     * database, mai una race a livello applicativo.
     *
     * @param  array<string, array<string, mixed>>  $snapshotDomains  lo snapshot['domains'] di PublicHealthDashboardService::snapshot()
     * @return array<string, PublicHealthBaseline>
     */
    public function recordMonth(array $snapshotDomains, ?string $period = null): array
    {
        $period ??= Carbon::now()->format('Y-m');
        $recordedAt = Carbon::now();

        $rows = collect(PublicHealthDashboardService::REAL_DOMAIN_KEYS)
            ->map(fn (string $key) => [
                'domain' => $key,
                'period' => $period,
                'finding_count' => $snapshotDomains[$key]['finding_count'],
                'open_count' => $snapshotDomains[$key]['open_count'],
                'dismissed_count' => $snapshotDomains[$key]['dismissed_count'],
                'high_open_count' => $snapshotDomains[$key]['high_open_count'],
                'checked_count' => $snapshotDomains[$key]['checked_count'] ?? null,
                'total_count' => $snapshotDomains[$key]['total_count'] ?? null,
                'recorded_at' => $recordedAt,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ])
            ->all();

        PublicHealthBaseline::query()->upsert(
            $rows,
            ['domain', 'period'],
            ['finding_count', 'open_count', 'dismissed_count', 'high_open_count', 'checked_count', 'total_count', 'recorded_at', 'updated_at'],
        );

        return PublicHealthBaseline::query()
            ->where('period', $period)
            ->whereIn('domain', PublicHealthDashboardService::REAL_DOMAIN_KEYS)
            ->get()
            ->keyBy('domain')
            ->all();
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
