<?php

namespace App\Services\OrganicDiscovery;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Services\ArticleRevisionTransparencyService;
use App\Services\Discovery\ArticleDiscoveryAuditService;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\EditorialQuality\EditorialQualityReport;
use App\Services\EditorialQuality\SeoMetadataQualityAuditService;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use Illuminate\Support\Collection;

/**
 * Cantiere 3 (programma "Kairus Organic Discovery"). Per ogni articolo
 * pubblico, uno stato spiegabile — bloccato/da migliorare/pronto/misurato
 * — MAI ricalcolando le regole già espresse altrove: compone
 * ArticleDiscoveryAuditService (percorsi/collegamenti in entrata),
 * SeoMetadataQualityAuditService (SEO/canonical), EditorialQualityChecker
 * (qualità complessiva), ArticleRevisionTransparencyService (freschezza),
 * il profilo di ricerca del Cantiere 2 e i dati Search Console del
 * Cantiere 1 — mai un punteggio opaco, ogni finding ha una causa e
 * un'azione suggerita (vedi describeFinding()).
 *
 * "misurato" è raggiungibile SOLO da "pronto" (nessun altro finding) più
 * almeno una riga Search Console reale per l'articolo nell'ultimo periodo
 * importato: prova di scoperta organica osservata, non solo teorica.
 *
 * Il confronto più approfondito canonical/robots/sitemap
 * (SearchConsoleCoverageUrlEligibility, Cantiere 6) è deliberatamente
 * ESCLUSO da auditAll() — richiede una richiesta in-process per articolo,
 * troppo costosa per l'intero corpus a ogni apertura della pagina
 * aggregata — ed è invece disponibile solo per l'articolo aperto nella
 * pagina di dettaglio (vedi Admin\OrganicDiscoveryReadinessController::show()).
 */
class OrganicDiscoveryReadinessService
{
    public const STATE_BLOCKED = 'blocked';

    public const STATE_NEEDS_WORK = 'needs_work';

    public const STATE_READY = 'ready';

    public const STATE_MEASURED = 'measured';

