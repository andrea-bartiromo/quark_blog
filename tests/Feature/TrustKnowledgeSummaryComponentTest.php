<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Cantiere 41 (programma "100 cantieri Kairus", dipende dal Cantiere 39).
 *
 * x-kairus.trust-knowledge-summary estrae in un componente riusabile il
 * blocco consenso/incertezza/cosa_manca finora inline in
 * admin/trust-knowledge/preview.blade.php (Cantiere 40). Puramente di
 * presentazione (stringhe già pronte, non un TrustKnowledgeStatement), lo
 * si testa quindi in isolamento senza dover costruire un modello Eloquent —
 * stesso principio già in uso per x-article.primary-sources.
 */
class TrustKnowledgeSummaryComponentTest extends TestCase
{
    private function render(array $props): string
    {
        return Blade::render(
            '<x-kairus.trust-knowledge-summary :consenso="$consenso" :incertezza="$incertezza" :cosa-manca="$cosaManca ?? null" />',
            $props
        );
    }

    public function test_it_shows_consenso_and_incertezza_with_their_headings(): void
    {
        $html = $this->render([
            'consenso' => 'Un consumo moderato è considerato sicuro.',
            'incertezza' => 'Gli effetti a lungo termine restano dibattuti.',
        ]);

        $this->assertStringContainsString('Cosa sappiamo con ragionevole certezza', $html);
        $this->assertStringContainsString('Un consumo moderato è considerato sicuro.', $html);
        $this->assertStringContainsString('Cosa resta incerto o dibattuto', $html);
        $this->assertStringContainsString('Gli effetti a lungo termine restano dibattuti.', $html);
    }

    public function test_it_omits_the_cosa_manca_section_when_not_provided(): void
    {
        $html = $this->render([
            'consenso' => 'Consenso di prova.',
            'incertezza' => 'Incertezza di prova.',
        ]);

        $this->assertStringNotContainsString('Cosa manca / limiti di questa risposta', $html);
    }

    public function test_it_shows_the_cosa_manca_section_when_provided(): void
    {
        $html = $this->render([
            'consenso' => 'Consenso di prova.',
            'incertezza' => 'Incertezza di prova.',
            'cosaManca' => 'Non copre le popolazioni pediatriche.',
        ]);

        $this->assertStringContainsString('Cosa manca / limiti di questa risposta', $html);
        $this->assertStringContainsString('Non copre le popolazioni pediatriche.', $html);
    }

    public function test_it_preserves_line_breaks_in_every_prose_field(): void
    {
        $html = $this->render([
            'consenso' => "Prima riga.\nSeconda riga.",
            'incertezza' => "Punto uno.\nPunto due.",
            'cosaManca' => "Limite uno.\nLimite due.",
        ]);

        $this->assertSame(3, substr_count($html, 'white-space:pre-line'));
        $this->assertStringContainsString("Prima riga.\nSeconda riga.", $html);
        $this->assertStringContainsString("Punto uno.\nPunto due.", $html);
        $this->assertStringContainsString("Limite uno.\nLimite due.", $html);
    }

    public function test_it_escapes_html_in_every_prose_field(): void
    {
        $html = $this->render([
            'consenso' => '<script>alert(1)</script>',
            'incertezza' => '<img src=x onerror=alert(1)>',
            'cosaManca' => '<b>non innocuo</b>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>non innocuo</b>', $html);
    }
}
