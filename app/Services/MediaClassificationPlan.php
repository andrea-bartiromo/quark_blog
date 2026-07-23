<?php

namespace App\Services;

final class MediaClassificationPlan
{
    /**
     * @param  list<MediaClassificationResult>  $results
     * @param  array<int, array<string, mixed>>  $unregisteredFiles
     */
    public function __construct(
        public readonly array $results,
        public readonly string $generatedAt,
        public readonly string $planHash,
        public readonly array $unregisteredFiles = [],
    ) {}

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $counts = [
            'analyzed' => count($this->results),
            'movable' => 0,
            'noop' => 0,
            'blocked' => 0,
            'ambiguous' => 0,
            'unclassified' => 0,
            'missing_source' => 0,
            'collision' => 0,
            'error' => 0,
        ];

        foreach ($this->results as $result) {
            if (isset($counts[$result->status])) {
                $counts[$result->status]++;
            }
        }

        return $counts;
    }

    /**
     * @return list<MediaClassificationResult>
     */
    public function movable(): array
    {
        return array_values(array_filter($this->results, fn (MediaClassificationResult $r) => $r->isMovable()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'plan_hash' => $this->planHash,
            'summary' => $this->summary(),
            'results' => array_map(fn (MediaClassificationResult $r) => $r->toArray(), $this->results),
            'unregistered_files' => $this->unregisteredFiles,
        ];
    }

    /**
     * @param  list<MediaClassificationResult>  $results
     */
    public static function hashFor(array $results): string
    {
        $canonical = array_map(
            fn (MediaClassificationResult $r) => $r->mediaId.'|'.$r->currentDiskName.'|'.$r->proposedDiskName.'|'.$r->status,
            $results
        );

        sort($canonical);

        return hash('sha256', implode("\n", $canonical));
    }
}
