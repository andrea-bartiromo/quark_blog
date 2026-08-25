<?php

namespace App\Services\EditorialQuality;

use App\Models\Article;

/**
 * Audit SEO editoriale read-only. Riusa EditorialQualityChecker per le
 * policy di lunghezza/indexability già formalizzate e aggiunge soltanto
 * diagnosi mancanti; non genera né salva copy SEO.
 */
class SeoMetadataQualityAuditService
{
    public function __construct(private readonly EditorialQualityChecker $qualityChecker) {}

    /** @return array<string, mixed> */
    public function audit(): array
    {
        $articles = Article::query()->with('author:id')->orderBy('id')->get();

        $duplicateArticleTitleIndex = $articles
            ->pluck('title')
            ->map(fn (?string $value) => $this->normalize($value))
            ->countBy()
            ->all();

        $effectiveTitleCounts = $articles
            ->map(fn (Article $article) => $this->normalize($article->metaTitle()))
            ->filter()
            ->countBy();

        $effectiveDescriptionCounts = $articles
            ->map(fn (Article $article) => $this->normalize($article->metaDescription()))
            ->filter()
            ->countBy();

        // Missione 38 (Fase E — Editorial Quality & Readiness): il
        // canonical di fallback è la route dello slug proprio dell'articolo
        // (sempre unico grazie al vincolo UNIQUE su articles.slug), quindi
        // un duplicato può nascere SOLO da un override esplicito che
        // collide con un altro articolo — un conflitto di canonicalizzazione
        // reale, non un falso positivo.
        $effectiveCanonicalCounts = $articles
            ->map(fn (Article $article) => $this->normalize($article->metaCanonicalUrl()))
            ->filter()
            ->countBy();

        $rows = $articles->map(function (Article $article) use ($duplicateArticleTitleIndex, $effectiveTitleCounts, $effectiveDescriptionCounts, $effectiveCanonicalCounts) {
            $quality = $this->qualityChecker->check($article, $duplicateArticleTitleIndex);
            $seoChecks = array_values(array_map(
                fn (EditorialQualityCheckResult $result) => [
                    'code' => $result->code,
                    'status' => $result->status,
                    'message' => $result->message,
                ],
                array_filter($quality->results, fn (EditorialQualityCheckResult $result) => $result->category === EditorialQualityCheckResult::CATEGORY_SEO)
            ));

            $effectiveTitle = $this->normalize($article->metaTitle());
            $effectiveDescription = $this->normalize($article->metaDescription());
            $effectiveCanonical = $this->normalize($article->metaCanonicalUrl());

            return [
                'article_id' => $article->id,
                'slug' => $article->slug,
                'status' => $article->status,
                'existing_quality_checks' => $seoChecks,
                'uses_seo_title_fallback' => blank($article->seo_title),
                'uses_seo_description_fallback' => blank($article->seo_description),
                'duplicate_effective_title' => $effectiveTitle !== '' && ($effectiveTitleCounts[$effectiveTitle] ?? 0) > 1,
                'duplicate_effective_description' => $effectiveDescription !== '' && ($effectiveDescriptionCounts[$effectiveDescription] ?? 0) > 1,
                'duplicate_canonical_url' => $effectiveCanonical !== '' && ($effectiveCanonicalCounts[$effectiveCanonical] ?? 0) > 1,
                'canonical' => $this->canonicalCheck($article),
                'social_metadata' => $this->socialMetadataCheck($article),
            ];
        })->values();

        return [
            'articles' => $rows->all(),
            'summary' => [
                'analyzed' => $rows->count(),
                'duplicate_effective_titles' => $rows->where('duplicate_effective_title', true)->count(),
                'duplicate_effective_descriptions' => $rows->where('duplicate_effective_description', true)->count(),
                'duplicate_canonical_urls' => $rows->where('duplicate_canonical_url', true)->count(),
                'canonical_warnings' => $rows->filter(fn (array $row) => $row['canonical']['status'] === 'WARNING')->count(),
            ],
            'policy_notes' => [
                'short_seo_title_threshold' => 'NOT_DEFINED_IN_REPOSITORY',
                'scheduled_surface_leakage' => 'REQUIRES_ROUTE_RENDERING_REGRESSION_TESTS',
            ],
        ];
    }

    /** @return array{status:string,reason:string,url:string} */
    private function canonicalCheck(Article $article): array
    {
        $url = trim($article->metaCanonicalUrl());
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($url === '' || ! in_array($scheme, ['http', 'https'], true) || $host === null) {
            return ['status' => 'WARNING', 'reason' => 'Canonical effettivo non è un URL HTTP(S) assoluto valido.', 'url' => $url];
        }

        if (parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
            return ['status' => 'WARNING', 'reason' => 'Canonical effettivo contiene query string o fragment.', 'url' => $url];
        }

        // Missione 38 (Fase E — Editorial Quality & Readiness): un canonical
        // override che punta a un dominio diverso dal sito stesso dice ai
        // motori di ricerca "il contenuto vero vive altrove" — de-indicizza
        // di fatto la pagina reale. Stesso confronto host case-insensitive
        // già usato da ArticleLinkInsertionService::isSafeInternalUrl().
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($appHost !== null && mb_strtolower($host) !== mb_strtolower($appHost)) {
            return ['status' => 'WARNING', 'reason' => 'Canonical effettivo punta a un dominio diverso dal sito.', 'url' => $url];
        }

        return ['status' => 'OK', 'reason' => 'Canonical effettivo sintatticamente valido.', 'url' => $url];
    }

    /** @return array<string, mixed> */
    private function socialMetadataCheck(Article $article): array
    {
        return [
            'og_title_present' => trim($article->metaOgTitle()) !== '',
            'og_description_present' => trim($article->metaOgDescription()) !== '',
            'og_image_present' => trim($article->metaOgImage()) !== '',
            'twitter_title_present' => trim($article->metaTwitterTitle()) !== '',
            'twitter_description_present' => trim($article->metaTwitterDescription()) !== '',
            'twitter_image_present' => trim($article->metaTwitterImage()) !== '',
        ];
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''), 'UTF-8');
    }
}
