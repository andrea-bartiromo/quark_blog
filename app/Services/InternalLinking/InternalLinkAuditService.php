<?php

namespace App\Services\InternalLinking;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\ArticleSlugRedirect;
use App\Services\ArticleLinkInsertionService;
use Illuminate\Support\Collection;

/**
 * Motore di App\Console\Commands\InternalLinkAuditCommand
 * (content:internal-link-audit) — SOLA LETTURA, mai una scrittura: nessuna
 * chiamata a save()/update()/delete() in questa classe (vedi
 * tests/Feature/InternalLinkAuditCommandTest.php per la prova che
 * l'esecuzione non modifica alcun articolo).
 *
 * Riusa la definizione esistente di "collegamento ad articolo"
 * (App\Services\ArticleLinkInsertionService::internalArticleLinkOccurrences(),
 * stesso pattern /articolo/{slug} già usato dal badge Admin e dal
 * suggeritore) — nessun parser HTML nuovo o divergente.
 *
 * Un solo passaggio su TUTTO il corpus (indipendentemente da eventuali
 * filtri --article=/--status=) costruisce contemporaneamente: risoluzione
 * dei target, classificazione dei link uscenti, e conteggio degli
 * "incoming links" per ogni articolo — quest'ultimo richiede di conoscere
 * chi altro nel sito collega un dato articolo, non solo il suo stesso
 * body, quindi non può essere limitato al sottoinsieme filtrato senza
 * perdere accuratezza (FASE 17).
 */
class InternalLinkAuditService
{
    /** Soglia oltre la quale un suggerimento ancora 'proposed' (non rivisto dalla redazione) è considerato un'opportunità ad alta confidenza da segnalare nell'audit — non un invito ad automatizzarne l'inserimento. */
    private const HIGH_CONFIDENCE_THRESHOLD = 70;

    private const MAX_TOP_OPPORTUNITIES = 10;

    public function __construct(
        private readonly ArticleLinkInsertionService $insertionService,
    ) {}

    public function audit(?int $articleId = null, ?string $status = null): InternalLinkAuditReport
    {
        $allArticles = Article::query()->get(['id', 'title', 'slug', 'status', 'published_at', 'body']);
        $bySlug = $allArticles->keyBy('slug');

        $redirectTargetSlugByOldSlug = ArticleSlugRedirect::query()
            ->with('article:id,slug')
            ->get()
            ->filter(fn (ArticleSlugRedirect $r) => $r->article !== null)
            ->mapWithKeys(fn (ArticleSlugRedirect $r) => [$r->old_slug => $r->article->slug]);

        [$outgoingByArticleId, $incomingCountBySlug] = $this->buildLinkGraph($allArticles, $bySlug, $redirectTargetSlugByOldSlug);

        $subjects = $allArticles;

        if ($articleId !== null) {
            $subjects = $subjects->where('id', $articleId);
        }

        if ($status !== null) {
            $subjects = $subjects->where('status', $status);
        }

        $rows = $subjects
            ->map(fn (Article $article) => $this->buildRow($article, $outgoingByArticleId[$article->id], $incomingCountBySlug))
            ->values();

        return new InternalLinkAuditReport(
            analyzed: $rows->count(),
            withoutOutgoingLinks: $rows->filter(fn (InternalLinkAuditRow $r) => $r->outgoingDistinctCount === 0)->count(),
            withOneOutgoingLink: $rows->filter(fn (InternalLinkAuditRow $r) => $r->outgoingDistinctCount === 1)->count(),
            withTwoOrMoreOutgoingLinks: $rows->filter(fn (InternalLinkAuditRow $r) => $r->outgoingDistinctCount >= 2)->count(),
            brokenLinks: $rows->sum(fn (InternalLinkAuditRow $r) => $r->countByClassification('missing')),
            selfLinks: $rows->sum(fn (InternalLinkAuditRow $r) => $r->countByClassification('self')),
            unpublishedTargets: $rows->sum(fn (InternalLinkAuditRow $r) => $r->countByClassification('unpublished')),
            redirectedLinks: $rows->sum(fn (InternalLinkAuditRow $r) => $r->countByClassification('redirected')),
            articlesWithAmbiguousAnchors: $rows->filter(fn (InternalLinkAuditRow $r) => $r->hasAmbiguousAnchor)->count(),
            isolatedArticles: $rows->filter(fn (InternalLinkAuditRow $r) => $r->isOrphan())->count(),
            rows: $rows->all(),
            publishedWithoutIncomingLinks: $this->publishedWithoutIncomingLinks($rows),
            scheduledWithoutInternalLinks: $this->scheduledWithoutInternalLinks($rows),
            highConfidenceUnusedSuggestions: $this->highConfidenceUnusedSuggestions($subjects),
        );
    }

