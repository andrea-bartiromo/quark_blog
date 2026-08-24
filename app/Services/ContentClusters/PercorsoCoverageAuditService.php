<?php

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;

/**
 * Audit diagnostico e read-only della copertura editoriale dei Percorsi.
 * Non assegna articoli, non riordina pivot e non modifica cluster.
 *
 * editorialOrderHealth() (Percorsi Editorial Order Health) estende questo
 * stesso audit con controlli sulla SEQUENZA editoriale — mai una
 * riscrittura delle regole di pubblico già esistenti altrove:
 * ContentClusterPublicSequence resta l'unica source of truth per "cosa è
 * pubblicamente raggiungibile ora", qui riusata (mai duplicata) per
 * derivare quali membri risultano bloccati da un gap. Nessuna mutazione:
 * un'inversione cronologica è un avviso editoriale (la narrazione può
 * intenzionalmente ignorare le date), mai un errore bloccante — solo le
 * anomalie sui dati di posizione stessi (duplicate/mancanti/non
 * valide) sono trattate come errore strutturale.
 */
class PercorsoCoverageAuditService
{
    public function __construct(
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $articles = Article::query()
            ->with('contentClusters:id,name,slug,is_active')
            ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
            ->orderBy('id')
            ->get(['id', 'title', 'slug', 'status', 'published_at']);

        $clusters = ContentCluster::query()
            ->with([
                'articles:id,title,slug,status,published_at',
                'pillarArticle:id,title,slug,status,published_at',
            ])
            ->ordered()
            ->get();

        $publishedWithoutPath = $articles
            ->filter(fn (Article $article) => $article->status === Article::STATUS_PUBLISHED && $article->contentClusters->isEmpty())
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $scheduledWithoutPath = $articles
            ->filter(fn (Article $article) => $article->status === Article::STATUS_SCHEDULED && $article->contentClusters->isEmpty())
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $clusterRows = $clusters->map(fn (ContentCluster $cluster) => $this->clusterRow($cluster))->values();

        return [
            'published_without_path' => $publishedWithoutPath,
            'scheduled_without_path' => $scheduledWithoutPath,
            'single_article_paths' => $clusterRows->filter(fn (array $row) => $row['member_count'] === 1)->values()->all(),
            'paths_with_duplicate_positions' => $clusterRows->filter(fn (array $row) => $row['duplicate_positions'] !== [])->values()->all(),
            'paths_with_non_publishable_members' => $clusterRows->filter(fn (array $row) => $row['non_publishable_members'] !== [])->values()->all(),
            'paths_with_incoherent_pillar' => $clusterRows->filter(fn (array $row) => $row['pillar_issue'] !== null)->values()->all(),
            'articles_in_multiple_paths' => $articles
                ->filter(fn (Article $article) => $article->contentClusters->count() > 1)
                ->map(fn (Article $article) => [
                    ...$this->articleSummary($article),
                    'path_count' => $article->contentClusters->count(),
                    'paths' => $article->contentClusters->pluck('slug')->values()->all(),
                ])
                ->values()
                ->all(),
            'clusters' => $clusterRows->all(),
            'policy_notes' => [
                'missing_pillar_is_not_an_error' => true,
                'multiple_paths_are_reported_not_failed' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clusterRow(ContentCluster $cluster): array
    {
        $positions = $cluster->articles
            ->map(fn (Article $article) => $article->pivot?->position)
            ->filter(fn ($position) => $position !== null)
            ->map(fn ($position) => (int) $position)
            ->values();

        $duplicatePositions = $positions
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->map(fn ($position) => (int) $position)
            ->sort()
            ->values()
            ->all();

        $nonPublishableMembers = $cluster->articles
            ->filter(fn (Article $article) => in_array($article->status, [Article::STATUS_DRAFT, Article::STATUS_REVIEW], true))
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $memberIds = $cluster->articles->pluck('id');
        $pillarIssue = null;

        if ($cluster->pillar_article_id !== null) {
            if ($cluster->pillarArticle === null) {
                $pillarIssue = 'pillar_target_missing';
            } elseif (! $memberIds->contains($cluster->pillar_article_id)) {
                $pillarIssue = 'pillar_not_in_path';
            } elseif (in_array($cluster->pillarArticle->status, [Article::STATUS_DRAFT, Article::STATUS_REVIEW], true)) {
                $pillarIssue = 'pillar_not_publishable';
            }
        }

        return [
            'id' => $cluster->id,
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'is_active' => (bool) $cluster->is_active,
            'member_count' => $cluster->articles->count(),
            'duplicate_positions' => $duplicatePositions,
            'non_publishable_members' => $nonPublishableMembers,
            'pillar_article_id' => $cluster->pillar_article_id,
            'pillar_issue' => $pillarIssue,
        ];
    }

    /**
     * @return array{id:int,title:string,slug:string,status:string}
     */
    private function articleSummary(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $article->status,
        ];
    }

    /**
     * Percorsi Editorial Order Health: read-only, mai un riordino dei
     * pivot, mai una mutazione di lifecycle_status. Ogni controllo qui è
     * classificato in una delle tre categorie richieste dalla missione:
     *
     *   structural_error     — anomalia sui DATI di posizione stessi
     *                          (duplicata/mancante/non valida): sempre
     *                          un problema, indipendentemente dalla
     *                          narrazione editoriale.
     *   publication_warning  — la sequenza pubblica (ContentClusterPublicSequence,
     *                          mai reimplementata qui) non corrisponde a
     *                          quanto il Percorso vorrebbe mostrare.
     *   editorial_advisory   — segnale informativo che PUÒ essere
     *                          intenzionale (una narrazione editoriale
     *                          può legittimamente ignorare l'ordine
     *                          cronologico): mai bloccante.
     *
     * @return array<string, mixed>
     */
    public function editorialOrderHealth(): array
    {
        $clusters = ContentCluster::query()
            ->with(['articles:id,title,slug,status,published_at'])
            ->ordered()
            ->get();

        $rows = $clusters->map(fn (ContentCluster $cluster) => $this->orderHealthRow($cluster))->values();

        return [
            'structural_error' => [
                'missing_position' => $rows->filter(fn (array $row) => $row['missing_position'] !== [])->values()->all(),
                'non_positive_position' => $rows->filter(fn (array $row) => $row['non_positive_position'] !== [])->values()->all(),
                'duplicate_position' => $rows->filter(fn (array $row) => $row['duplicate_position'] !== [])->values()->all(),
            ],
            'publication_warning' => [
                'published_beyond_gap' => $rows->filter(fn (array $row) => $row['published_beyond_gap'] !== [])->values()->all(),
                'pillar_outside_reachable_prefix' => $rows->filter(fn (array $row) => $row['pillar_outside_reachable_prefix'])->values()->all(),
                'complete_with_hidden_remainder' => $rows->filter(fn (array $row) => $row['complete_with_hidden_remainder'])->values()->all(),
            ],
            'editorial_advisory' => [
                'chronological_inversions' => $rows->filter(fn (array $row) => $row['chronological_inversions'] !== [])->values()->all(),
                'scheduled_out_of_order' => $rows->filter(fn (array $row) => $row['scheduled_out_of_order'] !== [])->values()->all(),
                'dangling_transition' => $rows->filter(fn (array $row) => $row['dangling_transition'] !== null)->values()->all(),
            ],
            'clusters' => $rows->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderHealthRow(ContentCluster $cluster): array
    {
        // $cluster->articles è già ordinato per pivot position (vedi
        // ContentCluster::articles(): ->orderByPivot('position')), stessa
        // sequenza editoriale che ContentClusterPublicSequence legge.
        $ordered = $cluster->articles->values()->all();

        $missingPosition = collect($ordered)
            ->filter(fn (Article $article) => $article->pivot?->position === null)
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $nonPositivePosition = collect($ordered)
            ->filter(fn (Article $article) => $article->pivot?->position !== null && (int) $article->pivot->position <= 0)
            ->map(fn (Article $article) => $this->articleSummary($article))
            ->values()
            ->all();

        $positionValues = collect($ordered)
            ->map(fn (Article $article) => $article->pivot?->position)
            ->filter(fn ($position) => $position !== null)
            ->map(fn ($position) => (int) $position);
        $duplicatePosition = $positionValues
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->map(fn ($position) => (int) $position)
            ->sort()
            ->values()
            ->all();

        // Riusa ContentClusterPublicSequence come unica source of truth
        // per "cosa è pubblico ora" — mai una seconda implementazione
        // dello stop-al-primo-gap qui.
        $sequence = $this->publicSequence->resolve($cluster);
        $publicIds = $sequence['articles']->pluck('id');

        $firstNonPublicIndex = null;
        foreach ($ordered as $index => $article) {
            if (! $publicIds->contains($article->id)) {
                $firstNonPublicIndex = $index;
                break;
            }
        }

        $publishedBeyondGap = $firstNonPublicIndex === null
            ? []
            : collect($ordered)
                ->slice($firstNonPublicIndex + 1)
                ->filter(fn (Article $article) => $article->status === Article::STATUS_PUBLISHED)
                ->map(fn (Article $article) => $this->articleSummary($article))
                ->values()
                ->all();

        $pillarOutsideReachablePrefix = $cluster->pillar_article_id !== null
            && ! $publicIds->contains($cluster->pillar_article_id);

        $completeWithHiddenRemainder = $cluster->lifecycle_status === ContentCluster::LIFECYCLE_COMPLETE
            && $sequence['has_hidden_remainder'];

        $chronologicalInversions = [];
        for ($i = 1; $i < count($ordered); $i++) {
            $previous = $ordered[$i - 1];
            $current = $ordered[$i];
            if ($previous->published_at !== null && $current->published_at !== null && $current->published_at->lt($previous->published_at)) {
                $chronologicalInversions[] = [
                    'earlier_position' => [...$this->articleSummary($previous), 'published_at' => $previous->published_at->toIso8601String()],
                    'later_position' => [...$this->articleSummary($current), 'published_at' => $current->published_at->toIso8601String()],
                ];
            }
        }

        $scheduledOutOfOrder = [];
        for ($i = 1; $i < count($ordered); $i++) {
            $earlier = $ordered[$i - 1];
            $later = $ordered[$i];
            if (
                $earlier->status === Article::STATUS_SCHEDULED
                && $later->status === Article::STATUS_SCHEDULED
                && $earlier->published_at !== null
                && $later->published_at !== null
                && $later->published_at->lt($earlier->published_at)
            ) {
                $scheduledOutOfOrder[] = [
                    'earlier_position' => [...$this->articleSummary($earlier), 'published_at' => $earlier->published_at->toIso8601String()],
                    'later_position' => [...$this->articleSummary($later), 'published_at' => $later->published_at->toIso8601String()],
                ];
            }
        }

        // "Punta verso una tappa non più adiacente" nella sua forma
        // strutturalmente verificabile: transition_text narra la tappa
        // SUCCESSIVA (vedi PercorsoPublicationReadinessService), quindi
        // un transition_text sull'ultima posizione non ha più nulla da
        // introdurre — è rimasto "appeso" a un riordino/rimozione.
        $last = $ordered[count($ordered) - 1] ?? null;
        $danglingTransition = ($last !== null && filled($last->pivot?->transition_text))
            ? [...$this->articleSummary($last), 'transition_text' => $last->pivot->transition_text]
            : null;

        return [
            'id' => $cluster->id,
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'lifecycle_status' => $cluster->lifecycle_status,
            'missing_position' => $missingPosition,
            'non_positive_position' => $nonPositivePosition,
            'duplicate_position' => $duplicatePosition,
            'published_beyond_gap' => $publishedBeyondGap,
            'pillar_outside_reachable_prefix' => $pillarOutsideReachablePrefix,
            'complete_with_hidden_remainder' => $completeWithHiddenRemainder,
            'chronological_inversions' => $chronologicalInversions,
            'scheduled_out_of_order' => $scheduledOutOfOrder,
            'dangling_transition' => $danglingTransition,
        ];
    }
}