    public function __construct(
        private readonly ArticleDiscoveryAuditService $discoveryAudit,
        private readonly SeoMetadataQualityAuditService $seoAudit,
        private readonly EditorialQualityChecker $qualityChecker,
        private readonly ArticleRevisionTransparencyService $revisionTransparency,
        private readonly SearchConsoleFreshnessService $searchConsoleFreshness,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function auditAll(): Collection
    {
        $discoveryRows = $this->discoveryAudit->audit()->keyBy('article_id');

        $seoRows = collect($this->seoAudit->audit()['articles'])->keyBy('article_id');

        // author:id eager-caricato per lo stesso motivo di
        // EditorialQualityAuditService/SeoMetadataQualityAuditService:
        // EditorialQualityChecker::authorCheck() legge $article->author,
        // altrimenti una query per articolo.
        $articles = Article::query()
            ->published()
            ->with(['searchProfile', 'author:id'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        // Stesso indice precalcolato una sola volta e passato a check(),
        // mai una query per articolo — stesso pattern di
        // EditorialQualityAuditService/SeoMetadataQualityAuditService.
        $duplicateTitleIndex = Article::query()
            ->pluck('title')
            ->map(fn (?string $title) => mb_strtolower(trim((string) $title), 'UTF-8'))
            ->countBy()
            ->all();

        $articleIdsWithSearchConsoleData = $this->articleIdsWithLatestSearchConsoleData();

        // Una sola query per l'intero corpus (mai una per articolo): vedi
        // il docblock di ArticleRevisionTransparencyService::lastEditorialUpdates().
        $articleIdsWithFreshnessUpdate = $this->revisionTransparency->lastEditorialUpdates($articles);

        return $articles->map(function (Article $article) use (
            $discoveryRows,
            $seoRows,
            $duplicateTitleIndex,
            $articleIdsWithSearchConsoleData,
            $articleIdsWithFreshnessUpdate,
        ): array {
            $discoveryRow = $discoveryRows->get($article->id);
            $seoRow = $seoRows->get($article->id);
            $qualityReport = $this->qualityChecker->check($article, $duplicateTitleIndex);
            $hasSearchConsoleData = $articleIdsWithSearchConsoleData->has($article->id);
            $hasFreshnessSignal = $article->searchProfile?->last_editorial_review_at !== null
                || $articleIdsWithFreshnessUpdate->has($article->id);

            $findings = $this->collectFindings($article, $discoveryRow, $seoRow, $qualityReport, $hasFreshnessSignal);
            $state = $this->resolveState($findings, $hasSearchConsoleData);

            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'state' => $state,
                'state_label' => self::stateLabel($state),
                'findings' => $findings,
                'findings_detail' => array_map(fn (string $code) => [
                    'code' => $code,
                    ...self::describeFinding($code),
                ], $findings),
                'has_search_console_data' => $hasSearchConsoleData,
                'discovery_class' => $discoveryRow['discovery_class'] ?? null,
                'quality_level' => $qualityReport->levelLabel(),
                // Nessun controllo programmatico affidabile per la presenza
                // di dati strutturati senza ri-renderizzare la vista
                // pubblica dell'articolo (verificato in fase di ispezione):
                // dichiarato esplicitamente non misurato, mai un booleano
                // indovinato — stesso principio di "not_measured" già in
                // ArticleDiscoveryAuditService/SearchConsoleBaselineReportService.
                'not_measured' => ['structured_data_presence'],
            ];
        })->values();
    }

    /** @return array<string, mixed>|null */
    public function forArticle(Article $article): ?array
    {
        return $this->auditAll()->firstWhere('article_id', $article->id);
    }

    /**
     * Anteprima leggera pre-pubblicazione per UN singolo articolo (bozza o
     * pubblicato), pensata per l'inclusione diretta nella pagina di
     * modifica — MAI l'intero auditAll() del corpus: ArticleDiscoveryAuditService
     * e SeoMetadataQualityAuditService sono deliberatamente pensati come
     * pagine standalone, eseguiti solo quando un editor le apre
     * esplicitamente (vedi i rispettivi docblock/controller), non ad ogni
     * apertura della pagina di modifica di un singolo articolo.
     *
     * Copre solo i controlli genuinamente economici per un solo articolo:
     * qualità editoriale (EditorialQualityChecker::check() supporta
     * esplicitamente questa modalità standalone, vedi il suo stesso
     * docblock sul parametro $duplicateTitleIndex), validità sintattica
     * del canonical (SeoMetadataQualityAuditService::canonicalCheck(), una
     * funzione pura sul singolo articolo), profilo di ricerca, freschezza
     * editoriale (una query, stesso costo già pagato da
     * ArticleController::show() sulla pagina pubblica). NON include
     * percorsi di scoperta interna, duplicati a livello di corpus o dati
     * Search Console — dichiarati esplicitamente non misurati in questo
     * contesto, mai un avviso indovinato. Solo un avviso non bloccante:
     * non impedisce mai il salvataggio o la pubblicazione.
     *
     * @return array<string, mixed>
     */
    public function previewForArticle(Article $article, EditorialQualityReport $qualityReport): array
    {
        $findings = collect();

        $level = $qualityReport->level();
        if ($level === EditorialQualityReport::LEVEL_INCOMPLETE) {
            $findings->push('QUALITY_INCOMPLETE');
        } elseif ($level === EditorialQualityReport::LEVEL_ATTENTION) {
            $findings->push('QUALITY_ATTENTION');
        }

        if ($this->seoAudit->canonicalCheck($article)['status'] === 'WARNING') {
            $findings->push('SEO_CANONICAL_WARNING');
        }

        $searchProfile = $article->searchProfile;
        if ($searchProfile === null) {
            $findings->push('SEARCH_PROFILE_MISSING');
        } elseif (blank($searchProfile->primary_query)) {
            $findings->push('SEARCH_PROFILE_INCOMPLETE');
        }

        $hasFreshnessSignal = $searchProfile?->last_editorial_review_at !== null
            || $this->revisionTransparency->lastEditorialUpdate($article) !== null;
        if (! $hasFreshnessSignal) {
            $findings->push('FRESHNESS_NOT_TRACKED');
        }

        $findings = $findings->unique()->values()->all();

        return [
            'findings' => $findings,
            'findings_detail' => array_map(fn (string $code) => [
                'code' => $code,
                ...self::describeFinding($code),
            ], $findings),
            'not_measured' => ['internal_discovery_paths', 'corpus_duplicate_metadata', 'structured_data_presence', 'search_console_data'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $discoveryRow
     * @param  array<string, mixed>|null  $seoRow
     * @return list<string>
     */
    private function collectFindings(
        Article $article,
        ?array $discoveryRow,
        ?array $seoRow,
        EditorialQualityReport $qualityReport,
        bool $hasFreshnessSignal,
    ): array {
        $findings = collect();

        $level = $qualityReport->level();
        if ($level === EditorialQualityReport::LEVEL_INCOMPLETE) {
            $findings->push('QUALITY_INCOMPLETE');
        } elseif ($level === EditorialQualityReport::LEVEL_ATTENTION) {
            $findings->push('QUALITY_ATTENTION');
        }

        if (($discoveryRow['discovery_class'] ?? null) === 'ZERO_PATHS') {
            $findings->push('ZERO_DISCOVERY_PATHS');
        }
        foreach ($discoveryRow['risks'] ?? [] as $risk) {
            $findings->push($risk);
        }

        if (($seoRow['canonical']['status'] ?? null) === 'WARNING') {
            $findings->push('SEO_CANONICAL_WARNING');
        }
        if (($seoRow['duplicate_effective_title'] ?? false) || ($seoRow['duplicate_effective_description'] ?? false) || ($seoRow['duplicate_canonical_url'] ?? false)) {
            $findings->push('DUPLICATE_METADATA');
        }

        $searchProfile = $article->searchProfile;
        if ($searchProfile === null) {
            $findings->push('SEARCH_PROFILE_MISSING');
        } elseif (blank($searchProfile->primary_query)) {
            $findings->push('SEARCH_PROFILE_INCOMPLETE');
        }

        if (! $hasFreshnessSignal) {
            $findings->push('FRESHNESS_NOT_TRACKED');
        }

        return $findings->unique()->values()->all();
    }

    /** @param  list<string>  $findings */
    private function resolveState(array $findings, bool $hasSearchConsoleData): string
    {
        if (in_array('QUALITY_INCOMPLETE', $findings, true) || in_array('ZERO_DISCOVERY_PATHS', $findings, true)) {
            return self::STATE_BLOCKED;
        }

        if ($findings === []) {
            return $hasSearchConsoleData ? self::STATE_MEASURED : self::STATE_READY;
        }

        return self::STATE_NEEDS_WORK;
    }

    /** @return Collection<int, int> chiavi = article_id, per un controllo O(1) con has() */
    private function articleIdsWithLatestSearchConsoleData(): Collection
    {
        $latestPeriod = $this->searchConsoleFreshness->availablePeriods()->first();

        if ($latestPeriod === null) {
            return collect();
        }

        return SearchConsoleQuery::query()
            ->whereDate('period_start', $latestPeriod['period_start'])
            ->whereDate('period_end', $latestPeriod['period_end'])
            ->whereNotNull('article_id')
            ->distinct()
            ->pluck('article_id')
            ->flip();
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_BLOCKED => 'Bloccato',
            self::STATE_NEEDS_WORK => 'Da migliorare',
            self::STATE_READY => 'Pronto',
            self::STATE_MEASURED => 'Misurato',
            default => $state,
        };
    }

    /** @return array{label:string,cause:string,action:string} */
    public static function describeFinding(string $code): array
    {
        return match ($code) {
            'QUALITY_INCOMPLETE' => [
                'label' => 'Qualità editoriale incompleta',
                'cause' => 'Almeno un controllo essenziale di EditorialQualityChecker è FAIL.',
                'action' => 'Aprire la pagina "Qualità editoriale" dell\'articolo e correggere i controlli essenziali segnalati.',
            ],
            'QUALITY_ATTENTION' => [
                'label' => 'Qualità editoriale da rivedere',
                'cause' => 'Almeno un controllo di qualità è in WARNING (nessun FAIL essenziale).',
                'action' => 'Rivedere i controlli in attenzione nella pagina "Qualità editoriale".',
            ],
            'ZERO_DISCOVERY_PATHS' => [
                'label' => 'Nessun percorso di scoperta interna',
                'cause' => 'L\'articolo non è raggiungibile da archivio, categoria, Percorso o collegamenti in entrata.',
                'action' => 'Aggiungere l\'articolo a una categoria pubblica o a un Percorso, oppure collegarlo dal corpo di un altro articolo pubblicato.',
            ],
            'NO_BODY_INCOMING_LINKS' => [
                'label' => 'Nessun collegamento interno in entrata',
                'cause' => 'Nessun articolo pubblicato collega questo articolo dal proprio corpo.',
                'action' => 'Collegare questo articolo dal corpo di almeno un altro articolo pubblicato pertinente.',
            ],
            'NO_ACTIVE_PATH' => [
                'label' => 'Nessun Percorso attivo',
                'cause' => 'L\'articolo non appartiene a nessun Percorso attualmente attivo.',
                'action' => 'Collegare l\'articolo a un Percorso pertinente e attivo.',
            ],
            'NO_CATEGORY_PATH' => [
                'label' => 'Nessuna categoria pubblica raggiungibile',
                'cause' => 'La categoria principale (e le secondarie) non sono pubblicamente navigabili.',
                'action' => 'Verificare che la categoria dell\'articolo sia pubblica e attiva.',
            ],
            'WEAK_DISCOVERY' => [
                'label' => 'Scoperta interna debole',
                'cause' => 'Nessuna categoria, nessun Percorso attivo e nessun collegamento in entrata: un solo percorso residuo (archivio/autore).',
                'action' => 'Rafforzare almeno uno tra categoria, Percorso o collegamenti interni in entrata.',
            ],
            'SEO_CANONICAL_WARNING' => [
                'label' => 'Canonical non valida',
                'cause' => 'Il canonical effettivo non è un URL assoluto valido, contiene query/fragment, o punta a un altro dominio.',
                'action' => 'Correggere il campo canonical nella sezione SEO dell\'articolo.',
            ],
            'DUPLICATE_METADATA' => [
                'label' => 'Metadati duplicati',
                'cause' => 'Titolo effettivo, descrizione effettiva o canonical coincidono con quelli di un altro articolo.',
                'action' => 'Rendere univoci titolo/descrizione/canonical rispetto agli altri articoli.',
            ],
            'SEARCH_PROFILE_MISSING' => [
                'label' => 'Profilo di ricerca assente',
                'cause' => 'Nessun profilo di ricerca editoriale (Cantiere 2) è stato compilato per questo articolo.',
                'action' => 'Compilare almeno la query primaria nella sezione "Profilo di ricerca editoriale".',
            ],
            'SEARCH_PROFILE_INCOMPLETE' => [
                'label' => 'Profilo di ricerca incompleto',
                'cause' => 'Il profilo di ricerca esiste ma non ha una query primaria.',
                'action' => 'Compilare la query primaria nella sezione "Profilo di ricerca editoriale".',
            ],
            'FRESHNESS_NOT_TRACKED' => [
                'label' => 'Freschezza editoriale non tracciata',
                'cause' => 'Nessuna data di ultima revisione editoriale (profilo di ricerca) né una modifica editoriale rilevata dopo la pubblicazione.',
                'action' => 'Registrare la data di ultima revisione nel profilo di ricerca, o aggiornare l\'articolo se necessario.',
            ],
            default => [
                'label' => $code,
                'cause' => 'Segnalazione non documentata.',
                'action' => 'Verificare manualmente.',
            ],
        };
    }
}
