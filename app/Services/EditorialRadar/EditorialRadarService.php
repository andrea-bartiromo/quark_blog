<?php

namespace App\Services\EditorialRadar;

use App\Models\Article;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use App\Services\ContentHealth\ArticleContentHealthService;
use App\Services\EditorialQuality\SeoMetadataQualityAuditService;
use App\Services\EditorialQuality\SourceImageAttributionHealthService;
use App\Services\InternalLinking\InternalLinkAuditService;
use Illuminate\Support\Collection;

class EditorialRadarService
{
    public function __construct(
        private readonly ArticleContentHealthService $contentHealth,
        private readonly PercorsoCoverageAuditService $percorsoCoverage,
        private readonly SourceImageAttributionHealthService $attribution,
        private readonly SeoMetadataQualityAuditService $seo,
        private readonly InternalLinkAuditService $internalLinks,
    ) {}

    /**
     * Explainable, read-only opportunities backed only by facts available on main.
     * No score, no mutation, no automatic editorial action.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function opportunities(): Collection
    {
        $opportunities = collect();
        $articles = Article::query()
            ->published()
            ->with('contentClusters:id')
            ->orderBy('id')
            ->get();
        $publicById = $articles->keyBy('id');

        foreach ($articles as $article) {
            $this->contentHealth->evaluate($article)
                ->where('status', ArticleContentHealthService::STATUS_WARNING)
                ->reject(fn (array $finding) => $finding['id'] === 'percorso')
                ->each(function (array $finding) use ($article, $opportunities): void {
                    $opportunities->push($this->row(
                        key: "article:{$article->id}:health:{$finding['id']}",
                        type: 'UPDATE_CONTENT',
                        provider: 'content_health',
                        priority: in_array($finding['id'], ['cover', 'summary', 'sources'], true) ? 'HIGH' : 'MEDIUM',
                        articleId: $article->id,
                        articleSlug: $article->slug,
                        title: $article->title,
                        detected: $finding['label'],
                        why: $finding['reason'],
                        action: 'Rivedi il controllo editoriale indicato; nessuna modifica viene applicata automaticamente.',
                    ));
                });

            collect($this->attribution->evaluate($article))
                ->where('status', SourceImageAttributionHealthService::WARNING)
                // These three facts are already represented by Content Health.
                ->reject(fn (array $finding) => in_array($finding['id'], ['cover_alt', 'cover_credit_source', 'editorial_sources'], true))
                ->each(function (array $finding) use ($article, $opportunities): void {
                    $opportunities->push($this->row(
                        key: "article:{$article->id}:attribution:{$finding['id']}",
                        type: 'UPDATE_CONTENT',
                        provider: 'attribution_health',
                        priority: $finding['id'] === 'external_body_images' ? 'HIGH' : 'MEDIUM',
                        articleId: $article->id,
                        articleSlug: $article->slug,
                        title: $article->title,
                        detected: $finding['id'],
                        why: $finding['reason'],
                        action: 'Verifica manualmente attribuzione, fonte e licenza prima di intervenire sul contenuto.',
                    ));
                });
        }

        $coverage = $this->percorsoCoverage->audit();
        foreach ($coverage['published_without_path'] as $article) {
            if (! $publicById->has($article['id'])) {
                continue;
            }

            $opportunities->push($this->row(
                key: "article:{$article['id']}:percorso:missing",
                type: 'PERCORSO_OPPORTUNITY',
                provider: 'percorso_coverage',
                priority: 'MEDIUM',
                articleId: $article['id'],
                articleSlug: $article['slug'],
                title: $article['title'],
                detected: 'Articolo pubblicato senza Percorso',
                why: 'L’audit Percorsi non rileva alcuna membership per questo articolo pubblicato.',
                action: 'Valuta manualmente se un Percorso esistente è pertinente; non viene effettuato alcun auto-assignment.',
            ));
        }

        foreach ($coverage['single_article_paths'] as $path) {
            $opportunities->push($this->row(
                key: "percorso:{$path['id']}:singleton",
                type: 'PERCORSO_OPPORTUNITY',
                provider: 'percorso_coverage',
                priority: 'MEDIUM',
                articleId: null,
                articleSlug: null,
                title: $path['name'],
                detected: 'Percorso con un solo membro',
                why: 'Il Percorso contiene un solo articolo e quindi offre una progressione editoriale limitata.',
                action: 'Valuta se ampliare, accorpare o mantenere consapevolmente il Percorso.',
            ));
        }

        $seoRows = collect($this->seo->audit()['articles'])->keyBy('article_id');
        foreach ($articles as $article) {
            $seo = $seoRows->get($article->id);
            if ($seo === null) {
                continue;
            }

            foreach ([
                'duplicate_effective_title' => 'Titolo SEO effettivo duplicato',
                'duplicate_effective_description' => 'Descrizione SEO effettiva duplicata',
            ] as $flag => $detected) {
                if (! ($seo[$flag] ?? false)) {
                    continue;
                }

                $opportunities->push($this->row(
                    key: "article:{$article->id}:seo:{$flag}",
                    type: 'SEO_OPPORTUNITY',
                    provider: 'seo_metadata',
                    priority: 'MEDIUM',
                    articleId: $article->id,
                    articleSlug: $article->slug,
                    title: $article->title,
                    detected: $detected,
                    why: 'L’audit SEO rileva metadati effettivi identici a quelli di un altro articolo.',
                    action: 'Rivedi manualmente i metadati per distinguere chiaramente l’intento della pagina.',
                ));
            }

            if (($seo['canonical']['status'] ?? null) === 'WARNING') {
                $opportunities->push($this->row(
                    key: "article:{$article->id}:seo:canonical",
                    type: 'SEO_OPPORTUNITY',
                    provider: 'seo_metadata',
                    priority: 'HIGH',
                    articleId: $article->id,
                    articleSlug: $article->slug,
                    title: $article->title,
                    detected: 'Canonical da verificare',
                    why: (string) $seo['canonical']['reason'],
                    action: 'Verifica il canonical effettivo; nessuna URL viene riscritta automaticamente.',
                ));
            }
        }

        $linkAudit = $this->internalLinks->audit(status: Article::STATUS_PUBLISHED);
        foreach ($linkAudit->rows as $row) {
            if (! $publicById->has($row->articleId)) {
                continue;
            }

            if ($row->hasBrokenOutgoingLinks()) {
                $article = $publicById->get($row->articleId);
                $opportunities->push($this->row(
                    key: "article:{$row->articleId}:internal-linking:broken",
                    type: 'INTERNAL_LINKING',
                    provider: 'internal_link_audit',
                    priority: 'HIGH',
                    articleId: $row->articleId,
                    articleSlug: $article->slug,
                    title: $article->title,
                    detected: 'Link interno verso destinazione inesistente',
                    why: 'L’audit dei link reali nel body ha rilevato almeno un target mancante.',
                    action: 'Rivedi manualmente gli href segnalati e correggi soltanto la destinazione verificata.',
                ));
            }

            if ($row->incomingLinksCount === 0) {
                $article = $publicById->get($row->articleId);
                $opportunities->push($this->row(
                    key: "article:{$row->articleId}:internal-linking:no-incoming",
                    type: 'INTERNAL_LINKING',
                    provider: 'internal_link_audit',
                    priority: 'MEDIUM',
                    articleId: $row->articleId,
                    articleSlug: $article->slug,
                    title: $article->title,
                    detected: 'Nessun incoming body link',
                    why: 'Nessun altro body articolo risulta collegare questo contenuto secondo l’audit corrente.',
                    action: 'Valuta suggerimenti editorialmente pertinenti; non inserire link automaticamente.',
                ));
            }
        }

        foreach ($linkAudit->highConfidenceUnusedSuggestions as $suggestion) {
            $sourceId = (int) $suggestion['source']['id'];
            $targetId = (int) $suggestion['target']['id'];
            if (! $publicById->has($sourceId) || ! $publicById->has($targetId)) {
                continue;
            }

            $opportunities->push([
                ...$this->row(
                    key: "article:{$sourceId}:internal-linking:candidate:{$targetId}",
                    type: 'INTERNAL_LINKING',
                    provider: 'internal_link_suggestions',
                    priority: 'MEDIUM',
                    articleId: $sourceId,
                    articleSlug: $publicById->get($sourceId)->slug,
                    title: $suggestion['source']['title'],
                    detected: 'Suggerimento di link interno non ancora usato',
                    why: 'Il suggeritore esistente ha prodotto un candidato ad alta confidenza che non risulta inserito nel body.',
                    action: 'Valuta manualmente target e anchor; non viene effettuato auto-accept o auto-insert.',
                ),
                'candidate_target_id' => $targetId,
                'candidate_target_title' => $suggestion['target']['title'],
                'anchor_evidence' => $suggestion['anchor_text'],
                'confidence' => (int) $suggestion['confidence_score'],
            ]);
        }

        return $opportunities
            ->unique('key')
            ->sortBy(fn (array $row) => [
                match ($row['priority']) {
                    'HIGH' => 1, 'MEDIUM' => 2, default => 3
                },
                $row['type'],
                $row['key'],
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    private function row(
        string $key,
        string $type,
        string $provider,
        string $priority,
        ?int $articleId,
        ?string $articleSlug,
        string $title,
        string $detected,
        string $why,
        string $action,
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'provider' => $provider,
            'priority' => $priority,
            'article_id' => $articleId,
            'article_slug' => $articleSlug,
            'title' => $title,
            'detected' => $detected,
            'why' => $why,
            'suggested_action' => $action,
        ];
    }
}
