<?php

namespace App\Services\SearchConsole;

use App\Models\Article;

readonly class SearchOpportunity
{
    public function __construct(
        public string $type,
        public string $query,
        public ?Article $article,
        public int $impressions,
        public int $clicks,
        public ?float $ctr,
        public ?float $position,
        public float $score,
        public string $explanation,
    ) {}
}
