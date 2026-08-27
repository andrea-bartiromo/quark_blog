<?php

namespace App\Services\ContentGraph;

use App\Models\ArticleConcept;
use App\Models\Concept;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only diagnostics for Article ↔ Concept relationships.
 *
 * Existing published-article orphans are delegated to
 * ContentGraphOrphanAuditService. This service adds only relationship facts
 * that were not already actionable on main.
 */
class ArticleConceptDiagnosticsService
{
    public const PUBLISHED_ARTICLE_WITH_INACTIVE_CONCEPT = 'PUBLISHED_ARTICLE_WITH_INACTIVE_CONCEPT';

    public const ACTIVE_CONCEPT_ONLY_NON_PUBLIC_ARTICLES = 'ACTIVE_CONCEPT_ONLY_NON_PUBLIC_ARTICLES';

    public function __construct(
        private readonly ContentGraphOrphanAuditService $orphanAudit,
    ) {}

    /**
     * @return array{
     *     published_articles_without_concept:list<array{id:int,title:string,slug:string,status:string}>,
     *     findings:list<array<string,mixed>>,
     *     policy_notes:list<string>
     * }
     */
    public function audit(): array
    {
        $findings = collect();

        ArticleConcept::query()
            ->with(['article', 'concept'])
            ->whereHas('article', fn (Builder $query) => $query->published())
            ->whereHas('concept', fn (Builder $query) => $query
                ->where('status', '!=', Concept::STATUS_ACTIVE))
            ->orderBy('article_id')
            ->orderBy('concept_id')
            ->get()
            ->each(function (ArticleConcept $relation) use ($findings) {
                $findings->push([
                    'code' => self::PUBLISHED_ARTICLE_WITH_INACTIVE_CONCEPT,
                    'article_id' => $relation->article_id,
                    'article_title' => $relation->article->title,
                    'concept_id' => $relation->concept_id,
                    'concept_name' => $relation->concept->name,
                    'relation_type' => $relation->relation_type,
                    'article_edit_url' => route('admin.articles.edit', $relation->article_id),
                    'concept_edit_url' => route('admin.concepts.edit', $relation->concept_id),
                ]);
            });

        Concept::query()
            ->active()
            ->whereHas('articleLinks')
            ->whereDoesntHave(
                'articleLinks.article',
                fn (Builder $query) => $query->published(),
            )
            ->withCount('articleLinks')
            ->orderBy('name')
            ->get()
            ->each(function (Concept $concept) use ($findings) {
                $findings->push([
                    'code' => self::ACTIVE_CONCEPT_ONLY_NON_PUBLIC_ARTICLES,
                    'concept_id' => $concept->id,
                    'concept_name' => $concept->name,
                    'article_links_count' => (int) $concept->article_links_count,
                    'concept_edit_url' => route('admin.concepts.edit', $concept),
                ]);
            });

        return [
            'published_articles_without_concept' => $this->orphanAudit->orphanArticles(),
            'findings' => $findings->values()->all(),
            'policy_notes' => [
                'Nessun vincolo aggiuntivo su primary/supporting: il dominio definisce solo il vocabolario.',
                'Nessuna soglia aggiuntiva sul peso: la sola policy esplicita resta 0–255.',
            ],
        ];
    }
}
