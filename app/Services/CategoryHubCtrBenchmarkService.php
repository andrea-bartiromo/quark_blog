<?php

namespace App\Services;

use App\Models\ArticleView;
use App\Models\Category;
use App\Models\CategoryHubImpression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Cantiere 53 (programma "100 cantieri Kairus", dipende dai Cantieri 49-50).
 *
 * Ispezione diretta prima di questo cantiere: ArticleController::category()
 * non registra nessuna impression — a differenza di ArticleController::show(),
 * che già scrive una riga per-pageview in article_views (incluso il campo
 * `referer`, vedi ArticleViewTrackingService::recordView()). Il gap reale
 * non è quindi il lato "click" (arriva già gratis dai referer già loggati
 * per ogni view articolo), ma il lato "impression": senza un conteggio di
 * quante volte una pagina hub categoria è stata vista, un CTR (click / vista)
 * non è calcolabile — solo un conteggio grezzo di click, senza denominatore.
 *
 * Stesso principio della "second read" (Growth S2, ContinuationAnalyticsService):
 * minimale rispetto al funnel completo, nessun identificativo di
 * visitatore/sessione persistito, deduplicazione via sessione Laravel,
 * traffico interno escluso riusando ArticleViewTrackingService::shouldCountRequest()
 * (stessa definizione, mai una seconda da mantenere allineata a mano),
 * fail-open: un fallimento di scrittura qui non deve mai impedire la
 * navigazione pubblica della pagina categoria.
 *
 * Il lato "click-through" è dedotto dal referer già presente in
 * article_views, non da un nuovo endpoint di tracking dedicato — stessa
 * scelta di scope già motivata in ContinuationAnalyticsService per il click
 * del funnel "Continua da qui" (costo/rischio di un endpoint POST dedicato
 * non giustificato quando il dato utile è già raccolto altrove).
 */
class CategoryHubCtrBenchmarkService
{
    public function __construct(
        private readonly ArticleViewTrackingService $viewTracking
    ) {}

    public function recordImpression(string $slug): void
    {
        if (! $this->viewTracking->shouldCountRequest()) {
            return;
        }

        $sessionKey = 'category_hub_impression_'.$slug;

        if (session()->has($sessionKey)) {
            return;
        }

        try {
            CategoryHubImpression::create(['category_slug' => $slug]);

            session()->put($sessionKey, true);
        } catch (\Throwable $exception) {
            Log::warning('CategoryHubCtrBenchmarkService: scrittura impression fallita, la navigazione pubblica non è stata bloccata.', [
                'category_slug' => $slug,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{impressions:int,click_throughs:int,ctr:float}
     */
    public function benchmarkFor(string $slug, ?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): array
    {
        $impressions = CategoryHubImpression::query()
            ->where('category_slug', $slug)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->count();

        $clickThroughs = $this->clickThroughQuery($slug, $since, $until)->count();

        return [
            'impressions' => $impressions,
            'click_throughs' => $clickThroughs,
            'ctr' => $impressions > 0 ? round($clickThroughs / $impressions, 4) : 0.0,
        ];
    }

    /**
     * Riepilogo per ogni hub categoria pubblicamente raggiungibile
     * (Category::publicOptions(), include il fallback legacy solo-config),
     * ordinato per click-through decrescenti — mai limitato a chi ha già
     * un'impression registrata: una categoria appena pubblicata deve
     * comunque comparire con zero/zero, non sparire dal riepilogo.
     *
     * @return Collection<int, array{slug:string,name:string,impressions:int,click_throughs:int,ctr:float}>
     */
    public function hubBreakdown(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): Collection
    {
        return collect(Category::publicOptions())
            ->map(function (string $name, string $slug) use ($since, $until) {
                $benchmark = $this->benchmarkFor($slug, $since, $until);

                return array_merge(['slug' => $slug, 'name' => $name], $benchmark);
            })
            ->values()
            ->sortByDesc('click_throughs')
            ->values();
    }

    /**
     * Totali sitewide nel periodo indicato — MAI sommare hubBreakdown() per
     * ottenere questo numero: quella lista è vincolata alle sole categorie
     * pubblicamente raggiungibili ORA, mentre le impression/click già
     * registrati per una categoria nel frattempo disattivata resterebbero
     * fuori da quella somma pur essendo eventi reali avvenuti nel periodo.
     *
     * @return array{impressions:int,click_throughs:int,ctr:float}
     */
    public function siteWideTotals(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): array
    {
        $impressions = CategoryHubImpression::query()
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->count();

        $clickThroughs = $this->clickThroughQuery(null, $since, $until)->count();

        return [
            'impressions' => $impressions,
            'click_throughs' => $clickThroughs,
            'ctr' => $impressions > 0 ? round($clickThroughs / $impressions, 4) : 0.0,
        ];
    }

    /**
     * Un click-through è una riga article_views il cui referer termina
     * esattamente con /categoria/{slug} (query string di paginazione
     * ammessa dopo un `?`). Un semplice LIKE '%/categoria/{slug}%' senza
     * l'ancoraggio di fine stringa/query darebbe falsi positivi su una
     * categoria il cui slug è prefisso di un'altra (es. "energia" dentro
     * "/categoria/energia-rinnovabile"). $slug è escapato per i caratteri
     * speciali di LIKE (% e _) prima di comporre il pattern.
     */
    private function clickThroughQuery(?string $slug, ?\DateTimeInterface $since, ?\DateTimeInterface $until)
    {
        $query = ArticleView::query()->whereNotNull('referer');

        if ($slug !== null) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $slug);

            $query->where(function ($query) use ($escaped) {
                $query->where('referer', 'like', '%/categoria/'.$escaped)
                    ->orWhere('referer', 'like', '%/categoria/'.$escaped.'?%');
            });
        } else {
            $query->where('referer', 'like', '%/categoria/%');
        }

        return $query
            ->when($since, fn ($query) => $query->where('viewed_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('viewed_at', '<=', $until));
    }
}
