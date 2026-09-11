<?php

namespace Tests\Unit;

use App\Services\ArticleManualSourcesDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleManualSourcesDetectorTest extends TestCase
{
    #[DataProvider('manualSourcesBodies')]
    public function test_it_recognizes_manual_sources_headings(string $body): void
    {
        $this->assertTrue(app(ArticleManualSourcesDetector::class)->hasManualSourcesSection($body));
    }

    public static function manualSourcesBodies(): array
    {
        return [
            'html h1' => ['<h1>Fonti</h1><p>Elenco.</p>'],
            'html h2 with colon' => ['<h2 class="section-title">Fonti:</h2>'],
            'html h3 nested markup' => ['<h3><strong>Fonti principali</strong></h3>'],
            'html h6 primary sources' => ['<h6>Fonti primarie</h6>'],
            'markdown atx' => ["## Fonti principali\n\n- Una fonte"],
            'markdown atx with colon' => ["###### Fonti:\n\n- Una fonte"],
            'markdown setext' => ["Fonti primarie\n----------------"],
        ];
    }

    #[DataProvider('bodiesWithoutManualSourcesHeadings')]
    public function test_it_does_not_match_a_sources_word_outside_a_heading(string $body): void
    {
        $this->assertFalse(app(ArticleManualSourcesDetector::class)->hasManualSourcesSection($body));
    }

    public static function bodiesWithoutManualSourcesHeadings(): array
    {
        return [
            'html paragraph' => ['<p>Le fonti disponibili descrivono il fenomeno.</p>'],
            'markdown paragraph' => ['Le fonti disponibili descrivono il fenomeno.'],
            'different heading' => ["## Metodologia\n\nLe fonti sono elencate nel testo."],
            'longer heading' => ['<h2>Fonti e approfondimenti</h2>'],
        ];
    }
}
