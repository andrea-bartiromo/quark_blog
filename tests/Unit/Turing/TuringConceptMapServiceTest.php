<?php

namespace Tests\Unit\Turing;

use App\Services\Turing\TuringConceptMapService;
use App\Services\Turing\TuringNavigationMetricsService;
use Tests\TestCase;

/**
 * Cantiere 59 (programma "100 cantieri Kairus"). Questi test verificano
 * l'INTEGRITÀ STRUTTURALE della trascrizione (nessun refuso di
 * transcrizione che punti a un capitolo inesistente, nessuna riga persa
 * o duplicata), non la sua fedeltà editoriale al documento sorgente
 * (docs/00_Governance/Architettura_Editoriale_v1.0.docx §4) — quella
 * fedeltà è stata verificata a mano riga per riga durante la stesura.
 */
class TuringConceptMapServiceTest extends TestCase
{
    public function test_every_concept_has_a_primary_chapter_that_is_a_real_turing_chapter(): void
    {
        $realChapters = array_filter(TuringNavigationMetricsService::CHAPTERS, fn (string $c) => $c !== 'hub');

        foreach (TuringConceptMapService::concepts() as $concept) {
            $this->assertContains(
                $concept['capitolo_principale'],
                $realChapters,
                "capitolo principale '{$concept['capitolo_principale']}' per l'argomento '{$concept['argomento']}' non è un vero capitolo Turing."
            );
        }
    }

    public function test_every_richiamo_is_a_real_turing_chapter_different_from_the_primary_one(): void
    {
        $realChapters = array_filter(TuringNavigationMetricsService::CHAPTERS, fn (string $c) => $c !== 'hub');

        foreach (TuringConceptMapService::concepts() as $concept) {
            foreach ($concept['richiami'] as $richiamo) {
                $this->assertContains(
                    $richiamo['capitolo'],
                    $realChapters,
                    "richiamo '{$richiamo['capitolo']}' per l'argomento '{$concept['argomento']}' non è un vero capitolo Turing."
                );
                $this->assertNotSame(
                    $concept['capitolo_principale'],
                    $richiamo['capitolo'],
                    "l'argomento '{$concept['argomento']}' richiama il proprio stesso capitolo principale."
                );
            }
        }
    }

    /**
     * Codex (PR #625, P2): il qualificatore ("teaser"/"cenno"/"richiamo"/
     * "fondamento") indica all'editor come il concetto va trattato fuori
     * dal proprio capitolo principale — non è un dettaglio decorativo,
     * ometterlo o lasciarlo vuoto renderebbe la trascrizione ambigua
     * rispetto alla fonte.
     */
    public function test_every_richiamo_has_a_non_empty_qualificatore(): void
    {
        foreach (TuringConceptMapService::concepts() as $concept) {
            foreach ($concept['richiami'] as $richiamo) {
                $this->assertNotSame(
                    '',
                    trim($richiamo['qualificatore']),
                    "il richiamo verso '{$richiamo['capitolo']}' per l'argomento '{$concept['argomento']}' non ha un qualificatore."
                );
            }
        }
    }

    public function test_no_two_concepts_share_the_exact_same_argomento(): void
    {
        $names = array_map(fn (array $c) => $c['argomento'], TuringConceptMapService::concepts());

        $this->assertCount(count($names), array_unique($names), 'ci sono argomenti duplicati nella trascrizione.');
    }

    public function test_no_concept_has_an_empty_argomento_or_livello_di_approfondimento(): void
    {
        foreach (TuringConceptMapService::concepts() as $concept) {
            $this->assertNotSame('', trim($concept['argomento']));
            $this->assertNotSame('', trim($concept['livello_approfondimento']));
        }
    }

    public function test_concepts_by_chapter_only_groups_by_real_chapters_and_never_includes_hub(): void
    {
        $grouped = TuringConceptMapService::conceptsByChapter();

        $this->assertArrayNotHasKey('hub', $grouped);
        $this->assertSame(
            array_values(array_filter(TuringNavigationMetricsService::CHAPTERS, fn (string $c) => $c !== 'hub')),
            array_keys($grouped)
        );
    }

    public function test_concepts_by_chapter_contains_every_concept_exactly_once(): void
    {
        $grouped = TuringConceptMapService::conceptsByChapter();
        $totalGrouped = array_sum(array_map('count', $grouped));

        $this->assertSame(count(TuringConceptMapService::concepts()), $totalGrouped);
    }

    public function test_concepts_by_chapter_only_contains_concepts_whose_primary_chapter_matches_the_group(): void
    {
        $grouped = TuringConceptMapService::conceptsByChapter();

        foreach ($grouped as $chapter => $concepts) {
            foreach ($concepts as $concept) {
                $this->assertSame($chapter, $concept['capitolo_principale']);
            }
        }
    }

    /**
     * Tripwire di fedeltà: il conteggio per capitolo trascritto dal §4
     * del documento sorgente (2 Enigma, 5 Computation, 4 Intelligence,
     * 11 AI, 4 Legacy, 26 totali) — una regressione qui segnala che una
     * riga è stata persa, duplicata o spostata sotto il capitolo
     * sbagliato rispetto alla trascrizione originale.
     */
    public function test_the_transcribed_count_per_chapter_matches_the_source_document(): void
    {
        $grouped = TuringConceptMapService::conceptsByChapter();

        $this->assertCount(2, $grouped['enigma']);
        $this->assertCount(5, $grouped['computation']);
        $this->assertCount(4, $grouped['intelligence']);
        $this->assertCount(11, $grouped['ai']);
        $this->assertCount(4, $grouped['legacy']);
        $this->assertCount(26, TuringConceptMapService::concepts());
    }

    /**
     * Tripwire di fedeltà sui qualificatori (Codex PR #625, P2): "Macchina
     * universale" è l'unica riga del §4 con due richiami diversi, ciascuno
     * con un qualificatore diverso dall'altro — la combinazione più a
     * rischio di essere scambiata o persa in una futura modifica.
     */
    public function test_macchina_universale_has_both_richiami_with_their_distinct_qualificatori(): void
    {
        $concept = collect(TuringConceptMapService::concepts())
            ->firstWhere('argomento', 'Macchina universale');

        $this->assertNotNull($concept);
        $this->assertSame([
            ['capitolo' => 'ai', 'qualificatore' => 'cenno'],
            ['capitolo' => 'legacy', 'qualificatore' => 'teaser'],
        ], $concept['richiami']);
    }
}
