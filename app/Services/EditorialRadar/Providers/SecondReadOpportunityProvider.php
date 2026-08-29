<?php

namespace App\Services\EditorialRadar\Providers;

use App\Services\ContinuationAnalyticsService;
use Illuminate\Support\Collection;
use Throwable;

class SecondReadOpportunityProvider
{
    private const LIMIT = 50;
    private const MIN_IMPRESSIONS = 20;
    private const WEAK_RATE = 0.10;
    private const STRONG_RATE = 0.25;
    private const MIN_STRONG_READS = 5;

    public function __construct(private readonly ContinuationAnalyticsService $analytics) {}

    /** @return Collection<int, array<string, mixed>> */
    public function opportunities(): Collection
    {
        try {
            return $this->analytics->articleBreakdown(
                now()->subDays(30),
                now(),
                self::LIMIT,
                fn (array $row) => $row['impressions'] >= self::MIN_IMPRESSIONS
                    && ($row['second_read_rate'] < self::WEAK_RATE
                        || ($row['second_reads'] >= self::MIN_STRONG_READS && $row['second_read_rate'] >= self::STRONG_RATE))
            )
                ->map(function (array $row): array {
                    $weak = $row['second_read_rate'] < self::WEAK_RATE;

                    return [
                        'key' => 'article:'.$row['source_article_id'].':second-read:'.($weak ? 'weak' : 'strong'),
                        'type' => 'SECOND_READ',
                        'provider' => 'second_read',
                        'priority' => $weak ? 'MEDIUM' : 'LOW',
                        'article_id' => $row['source_article_id'],
                        'article_slug' => $row['slug'],
                        'title' => $row['title'] ?? 'Articolo #'.$row['source_article_id'],
                        'detected' => $weak ? 'Continuation debole' : 'Buona continuità di lettura',
                        'why' => sprintf('%d second read su %d impression del modulo (%.1f%%) negli ultimi 30 giorni.', $row['second_reads'], $row['impressions'], $row['second_read_rate'] * 100),
                        'suggested_action' => $weak
                            ? 'Rivedi manualmente pertinenza, copy e destinazione del modulo; nessuna modifica automatica.'
                            : 'Valuta manualmente questo articolo come modello/sorgente per una continuità editoriale pertinente.',
                        'evidence' => [
                            'window_days' => 30,
                            'impressions' => $row['impressions'],
                            'second_reads' => $row['second_reads'],
                            'rate' => $row['second_read_rate'],
                        ],
                    ];
                })
                ->values();
        } catch (Throwable) {
            return collect();
        }
    }
}
