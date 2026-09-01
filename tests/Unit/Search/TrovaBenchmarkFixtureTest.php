<?php

namespace Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Tests\Support\TrovaBenchmarkEvaluator;

class TrovaBenchmarkFixtureTest extends TestCase
{
    public function test_the_human_reviewed_fixture_has_a_stable_complete_contract(): void
    {
        $fixture = $this->fixture();
        $cases = $fixture['cases'];

        $this->assertSame(1, $fixture['version']);
        $this->assertSame(3, $fixture['k']);
        $this->assertCount(count(array_unique(array_column($cases, 'id'))), $cases);
        $this->assertSameCanonicalizing(
            ['exact', 'synonym', 'question', 'typo', 'zero_result', 'noise'],
            array_values(array_unique(array_column($cases, 'kind'))),
        );

        foreach ($cases as $case) {
            $this->assertIsString($case['query']);
            $this->assertNotSame('', trim($case['query']));
            $this->assertIsArray($case['expected']);
            $this->assertSame($case['expected'] === [], $case['expect_no_results']);
        }
    }

    public function test_metrics_do_not_turn_missing_results_into_success_or_hide_duplicates(): void
    {
        $cases = [
            ['id' => 'a', 'expected' => ['concept:a'], 'expect_no_results' => false],
            ['id' => 'b', 'expected' => [], 'expect_no_results' => true],
        ];

        $metrics = (new TrovaBenchmarkEvaluator)->evaluate($cases, [
            'a' => ['concept:a', 'concept:a'],
            'b' => [],
        ], 3);

        $this->assertSame(0.25, $metrics['precision_at_k']);
        $this->assertSame(0.5, $metrics['no_result_rate']);
        $this->assertSame(1 / 6, $metrics['duplicate_rate']);
        $this->assertSame(2, $metrics['cases']);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $json = file_get_contents(__DIR__.'/../../Fixtures/trova/benchmark-v1.json');

        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }
}
