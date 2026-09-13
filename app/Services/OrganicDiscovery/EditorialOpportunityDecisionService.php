<?php

namespace App\Services\OrganicDiscovery;

use App\Models\Article;
use App\Models\ArticleSearchProfile;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Illuminate\Support\Collection;

/**
 * Cantiere 4: traduce le opportunità già calcolate da Search Console in una
 * coda editoriale spiegabile. Non assegna punteggi nuovi e non scrive mai:
 * compone esclusivamente il segnale esistente, la readiness e i profili.
 */
class EditorialOpportunityDecisionService
{
    public const HIGH = 'high_editorial_review';
    public const MEDIUM = 'medium_improvement';
    public const MONITOR = 'monitor';
    public const NO_ACTION = 'no_action';
    public const VERIFY = 'verify_data';

    public function __construct(
        private readonly OrganicDiscoveryReadinessService $readiness,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function decide(Collection $opportunities): Collection
    {
        $readiness = $this->readiness->auditAll()->keyBy('article_id');
        $profiles = ArticleSearchProfile::query()
            ->with(['article:id,status,published_at'])
            ->whereNotNull('primary_query')
            ->get()
            ->filter(fn (ArticleSearchProfile $profile) => $this->isPublic($profile->article))
            ->groupBy(fn (ArticleSearchProfile $profile) => $this->normalize($profile->primary_query));

        return $opportunities
            ->map(function (SearchOpportunity $opportunity) use ($readiness, $profiles): array {
                $article = $opportunity->article;
                $isPublic = $this->isPublic($article);
                $collisions = $profiles->get($this->normalize($opportunity->query), collect())
                    ->pluck('article_id')
                    ->filter(fn (int $id) => $article === null || $id !== $article->id)
                    ->values();
                $readinessRow = $isPublic ? $readiness->get($article->id) : null;

                [$decision, $reason, $action] = $this->resolve($opportunity, $article, $readinessRow, $collisions->isNotEmpty());

                return [
                    'key' => $opportunity->key,
                    'decision' => $decision,
                    'decision_label' => self::labels()[$decision],
                    'query' => $opportunity->query,
                    'opportunity_type' => $opportunity->type,
                    'opportunity_explanation' => $opportunity->explanation,
                    'impressions' => $opportunity->impressions,
                    'clicks' => $opportunity->clicks,
                    'ctr' => $opportunity->ctr,
                    'position' => $opportunity->position,
                    'article' => $isPublic ? $article : null,
                    'readiness' => $readinessRow,
                    'possible_collision_article_ids' => $collisions->all(),
                    'reason' => $reason,
                    'action' => $action,
                    'limits' => 'Decisione read-only: non modifica contenuti, link, SEO o stato di pubblicazione e non garantisce il posizionamento.',
                ];
            })
            ->sortBy(fn (array $row) => [self::order()[$row['decision']], -$row['impressions'], $row['query']])
            ->values();
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::HIGH => 'Priorità alta — revisione editoriale',
            self::MEDIUM => 'Priorità media — miglioramento consigliato',
            self::MONITOR => 'Monitorare',
            self::NO_ACTION => 'Non intervenire',
            self::VERIFY => 'Dati insufficienti / da verificare',
        ];
    }

    /** @return array<string, int> */
    public static function order(): array
    {
        return [self::HIGH => 0, self::MEDIUM => 1, self::VERIFY => 2, self::MONITOR => 3, self::NO_ACTION => 4];
    }

    /** @param array<string, mixed>|null $readiness */
    private function resolve(SearchOpportunity $opportunity, ?Article $article, ?array $readiness, bool $hasCollision): array
    {
        if ($this->isBrand($opportunity->query)) {
            return [self::NO_ACTION, 'Query brand: non è una priorità per la crescita organica non-brand.', 'Conservare come dato osservato; non cambiare un articolo solo per questa query.'];
        }

        if (! $this->isPublic($article)) {
            return [self::VERIFY, 'La query non è associata in modo affidabile a un articolo pubblico corrente.', 'Verificare manualmente l’intento e decidere se esiste una landing page editoriale adatta.'];
        }

        if ($hasCollision) {
            return [self::VERIFY, 'La query coincide con il profilo editoriale di più articoli pubblici.', 'Verificare possibile cannibalizzazione prima di modificare titolo, contenuto o collegamenti.'];
        }

        if (($readiness['state'] ?? null) === OrganicDiscoveryReadinessService::STATE_BLOCKED) {
            $reason = 'L’opportunità ha evidenza Search Console e l’articolo presenta blocchi di readiness.';

            if (in_array($opportunity->type, [
                SearchOpportunityScoringService::TYPE_GOOD_POSITION_LOW_CTR,
                SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR,
            ], true)) {
                $reason .= ' Il CTR debole rispetto alla posizione osservata rafforza la priorità editoriale.';
            }

            return [self::HIGH, $reason, 'Completare prima i requisiti editoriali e di scoperta indicati in Ricerca organica; poi rivalutare intento, titolo, description e collegamenti pertinenti.'];
        }

        if (in_array($opportunity->type, [
            SearchOpportunityScoringService::TYPE_GOOD_POSITION_LOW_CTR,
            SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR,
        ], true)) {
            return [self::HIGH, 'Query non-brand con evidenza sufficiente e CTR debole rispetto alla posizione osservata.', 'Revisionare manualmente intento, titolo, description, apertura e collegamenti pertinenti.'];
        }

        if ($opportunity->type === SearchOpportunityScoringService::TYPE_NEAR_PAGE_ONE) {
            return [self::MEDIUM, 'La query è vicina alla prima pagina ma il dato non prova da solo un problema tecnico.', 'Valutare un aggiornamento editoriale reale e collegamenti interni pertinenti; poi monitorare.'];
        }

        return [self::MONITOR, 'Il segnale è utile ma non dimostra ancora una modifica editoriale prioritaria.', 'Monitorare il prossimo import e intervenire solo con evidenza aggiuntiva.'];
    }

    private function isPublic(?Article $article): bool
    {
        return $article !== null && $article->status === Article::STATUS_PUBLISHED && $article->published_at?->isPast();
    }

    private function isBrand(string $query): bool
    {
        $query = $this->normalize($query);
        foreach (config('search-console.brand_terms', []) as $term) {
            $term = $this->normalize((string) $term);
            if ($term !== '' && str_contains($query, $term)) return true;
        }
        return false;
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''), 'UTF-8');
    }
}
