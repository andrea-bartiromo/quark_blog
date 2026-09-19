<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryHubEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Cantiere 53 (programma "100 cantieri Kairus", dipende dai Cantieri 49-50).
 *
 * Ispezione diretta prima di questo cantiere: ArticleController::category()
 * non registra nessuna impression, e nessun punto del sito misura quanti
 * visitatori di una pagina hub categoria proseguono verso un articolo — un
 * CTR (click/vista) non era calcolabile.
 *
 * Impression e click-through sono due eventi ESPLICITI e SIMMETRICI (stessa
 * granularità: una sola volta per categoria per sessione, indipendentemente
 * da quante volte la pagina viene ricaricata o quanti articoli distinti
 * vengono aperti dopo). Una prima versione di questo servizio deduceva il
 * click-through dal campo `referer` già presente in article_views — Codex
 * (PR #638) ha segnalato correttamente che questo produceva un CTR privo di
 * senso (un visitatore che apre due articoli diversi dalla stessa visita
 * contava due click-through contro una sola impression, arrivando anche
 * oltre il 100%) e che l'audit read-only RedirectAndCanonicalIntegrityAudit
 * (che visita /categoria/{slug} in-process via InProcessPageFetcher, header
 * X-Kairus-Internal-Audit) avrebbe gonfiato silenziosamente le impression a
 * ogni sua esecuzione. Corretto registrando entrambi i lati come eventi
 * propri, con lo stesso meccanismo di deduplicazione ed esclusione usato
 * ovunque nel progetto per questo genere di segnale (Growth S2,
 * ContinuationAnalyticsService): nessun identificativo di
 * visitatore/sessione persistito, deduplicazione via sessione Laravel,
 * traffico interno escluso riusando
 * ArticleViewTrackingService::shouldCountRequest(), audit interno escluso
 * controllando l'header X-Kairus-Internal-Audit (stesso controllo già
 * applicato in ArticleController::show() per le analytics articolo), fail
 * -open: un fallimento di scrittura qui non deve mai impedire la
 * navigazione pubblica.
 */
class CategoryHubCtrBenchmarkService
{
    public function __construct(
        private readonly ArticleViewTrackingService $viewTracking
    ) {}

    public function recordImpression(string $slug, bool $isInternalAudit = false): void
    {
        $this->recordOnce(CategoryHubEvent::EVENT_IMPRESSION, $slug, $isInternalAudit);
    }

    public function recordClickThrough(string $slug, bool $isInternalAudit = false): void
    {
        $this->recordOnce(CategoryHubEvent::EVENT_CLICK_THROUGH, $slug, $isInternalAudit);
    }

    /**
     * Estrae lo slug categoria dal referer della richiesta corrente, se e
     * solo se il referer punta ESATTAMENTE a /categoria/{slug} di questo
     * stesso sito (un solo segmento di path dopo /categoria/, nessun
     * sottopercorso) — mai un confronto per sottostringa: uno slug che è
     * prefisso di un altro (es. "energia" dentro "energia-rinnovabile")
     * non deve mai generare un falso match.
     */
    public function resolveCategoryHubSlugFromReferer(?string $refererUrl): ?string
    {
        if (blank($refererUrl)) {
            return null;
        }

        $path = parse_url($refererUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('~/categoria/([^/]+)$~', rtrim($path, '/'), $matches) !== 1) {
            return null;
        }

        return rawurldecode($matches[1]);
    }

    private function recordOnce(string $eventType, string $slug, bool $isInternalAudit): void
    {
        if ($isInternalAudit || ! $this->viewTracking->shouldCountRequest()) {
            return;
        }

        $sessionKey = 'category_hub_'.$eventType.'_'.$slug;

        if (session()->has($sessionKey)) {
            return;
        }

        try {
            CategoryHubEvent::create(['event_type' => $eventType, 'category_slug' => $slug]);

            session()->put($sessionKey, true);
        } catch (\Throwable $exception) {
            Log::warning('CategoryHubCtrBenchmarkService: scrittura evento fallita, la navigazione pubblica non è stata bloccata.', [
                'event_type' => $eventType,
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
        $impressions = $this->countFor(CategoryHubEvent::EVENT_IMPRESSION, $slug, $since, $until);
        $clickThroughs = $this->countFor(CategoryHubEvent::EVENT_CLICK_THROUGH, $slug, $since, $until);

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
     * Una sola query di aggregazione (GROUP BY categoria+tipo evento), non
     * una query per categoria: bounded per costruzione, indipendentemente
     * da quanti eventi storici esistano — finding Codex (PR #638), stesso
     * pattern già in uso in ContinuationAnalyticsService::articleBreakdown().
     *
     * @return Collection<int, array{slug:string,name:string,impressions:int,click_throughs:int,ctr:float}>
     */
    public function hubBreakdown(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): Collection
    {
        $counts = CategoryHubEvent::query()
            ->selectRaw('category_slug, event_type, COUNT(*) as total')
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->groupBy('category_slug', 'event_type')
            ->get()
            ->groupBy('category_slug');

        return collect(Category::publicOptions())
            ->map(function (string $name, string $slug) use ($counts) {
                $eventRows = $counts->get($slug, collect());
                $impressions = (int) ($eventRows->firstWhere('event_type', CategoryHubEvent::EVENT_IMPRESSION)->total ?? 0);
                $clickThroughs = (int) ($eventRows->firstWhere('event_type', CategoryHubEvent::EVENT_CLICK_THROUGH)->total ?? 0);

                return [
                    'slug' => $slug,
                    'name' => $name,
                    'impressions' => $impressions,
                    'click_throughs' => $clickThroughs,
                    'ctr' => $impressions > 0 ? round($clickThroughs / $impressions, 4) : 0.0,
                ];
            })
            ->values()
            ->sortByDesc('click_throughs')
            ->values();
    }

    /**
     * Totali sitewide nel periodo indicato — MAI sommare hubBreakdown() per
     * ottenere questo numero: quella lista è vincolata alle sole categorie
     * pubblicamente raggiungibili ORA, mentre gli eventi già registrati per
     * una categoria nel frattempo disattivata resterebbero fuori da quella
     * somma pur essendo eventi reali avvenuti nel periodo.
     *
     * @return array{impressions:int,click_throughs:int,ctr:float}
     */
    public function siteWideTotals(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): array
    {
        $impressions = $this->countFor(CategoryHubEvent::EVENT_IMPRESSION, null, $since, $until);
        $clickThroughs = $this->countFor(CategoryHubEvent::EVENT_CLICK_THROUGH, null, $since, $until);

        return [
            'impressions' => $impressions,
            'click_throughs' => $clickThroughs,
            'ctr' => $impressions > 0 ? round($clickThroughs / $impressions, 4) : 0.0,
        ];
    }

    private function countFor(string $eventType, ?string $slug, ?\DateTimeInterface $since, ?\DateTimeInterface $until): int
    {
        return CategoryHubEvent::query()
            ->where('event_type', $eventType)
            ->when($slug !== null, fn ($query) => $query->where('category_slug', $slug))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->count();
    }
}