    /**
     * @param  Collection<int, Article>  $allArticles
     * @param  Collection<string, Article>  $bySlug
     * @param  Collection<string, string>  $redirectTargetSlugByOldSlug
     * @return array{0: array<int, array<int, array{slug:string,anchorText:string,classification:string}>>, 1: array<string, int>}
     */
    private function buildLinkGraph(Collection $allArticles, Collection $bySlug, Collection $redirectTargetSlugByOldSlug): array
    {
        $outgoingByArticleId = [];
        $incomingCountBySlug = array_fill_keys($allArticles->pluck('slug')->all(), 0);

        foreach ($allArticles as $article) {
            $occurrences = $this->insertionService->internalArticleLinkOccurrences((string) $article->body);

            $classified = array_map(
                fn (array $o) => [...$o, 'classification' => $this->classify($o['slug'], $article, $bySlug, $redirectTargetSlugByOldSlug)],
                $occurrences
            );

            $outgoingByArticleId[$article->id] = $classified;

            $resolvedTargetSlugs = array_unique(array_filter(array_map(
                fn (array $o) => $this->resolveToCurrentSlug($o['slug'], $bySlug, $redirectTargetSlugByOldSlug),
                $occurrences
            )));

            foreach ($resolvedTargetSlugs as $resolvedSlug) {
                if ($resolvedSlug !== $article->slug && array_key_exists($resolvedSlug, $incomingCountBySlug)) {
                    $incomingCountBySlug[$resolvedSlug]++;
                }
            }
        }

        return [$outgoingByArticleId, $incomingCountBySlug];
    }

    private function resolveToCurrentSlug(string $slug, Collection $bySlug, Collection $redirectTargetSlugByOldSlug): ?string
    {
        if ($bySlug->has($slug)) {
            return $slug;
        }

        return $redirectTargetSlugByOldSlug->get($slug);
    }

    private function classify(string $targetSlug, Article $source, Collection $bySlug, Collection $redirectTargetSlugByOldSlug): string
    {
        if ($targetSlug === $source->slug) {
            return 'self';
        }

        if ($bySlug->has($targetSlug)) {
            return $bySlug->get($targetSlug)->status === Article::STATUS_PUBLISHED ? 'valid' : 'unpublished';
        }

        if ($redirectTargetSlugByOldSlug->has($targetSlug)) {
            return 'redirected';
        }

        return 'missing';
    }

    /**
     * @param  array<int, array{slug:string,anchorText:string,classification:string}>  $outgoingLinks
     */
    private function buildRow(Article $article, array $outgoingLinks, array $incomingCountBySlug): InternalLinkAuditRow
    {
        return new InternalLinkAuditRow(
            articleId: $article->id,
            title: $article->title,
            slug: $article->slug,
            status: $article->status,
            outgoingLinks: $outgoingLinks,
            outgoingDistinctCount: count(array_unique(array_column($outgoingLinks, 'slug'))),
            incomingLinksCount: $incomingCountBySlug[$article->slug] ?? 0,
            hasAmbiguousAnchor: $this->hasAmbiguousAnchor($outgoingLinks),
        );
    }

    /**
     * Un'anchor è "ambigua" se lo stesso testo cliccabile (case/spazi
     * normalizzati) compare più volte nello stesso articolo puntando a
     * destinazioni DIVERSE — un lettore che rivede la stessa frase non può
     * sapere dove porta senza cliccare (FASE 16, "anchor duplicati").
     *
     * @param  array<int, array{slug:string,anchorText:string,classification:string}>  $outgoingLinks
     */
    private function hasAmbiguousAnchor(array $outgoingLinks): bool
    {
        $targetsByAnchor = [];

        foreach ($outgoingLinks as $link) {
            $key = mb_strtolower(trim($link['anchorText']), 'UTF-8');

            if ($key === '') {
                continue;
            }

            $targetsByAnchor[$key][$link['slug']] = true;
        }

        foreach ($targetsByAnchor as $targets) {
            if (count($targets) > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, InternalLinkAuditRow>  $rows
     * @return array<int, array{id:int,title:string,slug:string}>
     */
    private function publishedWithoutIncomingLinks(Collection $rows): array
    {
        return $rows
            ->filter(fn (InternalLinkAuditRow $r) => $r->isOrphan())
            ->take(self::MAX_TOP_OPPORTUNITIES)
            ->map(fn (InternalLinkAuditRow $r) => ['id' => $r->articleId, 'title' => $r->title, 'slug' => $r->slug])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, InternalLinkAuditRow>  $rows
     * @return array<int, array{id:int,title:string,slug:string}>
     */
    private function scheduledWithoutInternalLinks(Collection $rows): array
    {
        return $rows
            ->filter(fn (InternalLinkAuditRow $r) => $r->status === Article::STATUS_SCHEDULED && $r->outgoingDistinctCount === 0)
            ->take(self::MAX_TOP_OPPORTUNITIES)
            ->map(fn (InternalLinkAuditRow $r) => ['id' => $r->articleId, 'title' => $r->title, 'slug' => $r->slug])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Article>  $subjects
     * @return array<int, array{id:int,source:array{id:int,title:string},target:array{id:int,title:string},anchor_text:string,confidence_score:int}>
     */
    private function highConfidenceUnusedSuggestions(Collection $subjects): array
    {
        return ArticleLinkSuggestion::proposed()
            ->whereIn('source_article_id', $subjects->pluck('id'))
            ->where('confidence_score', '>=', self::HIGH_CONFIDENCE_THRESHOLD)
            ->with(['sourceArticle:id,title', 'targetArticle:id,title'])
            ->orderByDesc('confidence_score')
            ->limit(self::MAX_TOP_OPPORTUNITIES)
            ->get()
            ->map(fn (ArticleLinkSuggestion $s) => [
                'id' => $s->id,
                'source' => ['id' => $s->sourceArticle->id, 'title' => $s->sourceArticle->title],
                'target' => ['id' => $s->targetArticle->id, 'title' => $s->targetArticle->title],
                'anchor_text' => $s->anchor_text,
                'confidence_score' => $s->confidence_score,
            ])
            ->all();
    }
}
