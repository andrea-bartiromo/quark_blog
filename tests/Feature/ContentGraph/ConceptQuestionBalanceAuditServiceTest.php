<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Services\ContentGraph\ConceptQuestionBalanceAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 78 (programma "100 cantieri Kairus"). Copre il metodo
 * Tukey/IQR di ConceptQuestionBalanceAuditService::audit() con casi
 * numerici verificati a mano (vedi i commenti su ciascun test per il
 * calcolo dei quartili), non solo con asserzioni "non vuoto".
 */
class ConceptQuestionBalanceAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConceptQuestionBalanceAuditService
    {
        return app(ConceptQuestionBalanceAuditService::class);
    }

    private function activeConceptWithQuestions(string $slug, int $questionCount): Concept
    {
        $concept = Concept::create([
            'name' => 'Concetto '.$slug,
            'slug' => $slug,
            'status' => Concept::STATUS_ACTIVE,
        ]);

        for ($i = 0; $i < $questionCount; $i++) {
            ConceptQuestion::create([
                'concept_id' => $concept->id,
                'question' => "Domanda {$slug} {$i}?",
            ]);
        }

        return $concept;
    }

    public function test_is_not_applicable_below_the_minimum_population(): void
    {
        // Solo 3 Concept con almeno una domanda: sotto MIN_POPULATION (5),
        // nessun quartile è statisticamente significativo.
        $this->activeConceptWithQuestions('a', 2);
        $this->activeConceptWithQuestions('b', 3);
        $this->activeConceptWithQuestions('c', 4);

        $result = $this->service()->audit();

        $this->assertFalse($result['applicable']);
        $this->assertSame(3, $result['population']);
        $this->assertSame([], $result['items']);
    }

    public function test_uniform_distribution_flags_no_outliers(): void
    {
        // 5 Concept, tutti con esattamente 4 domande: Q1=Q3=4, IQR=0 —
        // nessuno scostamento è possibile per costruzione.
        foreach (range(1, 5) as $number) {
            $this->activeConceptWithQuestions('u'.$number, 4);
        }

        $result = $this->service()->audit();

        $this->assertTrue($result['applicable']);
        $this->assertSame(5, $result['population']);
        $this->assertSame(4.0, $result['median']);
        $this->assertNull($result['lower_fence']);
        $this->assertNull($result['upper_fence']);
        $this->assertSame([], $result['items']);
    }

    public function test_flags_a_clear_over_represented_outlier(): void
    {
        // Conteggi ordinati [2,2,2,3,3,3,20], n=7.
        // Q1 (indice 1.5, interpolato tra idx1=2 e idx2=2) = 2.
        // Q3 (indice 4.5, interpolato tra idx4=3 e idx5=3) = 3.
        // IQR = 1 -> lower fence 0.5, upper fence 4.5.
        // 20 > 4.5 -> OVER_REPRESENTED; nessun altro fuori dai fence.
        $this->activeConceptWithQuestions('a', 2);
        $this->activeConceptWithQuestions('b', 2);
        $this->activeConceptWithQuestions('c', 2);
        $this->activeConceptWithQuestions('d', 3);
        $this->activeConceptWithQuestions('e', 3);
        $this->activeConceptWithQuestions('f', 3);
        $outlier = $this->activeConceptWithQuestions('g', 20);

        $result = $this->service()->audit();

        $this->assertTrue($result['applicable']);
        $this->assertSame(7, $result['population']);
        $this->assertSame(0.5, $result['lower_fence']);
        $this->assertSame(4.5, $result['upper_fence']);
        $this->assertCount(1, $result['items']);
        $this->assertSame($outlier->id, $result['items'][0]['concept_id']);
        $this->assertSame(20, $result['items'][0]['questions_count']);
        $this->assertSame(ConceptQuestionBalanceAuditService::OVER_REPRESENTED, $result['items'][0]['direction']);
    }

    public function test_flags_a_clear_under_represented_outlier_that_is_not_zero(): void
    {
        // Conteggi ordinati [1,5,6,6,7,7,8], n=7.
        // Q1 (indice 1.5, tra idx1=5 e idx2=6) = 5.5.
        // Q3 (indice 4.5, tra idx4=7 e idx5=7) = 7.
        // IQR = 1.5 -> lower fence 3.25, upper fence 9.25.
        // 1 < 3.25 -> UNDER_REPRESENTED; 8 resta dentro i fence.
        $outlier = $this->activeConceptWithQuestions('a', 1);
        $this->activeConceptWithQuestions('b', 5);
        $this->activeConceptWithQuestions('c', 6);
        $this->activeConceptWithQuestions('d', 6);
        $this->activeConceptWithQuestions('e', 7);
        $this->activeConceptWithQuestions('f', 7);
        $this->activeConceptWithQuestions('g', 8);

        $result = $this->service()->audit();

        $this->assertTrue($result['applicable']);
        $this->assertSame(3.25, $result['lower_fence']);
        $this->assertSame(9.25, $result['upper_fence']);
        $this->assertCount(1, $result['items']);
        $this->assertSame($outlier->id, $result['items'][0]['concept_id']);
        $this->assertSame(1, $result['items'][0]['questions_count']);
        $this->assertSame(ConceptQuestionBalanceAuditService::UNDER_REPRESENTED, $result['items'][0]['direction']);
    }

    public function test_active_concepts_with_zero_questions_are_excluded_from_the_population(): void
    {
        // Un Concept attivo con zero domande non entra nel calcolo (già
        // coperto da ConceptHealthService::ACTIVE_WITHOUT_QUESTIONS) — se
        // fosse incluso comprimerebbe artificialmente il quartile
        // inferiore.
        foreach (range(1, 5) as $number) {
            $this->activeConceptWithQuestions('z'.$number, 4);
        }
        Concept::create([
            'name' => 'Senza domande',
            'slug' => 'senza-domande',
            'status' => Concept::STATUS_ACTIVE,
        ]);

        $result = $this->service()->audit();

        $this->assertSame(5, $result['population']);
        $this->assertSame([], $result['items']);
    }

    public function test_inactive_and_draft_concepts_are_excluded_entirely(): void
    {
        foreach (range(1, 5) as $number) {
            $this->activeConceptWithQuestions('a'.$number, 3);
        }
        $inactive = Concept::create([
            'name' => 'Inattivo con molte domande',
            'slug' => 'inattivo-molte-domande',
            'status' => Concept::STATUS_INACTIVE,
        ]);
        foreach (range(1, 50) as $i) {
            ConceptQuestion::create(['concept_id' => $inactive->id, 'question' => "Domanda inattiva {$i}?"]);
        }
        $draft = Concept::create([
            'name' => 'Bozza con molte domande',
            'slug' => 'bozza-molte-domande',
            'status' => Concept::STATUS_DRAFT,
        ]);
        foreach (range(1, 50) as $i) {
            ConceptQuestion::create(['concept_id' => $draft->id, 'question' => "Domanda bozza {$i}?"]);
        }

        $result = $this->service()->audit();

        $this->assertSame(5, $result['population']);
        $this->assertSame([], $result['items']);
    }
}
