<?php

namespace Tests\Support;

final class TrovaBenchmarkEvaluator
{
    /**
     * @param  list<array{id:string, expected:list<string>, expect_no_results:bool}>  $cases
     * @param  array<string, list<string>>  $actualByCase
     * @return array{precision_at_k:float,no_result_rate:float,duplicate_rate:float,cases:int}
     */
    public function evaluate(array $cases, array $actualByCase, int $k): array
    {
        $precision = 0.0;
        $noResults = 0;
        $duplicates = 0;

        foreach ($cases as $case) {
            $actual = array_slice($actualByCase[$case['id']] ?? [], 0, $k);
            $precision += count(array_intersect(array_unique($actual), $case['expected'])) / max(1, count($actual));
            $noResults += $actual === [] ? 1 : 0;
            $duplicates += count($actual) - count(array_unique($actual));
        }

        $count = count($cases);

        return [
            'precision_at_k' => $count === 0 ? 0.0 : $precision / $count,
            'no_result_rate' => $count === 0 ? 0.0 : $noResults / $count,
            'duplicate_rate' => $count === 0 ? 0.0 : $duplicates / max(1, $count * $k),
            'cases' => $count,
        ];
    }
}
