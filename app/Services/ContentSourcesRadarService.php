<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Collection;

/**
 * Cantiere 84 (programma "100 cantieri Kairus"): "radar fonti interno"
 * — a differenza dell'audit già esistente su un singolo articolo
 * (EditorialQualityChecker::sourcesCheck(), Cantiere 33/34: verifica se
 * QUELL'articolo ha una sezione Fonti coerente), questo servizio guarda
 * l'INSIEME degli articoli pubblicati e riporta il profilo aggregato
 * delle fonti citate sul sito: quanti articoli citano almeno una fonte,
 * quali domini ricorrono più spesso, quanti articoli non citano nessuna
 * fonte. Sola lettura, nessuna scrittura, nessuna soglia che blocca
 * nulla — solo un segnale editoriale per un umano.
 *
 * Riusa ArticlePrimarySourcesParser (già esistente, già testato) per
 * interpretare Article::primary_sources: non reinterpreta né duplica
 * quella logica di classificazione riga-per-riga.
 */
class ContentSourcesRadarService
{
    public function __construct(
        private readonly ArticlePrimarySourcesParser $parser,
    ) {}

    /**
     * @return array{
     *     total_published_articles: int,
     *     articles_with_sources: int,
     *     articles_without_sources: int,
     *     articles_with_only_text_sources: int,
     *     distinct_domains: int,
     *     top_domains: array<int, array{domain: string, article_count: int}>,
     * }
     */
    public function report(): array
    {
        $articles = Article::published()->get(['id', 'primary_sources']);

        $articlesWithSources = 0;
        $articlesWithoutSources = 0;
        $articlesWithOnlyTextSources = 0;

        /** @var Collection<string, int> $domainArticleCounts */
        $domainArticleCounts = collect();

        foreach ($articles as $article) {
            $items = $this->parser->parse($article->primary_sources);

            if ($items === []) {
                $articlesWithoutSources++;

                continue;
            }

            $articlesWithSources++;

            // Domini deduplicati PER ARTICOLO prima di contare: un
            // articolo che cita lo stesso dominio tre volte non deve
            // pesare tre volte quanto tre articoli che lo citano una
            // volta ciascuno — il radar misura quanti articoli
            // DISTINTI si appoggiano a un dominio, non il numero grezzo
            // di righe, altrimenti un solo articolo con citazioni
            // ripetute distorcerebbe la classifica.
            $domainsInThisArticle = collect($items)
                ->filter(fn (array $item) => $item['type'] === 'link' && $item['url'] !== null)
                ->map(fn (array $item) => $this->normalizedDomain($item['url']))
                ->filter()
                ->unique();

            if ($domainsInThisArticle->isEmpty()) {
                $articlesWithOnlyTextSources++;
            }

            foreach ($domainsInThisArticle as $domain) {
                $domainArticleCounts->put($domain, ($domainArticleCounts->get($domain, 0)) + 1);
            }
        }

        $topDomains = $domainArticleCounts
            ->sortDesc()
            ->take(20)
            ->map(fn (int $count, string $domain) => ['domain' => $domain, 'article_count' => $count])
            ->values()
            ->all();

        return [
            'total_published_articles' => $articles->count(),
            'articles_with_sources' => $articlesWithSources,
            'articles_without_sources' => $articlesWithoutSources,
            'articles_with_only_text_sources' => $articlesWithOnlyTextSources,
            'distinct_domains' => $domainArticleCounts->count(),
            'top_domains' => $topDomains,
        ];
    }

    /**
     * Normalizza un host per il conteggio: minuscolo, senza il prefisso
     * "www." (altrimenti "www.nature.com" e "nature.com" verrebbero
     * contati come due domini distinti). Un URL malformato (già escluso
     * a monte da ArticlePrimarySourcesParser, ma qui per difesa) produce
     * host null, filtrato dal chiamante.
     */
    private function normalizedDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = mb_strtolower($host, 'UTF-8');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
