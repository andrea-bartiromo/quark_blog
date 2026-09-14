<?php

namespace App\Services;

use App\Models\Article;
use App\Services\EditorialQuality\EditorialQualityChecker;
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
 *
 * Le "fonti" di un articolo non vivono solo nel campo dedicato
 * primary_sources — il caso reale più comune è una sezione "Fonti"
 * scritta a mano nel corpo (heading, o testo libero dopo "---"), già
 * riconosciuta da sourcesCheck() (Codex, PR #609): senza replicare
 * anche questi due casi qui, il radar sottostimava sistematicamente
 * "senza fonti". Inoltre, quando esiste una sezione manuale a heading,
 * resources/views/articolo.blade.php SOPPRIME il pannello pubblico di
 * primary_sources (@unless($hasManualSourcesSection)) — quindi in quel
 * caso i suoi URL, anche se compilati, non sono mai visibili al
 * lettore e non devono contribuire al conteggio domini.
 */
class ContentSourcesRadarService
{
    public function __construct(
        private readonly ArticlePrimarySourcesParser $parser,
        private readonly ArticleManualSourcesDetector $manualSourcesDetector,
        private readonly EditorialQualityChecker $qualityChecker,
    ) {}

    /**
     * @return array{
     *     total_published_articles: int,
     *     articles_with_sources: int,
     *     articles_without_sources: int,
     *     articles_with_only_text_sources: int,
     *     articles_with_body_only_sources: int,
     *     distinct_domains: int,
     *     top_domains: array<int, array{domain: string, article_count: int}>,
     * }
     */
    public function report(): array
    {
        $articles = Article::published()->get(['id', 'primary_sources', 'body']);

        $articlesWithSources = 0;
        $articlesWithoutSources = 0;
        $articlesWithOnlyTextSources = 0;
        $articlesWithBodyOnlySources = 0;

        /** @var Collection<string, int> $domainArticleCounts */
        $domainArticleCounts = collect();

        foreach ($articles as $article) {
            // Priorità 1: una sezione Fonti manuale a heading nel corpo
            // sopprime pubblicamente il pannello di primary_sources —
            // stesso identico segnale usato da articolo.blade.php,
            // mai i suoi URL (anche se il campo è compilato) devono
            // contribuire al conteggio domini qui.
            if ($this->manualSourcesDetector->hasManualSourcesSection($article->body)) {
                $articlesWithSources++;
                $articlesWithBodyOnlySources++;

                continue;
            }

            $items = $this->parser->parse($article->primary_sources);

            if ($items !== []) {
                $articlesWithSources++;

                // Domini deduplicati PER ARTICOLO prima di contare: un
                // articolo che cita lo stesso dominio tre volte non deve
                // pesare tre volte quanto tre articoli che lo citano una
                // volta ciascuno — il radar misura quanti articoli
                // DISTINTI si appoggiano a un dominio, non il numero
                // grezzo di righe, altrimenti un solo articolo con
                // citazioni ripetute distorcerebbe la classifica.
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

                continue;
            }

            // Priorità 3 (stessa di sourcesCheck()): primary_sources
            // vuoto, nessuna heading manuale — resta il caso legacy del
            // testo libero dopo "---" nel corpo.
            if ($this->qualityChecker->hasDelimitedSourcesSection((string) $article->body)) {
                $articlesWithSources++;
                $articlesWithBodyOnlySources++;

                continue;
            }

            $articlesWithoutSources++;
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
            'articles_with_body_only_sources' => $articlesWithBodyOnlySources,
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
