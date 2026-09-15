<?php

namespace App\Services\ContentGraph;

use App\Models\Concept;
use Illuminate\Support\Collection;

/**
 * Cantiere 78 (programma "100 cantieri Kairus"). Nessun servizio esistente
 * nel Content Graph confronta i Concept tra loro: ConceptHealthService,
 * PublicAnswerableQuestionCoverageService e gli altri audit classificano
 * ciascun Concept contro una regola ASSOLUTA (zero domande, nessuna
 * domanda pubblicamente rispondibile, link orfano), mai relativa alla
 * popolazione. Questo servizio copre quella lacuna: individua i Concept
 * il cui numero di domande è uno scostamento statistico significativo
 * rispetto ai propri pari — metodo Tukey/IQR (Q1/Q3 + 1.5×IQR), lo stesso
 * standard usato per il rilevamento di outlier in un boxplot: nessuna
 * soglia inventata, solo distribuzione osservata. Sia in eccesso
 * (sovra-rappresentato) sia in difetto (sotto-rappresentato, ma diverso
 * da zero).
 *
 * Deliberatamente ESCLUDE dalla popolazione di riferimento i Concept
 * attivi con zero domande: quel caso è già itemizzato da
 * ConceptHealthService::ACTIVE_WITHOUT_QUESTIONS — includerli qui
 * duplicherebbe quel segnale e comprimerebbe artificialmente il quartile
 * inferiore, rischiando di far sembrare "normali" concept con una sola
 * domanda solo perché la popolazione include anche zeri.
 *
 * Sola lettura: non crea, modifica né elimina mai un Concept o una
 * ConceptQuestion. Un editor decide se e come intervenire.
 */
class ConceptQuestionBalanceAuditService
{
    public const OVER_REPRESENTED = 'OVER_REPRESENTED';

    public const UNDER_REPRESENTED = 'UNDER_REPRESENTED';

    /**
     * Sotto questa soglia un quartile non è statisticamente significativo
     * (troppo pochi punti): non una costante "vera", solo un floor
     * ragionevole contro il rumore su campioni minuscoli — stesso
     * principio della soglia di evidenza MIN_IMPRESSIONS già usata dal
     * rilevatore di cannibalizzazione (programma Kairus Organic
     * Discovery, Cantiere 5).
     */
    private const MIN_POPULATION = 5;

    /**
     * @return array{
     *     applicable: bool,
     *     population: int,
     *     median: float|null,
     *     lower_fence: float|null,
     *     upper_fence: float|null,
     *     items: list<array{concept_id:int, name:string, slug:string, questions_count:int, direction:string}>,
     * }
     */
    public function audit(): array
    {
        $rows = Concept::query()
            ->active()
            ->withCount('questions')
            ->orderBy('name')
            ->get()
            ->filter(fn (Concept $concept) => (int) $concept->questions_count > 0)
            ->values();

        if ($rows->count() < self::MIN_POPULATION) {
            return $this->notApplicable($rows->count());
        }

        $counts = $rows->pluck('questions_count')->map(fn ($count) => (int) $count)->sort()->values();
        $q1 = $this->percentile($counts, 0.25);
        $q3 = $this->percentile($counts, 0.75);
        $iqr = $q3 - $q1;

        if ($iqr <= 0.0) {
            // Distribuzione uniforme (o quasi): nessuno scostamento è
            // statisticamente significativo — mai forzare un falso
            // positivo solo perché ogni Concept ha lo stesso numero di
            // domande (o quasi).
            return [
                'applicable' => true,
                'population' => $rows->count(),
                'median' => $this->percentile($counts, 0.5),
                'lower_fence' => null,
                'upper_fence' => null,
                'items' => [],
            ];
        }

        $lowerFence = $q1 - 1.5 * $iqr;
        $upperFence = $q3 + 1.5 * $iqr;

        $items = $rows
            ->filter(fn (Concept $concept) => $concept->questions_count < $lowerFence || $concept->questions_count > $upperFence)
            ->map(fn (Concept $concept) => [
                'concept_id' => $concept->id,
                'name' => $concept->name,
                'slug' => $concept->slug,
                'questions_count' => (int) $concept->questions_count,
                'direction' => $concept->questions_count > $upperFence ? self::OVER_REPRESENTED : self::UNDER_REPRESENTED,
            ])
            ->values()
            ->all();

        return [
            'applicable' => true,
            'population' => $rows->count(),
            'median' => $this->percentile($counts, 0.5),
            'lower_fence' => $lowerFence,
            'upper_fence' => $upperFence,
            'items' => $items,
        ];
    }

    /**
     * @return array{applicable: bool, population: int, median: null, lower_fence: null, upper_fence: null, items: array{}}
     */
    private function notApplicable(int $population): array
    {
        return [
            'applicable' => false,
            'population' => $population,
            'median' => null,
            'lower_fence' => null,
            'upper_fence' => null,
            'items' => [],
        ];
    }

    /**
     * Percentile a interpolazione lineare su una Collection già ordinata
     * — lo stesso metodo di default di numpy/Excel (PERCENTILE.INC).
     *
     * @param  Collection<int, int>  $sorted
     */
    private function percentile(Collection $sorted, float $p): float
    {
        $count = $sorted->count();

        if ($count === 1) {
            return (float) $sorted->first();
        }

        $index = $p * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $weight = $index - $lower;

        $lowerValue = (float) $sorted->get($lower);
        $upperValue = (float) $sorted->get($upper);

        return $lowerValue + ($upperValue - $lowerValue) * $weight;
    }
}
