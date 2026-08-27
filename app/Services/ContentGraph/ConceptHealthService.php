<?php

namespace App\Services\ContentGraph;

use App\Models\Concept;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Deterministic, read-only operational health for every Concept.
 *
 * The service only classifies facts already present in the Content Graph. It
 * never writes Concept, Article or Question state and deliberately avoids an
 * opaque numeric score.
 */
class ConceptHealthService
{
    public const READY = 'READY';

    public const ATTENTION = 'ATTENTION';

    public const INCOMPLETE = 'INCOMPLETE';

    public const ACTIVE_WITHOUT_ARTICLE_LINK = 'ACTIVE_WITHOUT_ARTICLE_LINK';

    public const ACTIVE_WITHOUT_QUESTIONS = 'ACTIVE_WITHOUT_QUESTIONS';

    public const NO_PUBLIC_ANSWERABLE_QUESTION = 'NO_PUBLIC_ANSWERABLE_QUESTION';

    public const INACTIVE_WITH_PUBLIC_RELATIONS = 'INACTIVE_WITH_PUBLIC_RELATIONS';

    /**
     * Return all Concept classifications with a bounded query shape.
     *
     * withCount()/withExists() compile to correlated subqueries in this single
     * catalogue query; adding Concepts cannot create an N+1.
     *
     * @return Collection<int, array{
     *     concept_id:int,
     *     name:string,
     *     slug:string,
     *     concept_status:string,
     *     health:string,
     *     label:string,
     *     codes:list<string>
     * }>
     */
    public function all(): Collection
    {
        return $this->query()
            ->orderBy('name')
            ->get()
            ->map(fn (Concept $concept) => $this->classifyLoaded($concept));
    }

    /**
     * Classify one Concept using the same bounded aggregate query.
     *
     * @return array{
     *     concept_id:int,
     *     name:string,
     *     slug:string,
     *     concept_status:string,
     *     health:string,
     *     label:string,
     *     codes:list<string>
     * }
     */
    public function classify(Concept $concept): array
    {
        $loaded = $this->query()->findOrFail($concept->getKey());

        return $this->classifyLoaded($loaded);
    }

    private function query(): Builder
    {
        return Concept::query()
            ->withCount(['articleLinks', 'questions'])
            ->withExists([
                'questions as has_public_answerable_question' => fn (Builder $query) => $query->publiclyAnswerable(),
                'articleLinks as has_published_article_relation' => fn (Builder $query) => $query
                    ->whereHas('article', fn (Builder $articleQuery) => $articleQuery->published()),
            ]);
    }

    private function classifyLoaded(Concept $concept): array
    {
        $codes = [];

        if ($concept->status === Concept::STATUS_ACTIVE) {
            if ((int) $concept->article_links_count === 0) {
                $codes[] = self::ACTIVE_WITHOUT_ARTICLE_LINK;
            }

            if ((int) $concept->questions_count === 0) {
                $codes[] = self::ACTIVE_WITHOUT_QUESTIONS;
            }

            if (! (bool) $concept->has_public_answerable_question) {
                $codes[] = self::NO_PUBLIC_ANSWERABLE_QUESTION;
            }

            $health = $codes === [] ? self::READY : self::INCOMPLETE;
        } else {
            if ((bool) $concept->has_published_article_relation) {
                $codes[] = self::INACTIVE_WITH_PUBLIC_RELATIONS;
            }

            $health = $codes === [] ? self::READY : self::ATTENTION;
        }

        return [
            'concept_id' => $concept->id,
            'name' => $concept->name,
            'slug' => $concept->slug,
            'concept_status' => $concept->status,
            'health' => $health,
            'label' => $this->label($health),
            'codes' => $codes,
        ];
    }

    private function label(string $health): string
    {
        return match ($health) {
            self::READY => 'Pronto',
            self::ATTENTION => 'Richiede attenzione',
            self::INCOMPLETE => 'Incompleto',
        };
    }
}
