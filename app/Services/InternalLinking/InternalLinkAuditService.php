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
        private readonly InternalLinkTemporalEligibility $temporalEligibility = new InternalLinkTemporalEligibility,
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
            scheduledSafeLinks: $rows->sum(fn (InternalLinkAuditRow $r) => $r->countByClassification('scheduled_safe')),
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
     * @return array{0: array<int, array<int, array{slug:string,anchorText:string,classification:string,resolvedSlug:?string}>>, 1: array<string, int>}
     */
    private function buildLinkGraph(Collection $allArticles, Collection $bySlug, Collection $redirectTargetSlugByOldSlug): array
    {
        $outgoingByArticleId = [];
        $incomingCountBySlug = array_fill_keys($allArticles->pluck('slug')->all(), 0);

        foreach ($allArticles as $article) {
            $occurrences = $this->insertionService->internalArticleLinkOccurrences((string) $article->body);

            $classified = array_map(
                fn (array $o) => [
                    ...$o,
                    'classification' => $this->classify($o['slug'], $article, $bySlug, $redirectTargetSlugByOldSlug),
                    // Regressione Codex (PR #158, P2): un redirect verso lo
                    // stesso target del suo slug corrente non è un secondo
                    // collegamento distinto — la deduplicazione (buildRow())
                    // usa questa identità risolta, non lo slug grezzo scritto
                    // nell'href.
                    'resolvedSlug' => $this->resolveToCurrentSlug($o['slug'], $bySlug, $redirectTargetSlugByOldSlug),
                ],
                $occurrences
            );

            $outgoingByArticleId[$article->id] = $classified;

            foreach (array_unique(array_filter(array_column($classified, 'resolvedSlug'))) as $resolvedSlug) {
                // Regressione Codex (PR #158, P2): un incoming link conta
                // solo se il target risolto è davvero pubblicamente
                // visibile — un redirect (o uno slug diretto) che punta a un
                // articolo non pubblico non è un collegamento che un
                // lettore può davvero seguire, non deve "salvare" quel
                // target dall'essere considerato isolato.
                $resolvedArticle = $bySlug->get($resolvedSlug);

                // Nota V2.1: qui resta SOLO "pubblicamente visibile ora",
                // deliberatamente non esteso alla sicurezza temporale
                // scheduled→scheduled sotto — un lettore non può seguire
                // DAVVERO questo link finché $article stessa (la sorgente)
                // non è a sua volta pubblica, quindi non è ancora un
                // incoming link reale, indipendentemente da quanto sia
                // "sicuro" il collegamento in prospettiva. Non influisce
                // comunque su isOrphan() (si applica solo a status
                // 'published'), quindi nessun comportamento visibile
                // cambia per un target ancora scheduled.
                if ($resolvedSlug !== $article->slug
                    && array_key_exists($resolvedSlug, $incomingCountBySlug)
                    && $resolvedArticle !== null
                    && $this->temporalEligibility->isPubliclyVisible($resolvedArticle)) {
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

    /**
     * Internal Linking V2.1 — classifica un link uscente rispetto al suo
     * stato REALE corrente e a quello, altrettanto reale e corrente, di
     * $source: entrambi vengono sempre letti "adesso" (l'audit non è mai
     * persistito, vedi InternalLinkAuditRow), quindi un target riprogrammato
     * più avanti, retrocesso a bozza, o una sorgente pubblicata in anticipo
     * rispetto al previsto vengono automaticamente rilevati alla prossima
     * esecuzione senza bisogno di logica dedicata per ciascun caso — sono
     * solo il risultato di rivalutare la stessa regola (vedi
     * InternalLinkTemporalEligibility) sullo stato presente.
     *
     * 'unpublished' resta riservato ai casi realmente anomali: un target
     * che esiste ma non è (e non sarà deterministicamente, prima che
     * $source diventi pubblica) raggiungibile — mai mascherato.
     * 'scheduled_safe' rende invece esplicito, non silenzioso, il caso in
     * cui il target non è ancora pubblico ORA ma lo sarà con certezza prima
     * che $source lo diventi: un fatto distinto da 'valid' (che significa
     * "raggiungibile in questo momento"), non un sinonimo.
     */
    private function classify(string $targetSlug, Article $source, Collection $bySlug, Collection $redirectTargetSlugByOldSlug): string
    {
        if ($targetSlug === $source->slug) {
            return 'self';
        }

        if ($bySlug->has($targetSlug)) {
            return $this->classifyTarget($source, $bySlug->get($targetSlug));
        }

        if ($redirectTargetSlugByOldSlug->has($targetSlug)) {
            $resolvedArticle = $bySlug->get($redirectTargetSlugByOldSlug->get($targetSlug));

            if ($resolvedArticle === null) {
                return 'unpublished';
            }

            $classification = $this->classifyTarget($source, $resolvedArticle);

            // Un redirect verso un target sicuro-perché-scheduled resta
            // comunque un redirect (lo slug scritto nel link non è più
            // quello corrente) — 'redirected' e 'scheduled_safe' sono due
            // fatti indipendenti (identità dello slug vs. raggiungibilità
            // temporale), 'scheduled_safe' qui significherebbe "il target
            // risolto è sicuro" ma nasconderebbe che lo slug è comunque da
            // aggiornare. Solo 'unpublished' resta un'anomalia da riportare
            // as-is, essendo l'unico caso realmente problematico.
            return $classification === 'unpublished' ? 'unpublished' : 'redirected';
        }

        return 'missing';
    }

    private function classifyTarget(Article $source, Article $target): string
    {
        if ($this->temporalEligibility->isPubliclyVisible($target)) {
            return 'valid';
        }

        return $this->temporalEligibility->isTargetSafeForSource($source, $target) ? 'scheduled_safe' : 'unpublished';
    }

    /**
     * @param  array<int, array{slug:string,anchorText:string,classification:string,resolvedSlug:?string}>  $outgoingLinks
     */
    private function buildRow(Article $article, array $outgoingLinks, array $incomingCountBySlug): InternalLinkAuditRow
    {
        // Regressione Codex (PR #158, P2): deduplicare per identità
        // RISOLTA (resolvedSlug), non per lo slug grezzo scritto
        // nell'href — un vecchio slug (redirect) e lo slug corrente dello
        // stesso articolo non sono due destinazioni distinte. Uno slug
        // 'missing' (resolvedSlug null) resta invece distinto per se
        // stesso: due link rotti verso lo STESSO slug inesistente sono
        // comunque un solo problema da segnalare, non due.
        $distinctIdentities = array_unique(array_map(
            fn (array $l) => $l['resolvedSlug'] ?? 'missing:'.$l['slug'],
            $outgoingLinks
        ));

        return new InternalLinkAuditRow(
            articleId: $article->id,
            title: $article->title,
            slug: $article->slug,
            status: $article->status,
            outgoingLinks: $outgoingLinks,
            outgoingDistinctCount: count($distinctIdentities),
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
     * Codex (PR #165, round 12): un suggerimento 'proposed' ad alta
     * confidenza il cui target è stato riprogrammato DOPO la sorgente (o
     * comunque non è più temporalmente sicuro secondo
     * InternalLinkTemporalEligibility) resta 'proposed' a livello di
     * database — l'unica revalidazione avviene quando la sorgente viene di
     * nuovo salvata (vedi ArticleLinkSuggestionService::markAccepted()),
     * mai qui. Senza questo filtro, applicato PRIMA di ->take(), una simile
     * riga stale poteva occupare uno dei MAX_TOP_OPPORTUNITIES slot al
     * posto di un'opportunità realmente inseribile — stesso principio
     * "mai limitare prima di filtrare" già corretto in questa stessa PR per
     * Article::proposedLinkSuggestions() e
     * ArticleLinkSuggestionService::analyzeForSource().
     *
     * @param  Collection<int, Article>  $subjects
     * @return array<int, array{id:int,source:array{id:int,title:string},target:array{id:int,title:string},anchor_text:string,confidence_score:int}>
     */
    private function highConfidenceUnusedSuggestions(Collection $subjects): array
    {
        return ArticleLinkSuggestion::proposed()
            ->whereIn('source_article_id', $subjects->pluck('id'))
            ->where('confidence_score', '>=', self::HIGH_CONFIDENCE_THRESHOLD)
            ->with([
                'sourceArticle:id,title,status,published_at',
                'targetArticle:id,title,status,published_at',
            ])
            ->orderByDesc('confidence_score')
            ->get()
            ->filter(fn (ArticleLinkSuggestion $s) => $s->sourceArticle !== null
                && $s->targetArticle !== null
                && $this->temporalEligibility->isTargetSafeForSource($s->sourceArticle, $s->targetArticle))
            ->take(self::MAX_TOP_OPPORTUNITIES)
            ->map(fn (ArticleLinkSuggestion $s) => [
                'id' => $s->id,
                'source' => ['id' => $s->sourceArticle->id, 'title' => $s->sourceArticle->title],
                'target' => ['id' => $s->targetArticle->id, 'title' => $s->targetArticle->title],
                'anchor_text' => $s->anchor_text,
                'confidence_score' => $s->confidence_score,
            ])
            ->values()
            ->all();
    }
}
