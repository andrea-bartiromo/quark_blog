<?php

namespace App\Services\SearchConsole;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\PublicPages\InProcessPageFetcher;
use App\Services\PublicPages\PublicPageInventory;

/**
 * Risolve soltanto l'eleggibilità pubblica corrente di un URL importato.
 * Non esegue richieste HTTP e non interpreta mai un record non pubblico come
 * una pagina da indicizzare.
 */
class SearchConsoleCoverageUrlEligibility
{
    private ?string $sitemap = null;

    public function __construct(
        private readonly PublicPageInventory $inventory,
        private readonly InProcessPageFetcher $fetcher,
    ) {}

    public function status(?string $url): string
    {
        return $this->audit($url)['visibility'];
    }

    /**
     * Audit locale, senza rete: gli URL esterni o ambigui non vengono mai
     * richiesti. I fatti raccolti sono informativi, non innescano correzioni.
     *
     * @return array{visibility:string,http_status:int|null,canonical:?string,robots:?string,in_sitemap:?bool}
     */
    public function audit(?string $url): array
    {
        $empty = ['visibility' => 'unknown', 'http_status' => null, 'canonical' => null, 'robots' => null, 'in_sitemap' => null];
        if ($url === null || $url === '') return $empty;
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);
        if ($urlHost !== null && $appHost !== null && strcasecmp($urlHost, $appHost) !== 0) return $empty;
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $knownStatic = collect($this->inventory->pages())->pluck('sample_url')->filter()->map(fn ($value) => '/'.ltrim((string) parse_url($value, PHP_URL_PATH), '/'));
        $visibility = $knownStatic->contains($path) ? 'public' : 'unknown';

        if (preg_match('#^/articolo/([^/]+)$#', $path, $matches) === 1) {
            $visibility = Article::query()->published()->where('slug', $matches[1])->exists() ? 'public' : 'not_public';
        }
        if (preg_match('#^/categoria/([^/]+)$#', $path, $matches) === 1) {
            $visibility = array_key_exists($matches[1], Category::publicOptions()) ? 'public' : 'not_public';
        }
        if (preg_match('#^/percorsi/([^/]+)$#', $path, $matches) === 1) {
            $visibility = ContentCluster::query()->publiclyVisible()->where('slug', $matches[1])->exists() ? 'public' : 'not_public';
        }
        if (preg_match('#^/autore/(\d+)$#', $path, $matches) === 1) {
            $visibility = User::query()->whereKey($matches[1])->whereHas('articles', fn ($query) => $query->published())->exists() ? 'public' : 'not_public';
        }
        if ($visibility !== 'public') return [...$empty, 'visibility' => $visibility];

        $response = $this->fetcher->fetch($url);
        $html = (string) $response->getContent();
        $canonical = preg_match('#<link rel="canonical" href="(.*?)">#', $html, $matches) === 1 ? $matches[1] : null;
        $robots = preg_match('#<meta name="robots" content="(.*?)">#', $html, $matches) === 1 ? $matches[1] : null;
        $sitemap = $this->sitemap ??= (string) $this->fetcher->fetch(route('sitemap'))->getContent();

        return ['visibility' => 'public', 'http_status' => $response->getStatusCode(), 'canonical' => $canonical, 'robots' => $robots, 'in_sitemap' => str_contains($sitemap, '<loc>'.htmlspecialchars($url, ENT_XML1).'</loc>')];
    }
}
