<?php

namespace App\Services\EditorialOperations;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use App\Services\ContentClusters\PercorsoPublicationReadinessService;
use App\Services\ContentGraph\ContentGraphCoverageService;
use App\Services\ContentGraph\ContentGraphOrphanAuditService;
use App\Services\ContentHealth\ArticleContentHealthService;
use App\Services\ContinuationAnalyticsService;
use App\Services\EditorialQuality\SeoMetadataQualityAuditService;
use App\Services\EditorialQuality\SourceImageAttributionHealthService;
use App\Services\EditorialRadar\EditorialRadarProviderGraphService;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use Illuminate\Support\Collection;

/**
 * Editorial Operations Dashboard V1 — recuperato (Mission 09) dal foundation
 * isolato in PR #294 e convergente sui due servizi Percorsi più recenti
 * (Mission 02 — Percorsi Readiness V2, Mission 04 — Editorial Order
 * Health), entrambi assenti alla base di #294.
 *
 * Un solo principio guida ogni sezione: MAI ricalcolare qui una regola già
 * espressa da un servizio esistente — questo aggregatore chiama, raccoglie
 * e riassume, non decide nulla di nuovo. Read-only per costruzione: nessun
 * metodo qui scrive sul database.
 */
class EditorialOperationsDashboardService
{
    public function __construct(
        private readonly ArticleContentHealthService $contentHealth,
        private readonly PercorsoCoverageAuditService $percorsoCoverage,
        private readonly PercorsoPublicationReadinessService $percorsoReadiness,
        private readonly SourceImageAttributionHealthService $attribution,
        private readonly SeoMetadataQualityAuditService $seo,
        private readonly EditorialRadarProviderGraphService $radar,
        private readonly ContentGraphOrphanAuditService $contentGraphOrphans,
        private readonly ContentGraphCoverageService $contentGraphCoverage,
        private readonly ContinuationAnalyticsService $continuationAnalytics,
        private readonly SearchConsoleFreshnessService $searchConsoleFreshness,
        private readonly PublicationCadenceService $publicationCadence,
    ) {}

    /**
     * Quante righe mostrare nella card Opportunità — un tetto editoriale,
     * non un limite tecnico: il Radar può produrre più segnali di quanti
     * un editor possa realisticamente lavorare in una sessione. Il conteggio
     * totale resta comunque visibile accanto all'elenco troncato.
     */
    private const OPPORTUNITA_DISPLAY_LIMIT = 10;

    /**
     * Mission 37 — Dashboard Priority Model V2. Stesso vocabolario HIGH/
     * MEDIUM già stabilito da Radar (Mission 35/
     * EditorialRadarService::opportunities()) per i finding di content
     * health/attribuzione — riusato qui, mai reinventato, così "HIGH"
     * significa la stessa cosa ovunque compaia nella dashboard.
     *
     * @var list<string>
     */
    private const HIGH_PRIORITY_CONTENT_HEALTH_IDS = ['cover', 'summary', 'sources'];

    private const HIGH_PRIORITY_ATTRIBUTION_ID = 'external_body_images';

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $articles = Article::query()
            ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
            ->with('contentClusters:id')
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();

        $scheduledArticles = $articles->where('status', Article::STATUS_SCHEDULED);

        // Missione 39 (Fase E — Editorial Quality & Readiness): il form di
        // pianificazione (data + ora, senza secondi) non impedisce a due
        // articoli di ricevere lo stesso istante — PublishScheduledArticles
        // li pubblicherebbe entrambi nella stessa run, in un ordine deciso
        // solo dall'id, non da una scelta editoriale. countBy() sullo
        // stesso istante ISO riusa la stessa lista già caricata sopra,
        // nessuna nuova query.
        $scheduledInstantCounts = $scheduledArticles
            ->map(fn (Article $article) => $article->published_at?->toISOString())
            ->filter()
            ->countBy();

        // Mission 37: già ordinati per published_at asc dalla query sopra,
        // quindi un articolo "in ritardo" (published_at nel passato ma lo
        // scheduler non lo ha ancora pubblicato) è già in cima per
        // costruzione — 'overdue' rende visibile IL PERCHÉ, non cambia
        // l'ordine.
        $toPublish = $scheduledArticles
            ->map(fn (Article $article) => [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'published_at' => $article->published_at?->toISOString(),
                'overdue' => $article->published_at !== null && $article->published_at->isPast(),
                'collision' => $article->published_at !== null
                    && ($scheduledInstantCounts[$article->published_at->toISOString()] ?? 0) > 1,
            ])->values()->all();

        $toFix = $articles->map(function (Article $article): ?array {
            $healthWarnings = $this->contentHealth->evaluate($article)
                ->where('status', ArticleContentHealthService::STATUS_WARNING)
                ->values();
            $attributionWarnings = collect($this->attribution->evaluate($article))
                ->where('status', SourceImageAttributionHealthService::WARNING)
                ->values();

            if ($healthWarnings->isEmpty() && $attributionWarnings->isEmpty()) {
                return null;
            }

            // Mission 37: stesso vocabolario HIGH/MEDIUM di Radar — HIGH se
            // almeno un finding rientra negli id già trattati come critici
            // altrove nella dashboard (vedi HIGH_PRIORITY_CONTENT_HEALTH_IDS/
            // HIGH_PRIORITY_ATTRIBUTION_ID), mai una seconda definizione di
            // "critico" inventata qui.
            $isHigh = $healthWarnings->contains(fn (array $f) => in_array($f['id'], self::HIGH_PRIORITY_CONTENT_HEALTH_IDS, true))
                || $attributionWarnings->contains(fn (array $f) => $f['id'] === self::HIGH_PRIORITY_ATTRIBUTION_ID);

            // Mission 38 — Dashboard "Why?" Explanations: mai un badge
            // HIGH/MEDIUM senza un motivo accanto — riusa le label/reason
            // già scritte da ArticleContentHealthService e
            // SourceImageAttributionHealthService, mai una nuova frase
            // inventata qui.
            $reasonSummary = $healthWarnings->pluck('label')
                ->merge($attributionWarnings->pluck('reason'))
                ->values()
                ->all();

            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'health_warnings' => $healthWarnings->all(),
                'attribution_warnings' => $attributionWarnings->all(),
                'priority' => $isHigh ? 'HIGH' : 'MEDIUM',
                'reason_summary' => $reasonSummary,
            ];
        })->filter()->values()
            // Stable sort: HIGH prima di MEDIUM, a parità mantiene l'ordine
            // di partenza (published_at asc) grazie a Collection::sortBy()
            // essere stabile in PHP 8+.
            ->sortBy(fn (array $row) => $row['priority'] === 'HIGH' ? 0 : 1)
            ->values()->all();

        // Missione 30 (Fase D — Editorial Operations Command Center):
        // "publication readiness summary" — un articolo programmato è
        // "pronto" se non ha alcun warning content-health/attribuzione
        // aperto: stesso insieme di article_id già calcolato sopra per
        // 'da_sistemare', mai una seconda soglia di "readiness" inventata
        // qui. L'assegnazione a un Percorso è un segnale diverso (Missione
        // 29), non un blocco alla pubblicazione del singolo articolo.
        $articlesWithOpenIssues = collect($toFix)->pluck('article_id')->all();
        $toPublish = collect($toPublish)
            ->map(fn (array $row) => [
                ...$row,
                'ready' => ! in_array($row['article_id'], $articlesWithOpenIssues, true),
            ])
            ->values()
            ->all();
        $readyToPublishCount = collect($toPublish)->where('ready', true)->count();

        $coverage = $this->percorsoCoverage->audit();
        // Missione 50 (secondo batch autonomo KAIRUS, Fase F — Search
        // Intelligence): "percorso pillar integrity" — PercorsoCoverageAuditService
        // ::audit() calcola già paths_with_incoherent_pillar (pillar mai
        // aggiunto come membro del Percorso, o ancora in bozza/revisione),
        // ma nessuna vista lo aveva mai letto: né qui né
        // PercorsoPublicationReadinessService, che controlla il pillar solo
        // rispetto al prefisso pubblico raggiungibile (pillar_outside_
        // reachable_prefix), mai la sua coerenza strutturale con il
        // Percorso. Il terzo codice possibile, pillar_target_missing (target
        // eliminato), non è invece raggiungibile tramite Article::delete():
        // pillar_article_id ha nullOnDelete() sulla FK, quindi la colonna si
        // azzera nella stessa operazione — resta comunque gestito qui sotto
        // per completezza difensiva.
        $pillarIssues = collect($coverage['paths_with_incoherent_pillar'])
            ->map(fn (array $row) => [
                'cluster_id' => $row['id'],
                'name' => $row['name'],
                'pillar_issue' => $row['pillar_issue'],
                'pillar_issue_label' => $this->pillarIssueLabel($row['pillar_issue']),
            ])
            ->values()
            ->all();
        // Missione 53 (secondo batch autonomo KAIRUS, Fase F — Search
        // Intelligence): PercorsoCoverageAuditService::audit() calcola già
        // articles_in_multiple_paths (con path_count e l'elenco degli slug),
        // e il suo stesso policy_notes.multiple_paths_are_reported_not_failed
        // promette esplicitamente che questo venga "reported" — una promessa
        // mai mantenuta, perché nessuna vista leggeva mai questa chiave.
        // Deliberatamente ESCLUSO da open_problems_total, coerente con la
        // policy: "reported, not failed" — un articolo in più Percorsi è
        // un fatto editoriale legittimo, non un'anomalia da contare.
        $articlesInMultiplePaths = $coverage['articles_in_multiple_paths'];
        // Missione 54 (secondo batch autonomo KAIRUS, Fase F — Search
        // Intelligence): PercorsoCoverageAuditService::audit() calcola già
        // paths_with_non_publishable_members (un Percorso che elenca come
        // membro formale un articolo ancora in bozza/revisione), ma
        // nessuna vista lo leggeva mai. A differenza di
        // articles_in_multiple_paths (Missione 53), qui non esiste alcuna
        // policy_notes che lo dichiari "reported, not failed" — un
        // membro non pubblicabile in un Percorso che dovrebbe restare
        // coerente è un problema strutturale reale (stesso trattamento di
        // paths_with_incoherent_pillar sopra), quindi CONTA in
        // open_problems_total, un termine per Percorso coinvolto.
        $nonPublishableMembers = collect($coverage['paths_with_non_publishable_members'])
            ->map(fn (array $row) => [
                'cluster_id' => $row['id'],
                'name' => $row['name'],
                'members' => $row['non_publishable_members'],
            ])
            ->values()
            ->all();
        $seo = $this->seo->audit();
        $orderHealth = $this->percorsoCoverage->editorialOrderHealth();
        $orderHealthSummary = $this->orderHealthSummary($orderHealth);
        // Mission 15 — Dashboard Integration: the two summaries below cover
        // genuinely different concerns and must keep listing genuinely
        // different problems separately (see
        // test_a_percorso_with_both_readiness_findings_and_order_health_issues_appears_in_both_sections).
        // But Mission 14 wired one SHARED signal (complete_with_hidden_remainder)
        // into both readiness and order-health, so the same Percorso can now
        // legitimately appear in both sections for the identical root cause —
        // this flag lets the view say so instead of presenting it as two
        // unrelated problems.
        $orderHealthFlaggedClusterIds = collect($orderHealthSummary['clusters_with_issues'])
            ->merge($orderHealthSummary['clusters_with_advisories_only'])
            ->pluck('cluster_id')
            ->all();
        $percorsiReadiness = $this->percorsiReadinessSummary($orderHealthFlaggedClusterIds);
        $opportunities = $this->radar->opportunities();

        // Mission 37: PercorsoCoverageAuditService::articleSummary() non
        // include published_at (non serve al suo dominio) — arricchito qui,
        // nel solo layer di aggregazione, riusando $articles già caricato
        // sopra invece di aggiungere una query o cambiare la forma di un
        // servizio condiviso. Più a lungo un articolo pubblicato resta
        // isolato, più la sua posizione in cima alla lista è la priorità:
        // nessuna soglia arbitraria in giorni, solo l'ordine reale.
        $articlesById = $articles->keyBy('id');
        $isolatedArticles = collect($coverage['published_without_path'])
            ->map(fn (array $row) => [
                ...$row,
                'published_at' => $articlesById->get($row['id'])?->published_at?->toISOString(),
            ])
            ->sortBy('published_at')
            ->values()
            ->all();

        // Missione 27 (Fase D — Editorial Operations Command Center):
        // "actionable problems queue" — "pubblicato senza Concept" è
        // l'unico esempio della missione non ancora coperto da nessuna
        // sezione esistente E con un dato pronto, già testato, già a
        // livello di singolo articolo: ContentGraphOrphanAuditService::
        // orphanArticles() (Missione 23, batch precedente). Le altre
        // voci d'esempio della missione o sono già coperte altrove
        // (contenuti_isolati, percorsi_order_health) o sono il compito
        // dedicato di una missione successiva con una nuance propria
        // (Missione 29 — pubblicazione programmata non assegnata;
        // Missione 34 — Search Console) — mai anticipate qui in forma
        // grezza per non doverle poi ridisegnare.
        $articlesWithoutConcept = collect($this->contentGraphOrphans->orphanArticles())
            ->map(fn (array $row) => [
                ...$row,
                'published_at' => $articlesById->get($row['id'])?->published_at?->toISOString(),
            ])
            ->sortBy('published_at')
            ->values()
            ->all();

        // Missione 41 (Fase E — Editorial Quality & Readiness): "stale
        // content candidates" — il dominio ha già un segnale esplicito e
        // non inventato per questo, Article::verification_status ===
        // 'needs_update' (già gestito dalla pagina dedicata /admin/verifica),
        // mai una soglia di anzianità arbitraria (ArticleContentHealthService
        // ::freshness() la esclude esplicitamente: "nessuna soglia editoriale
        // di anzianità è definita nel dominio corrente"). Il gap reale è che
        // questo segnale non è mai stato esposto sul command center — riusa
        // $articles già caricato sopra, nessuna nuova query.
        $staleContentCandidates = $articles
            ->where('verification_status', 'needs_update')
            ->map(fn (Article $article) => [
                'article_id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'published_at' => $article->published_at?->toISOString(),
            ])
            ->sortBy('published_at')
            ->values()
            ->all();

        // Missione 29 (Fase D — Editorial Operations Command Center):
        // "unassigned scheduled articles" — PercorsoCoverageAuditService::
        // audit() calcola già scheduled_without_path con la stessa regola
        // di published_without_path (già usata sopra per contenuti_isolati),
        // solo mai esposta prima d'ora fuori dal dominio Percorsi. Nessuna
        // nuova regola qui: solo lo stesso arricchimento con published_at
        // già applicato alle altre code di articoli.
        $unassignedScheduledArticles = collect($coverage['scheduled_without_path'])
            ->map(fn (array $row) => [
                ...$row,
                'published_at' => $articlesById->get($row['id'])?->published_at?->toISOString(),
            ])
            ->sortBy('published_at')
            ->values()
            ->all();

        $seoViolations = $this->seoViolations($seo['articles'], $articlesById);
        $overdueCount = collect($toPublish)->where('overdue', true)->count();
        $collisionCount = collect($toPublish)->where('collision', true)->count();
        $openProblemsTotal = count($toFix)
            + count($isolatedArticles)
            + count($articlesWithoutConcept)
            + count($unassignedScheduledArticles)
            + count($percorsiReadiness)
            + $orderHealthSummary['structural_error_count']
            + $orderHealthSummary['publication_warning_count']
            + count($seoViolations)
            + $overdueCount
            + $collisionCount
            + count($staleContentCandidates)
            + count($pillarIssues)
            + count($nonPublishableMembers);

        return [
            // Missione 26 (Fase D — Editorial Operations Command Center):
            // "capire in pochi secondi se la macchina editoriale è sana."
            // Nessuna nuova regola di dominio — solo una somma dei conteggi
            // di problema già calcolati dalle sezioni sotto (mai i segnali
            // editorial_advisory, già "mai bloccanti" per contratto — vedi
            // orderHealthSummary()). Il contesto di catalogo (pubblicati,
            // Percorsi attivi) usa le stesse scope già pubbliche
            // (Article::published(), ContentCluster::publiclyVisible())
            // riusate ovunque nel dominio, mai un nuovo conteggio.
            'salute_operativa' => [
                'status' => $openProblemsTotal === 0 ? 'SANA' : 'DA_RIVEDERE',
                'open_problems_total' => $openProblemsTotal,
                'published_articles_total' => Article::query()->published()->count(),
                'active_percorsi_total' => ContentCluster::query()->publiclyVisible()->count(),
            ],
            'da_pubblicare' => $toPublish,
            'pubblicazione_readiness' => [
                'total' => count($toPublish),
                'ready_count' => $readyToPublishCount,
                'not_ready_count' => count($toPublish) - $readyToPublishCount,
                'collision_count' => $collisionCount,
            ],
            'da_sistemare' => $toFix,
            'contenuti_isolati' => $isolatedArticles,
            'contenuti_senza_concept' => $articlesWithoutConcept,
            'contenuti_da_aggiornare' => $staleContentCandidates,
            // Missione 32 (Fase D — Editorial Operations Command Center):
            // "Content Graph operational health" — solo i numeri aggregati
            // già calcolati da ContentGraphCoverageService (Missione 19,
            // primo batch), mai un ricalcolo. La diagnostica per-item più
            // approfondita (classificazione salute concetto, copertura
            // domande pubbliche, ecc.) è compito dedicato della Fase G
            // (Missioni 55-64) — qui solo il riepilogo operativo.
            'content_graph' => $this->contentGraphCoverage->summary(),
            // Missione 33 (Fase D — Editorial Operations Command Center):
            // "second-read operational health" — riusa
            // ContinuationAnalyticsService::siteWideTotals() (Missione 33),
            // mai un ricalcolo qui. Nessun limite di finestra temporale:
            // stesso "sempre" di default già usato dalla pagina
            // /admin/second-read.
            'second_read' => $this->continuationAnalytics->siteWideTotals(),
            // Missione 34 (Fase D — Editorial Operations Command Center):
            // "Search Opportunities operational health" — riusa
            // SearchConsoleFreshnessService::summary() (Missione 34), mai
            // un ricalcolo qui. Nessuna soglia di staleness: quella è il
            // compito dedicato della Fase F (Missione 45 — import
            // freshness), mai anticipata in forma grezza qui.
            'search_console' => $this->searchConsoleFreshness->summary(),
            // Missione 40 (Fase E — Editorial Quality & Readiness):
            // "publication gaps" a livello di sito, distinto dal
            // "published_beyond_gap" per-Percorso già coperto sopra (Missione
            // 21) — riusa PublicationCadenceService::summary() (Missione 40),
            // mai un ricalcolo qui. Nessuna soglia di "troppo tempo": non
            // definita nel repository, mai inventata qui.
            'ritmo_pubblicazione' => $this->publicationCadence->summary(),
            'programmati_non_assegnati' => $unassignedScheduledArticles,
            'seo' => [
                'summary' => $seo['summary'],
                'articles' => $seo['articles'],
                'violations' => $seoViolations,
            ],
            'percorsi_readiness' => $percorsiReadiness,
            'percorsi_order_health' => $orderHealthSummary,
            'percorsi_pillar_issues' => $pillarIssues,
            'articles_in_multiple_paths' => $articlesInMultiplePaths,
            'percorsi_non_publishable_members' => $nonPublishableMembers,
            'opportunita' => [
                'available' => true,
                'total' => $opportunities->count(),
                'items' => $opportunities->take(self::OPPORTUNITA_DISPLAY_LIMIT)->values()->all(),
            ],
            // Mission 36: UtmLinkGenerator è già su main, ma è deliberatamente
            // stateless (vedi docs/SOCIAL_DISTRIBUTION.md) — nessuna campagna
            // o click viene mai persistito, quindi non esiste alcun dato
            // aggregato reale da riassumere in una card. 'available' resta
            // false per non inventare metriche, ma tool_url punta comunque
            // allo strumento reale così l'editor non perde l'accesso.
            'distribuzione' => [
                'available' => false,
                'reason' => 'Il generatore di link UTM è già su main ma è deliberatamente stateless: nessuna campagna o click viene persistito, quindi non c’è alcun dato aggregato da mostrare qui.',
                'tool_url' => route('admin.social-distribution'),
            ],
        ];
    }

    /**
     * Riusa PercorsoPublicationReadinessService::evaluate() (Mission 02),
     * mai una riga di logica di readiness riscritta qui. Un solo Percorso
     * NON READY/READY WITH WARNINGS per riga — i Percorsi già READY non
     * compaiono (la dashboard mostra solo ciò che richiede attenzione,
     * stesso principio già applicato a "da_sistemare"/"contenuti_isolati").
     *
     * Costo query: O(N) sui Percorsi (un evaluate() per cluster, ciascuno
     * con un numero fisso di query interne — vedi
     * PercorsoPublicationReadinessService::evaluate(), che risolve la
     * sequenza pubblica reale via ContentClusterPublicSequence::resolve()
     * per ciascun cluster). Deliberatamente non "aggirato" con una
     * reimplementazione locale della stessa regola solo per renderlo O(1):
     * i Percorsi sono un catalogo editoriale curato a mano (unità, non
     * migliaia, a differenza degli Articoli), quindi la crescita lineare
     * resta trascurabile in pratica — provato dal test di query budget.
     *
     * @param  list<int>  $orderHealthFlaggedClusterIds
     * @return array<int, array<string, mixed>>
     */
    private function percorsiReadinessSummary(array $orderHealthFlaggedClusterIds): array
    {
        return ContentCluster::query()
            ->ordered()
            ->get()
            ->map(function (ContentCluster $cluster) use ($orderHealthFlaggedClusterIds) {
                $result = $this->percorsoReadiness->evaluate($cluster);

                if ($result['status'] === 'READY') {
                    return null;
                }

                return [
                    'cluster_id' => $cluster->id,
                    'name' => $cluster->name,
                    'slug' => $cluster->slug,
                    'status' => $result['status'],
                    'finding_count' => $result['findings']->count(),
                    'error_count' => $result['findings']->where('severity', 'ERROR')->count(),
                    'warning_count' => $result['findings']->where('severity', 'WARNING')->count(),
                    // Mission 15: which ERROR/WARNING codes are actually
                    // driving this row — INFO codes (e.g. SCHEDULING_NOT_AVAILABLE)
                    // stay out of the visible list, same as they already stay
                    // out of error_count/warning_count above.
                    'codes' => $result['findings']
                        ->whereIn('severity', ['ERROR', 'WARNING'])
                        ->pluck('code')
                        ->values()
                        ->all(),
                    'also_in_order_health' => in_array($cluster->id, $orderHealthFlaggedClusterIds, true),
                ];
            })
            ->filter()
            // Mission 37: NOT READY (blocca la pubblicazione futura) prima di
            // READY WITH WARNINGS, poi per numero di errori/warning — tutti
            // valori già calcolati da PercorsoPublicationReadinessService,
            // mai una nuova regola: solo l'ordine di lettura cambia.
            ->sortBy(fn (array $row) => [
                $row['status'] === 'NOT READY' ? 0 : 1,
                -$row['error_count'],
                -$row['warning_count'],
            ])
            ->values()
            ->all();
    }

    /**
     * Riassume editorialOrderHealth() (Mission 04) in conteggi utili per
     * una card — mai un ricalcolo delle categorie, solo un conteggio dei
     * risultati già classificati dal servizio.
     *
     * @param  array<string, mixed>  $orderHealth
     * @return array<string, mixed>
     */
    private function orderHealthSummary(array $orderHealth): array
    {
        $structuralErrorCount = collect($orderHealth['structural_error'])->sum(fn (array $rows) => count($rows));
        $publicationWarningCount = collect($orderHealth['publication_warning'])->sum(fn (array $rows) => count($rows));
        $editorialAdvisoryCount = collect($orderHealth['editorial_advisory'])->sum(fn (array $rows) => count($rows));

        $blockingIssue = fn (array $row) => $row['missing_position'] !== []
            || $row['non_positive_position'] !== []
            || $row['duplicate_position'] !== []
            || $row['published_beyond_gap'] !== []
            || $row['pillar_outside_reachable_prefix']
            || $row['complete_with_hidden_remainder'];

        $advisoryOnly = fn (array $row) => $row['chronological_inversions'] !== []
            || $row['scheduled_out_of_order'] !== []
            || $row['dangling_transition'] !== null;

        // Missione 21 (secondo batch autonomo KAIRUS, Fase C — Percorsi
        // Advanced Operations): "publication gap dashboard". published_beyond_gap
        // era già calcolato per cluster (editorialOrderHealth(),
        // publication_warning) e già visibile come UNO dei tanti codici in
        // clusters_with_issues, ma senza un conteggio proprio: un editor non
        // poteva sapere QUANTI articoli già pubblicati restano invisibili
        // dietro un gap senza aprire ogni Percorso segnalato e contarli a
        // mano. Nessun nuovo calcolo — solo un riassunto in più dello stesso
        // elenco già prodotto da orderHealthRow()/editorialOrderHealth().
        $publishedBeyondGapClusters = collect($orderHealth['publication_warning']['published_beyond_gap']);
        $publishedBeyondGapArticleCount = $publishedBeyondGapClusters
            ->sum(fn (array $row) => count($row['published_beyond_gap']));

        return [
            'structural_error_count' => $structuralErrorCount,
            'publication_warning_count' => $publicationWarningCount,
            'editorial_advisory_count' => $editorialAdvisoryCount,
            'published_beyond_gap_article_count' => $publishedBeyondGapArticleCount,
            'published_beyond_gap_cluster_count' => $publishedBeyondGapClusters->count(),
            'clusters_with_issues' => collect($orderHealth['clusters'])
                ->filter($blockingIssue)
                ->map(fn (array $row) => [
                    'cluster_id' => $row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'codes' => $this->orderHealthCodes($row),
                ])
                ->values()
                ->all(),
            // Percorsi la cui unica segnalazione è un editorial_advisory:
            // mai bloccante per design (vedi editorialOrderHealth()), quindi
            // elencati separatamente e mai fusi in clusters_with_issues —
            // un editor non deve leggerli come "da correggere".
            'clusters_with_advisories_only' => collect($orderHealth['clusters'])
                ->filter(fn (array $row) => ! $blockingIssue($row) && $advisoryOnly($row))
                ->map(fn (array $row) => [
                    'cluster_id' => $row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'codes' => $this->orderHealthCodes($row),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Mission 38 — Dashboard "Why?" Explanations. editorialOrderHealth()
     * calcola già ciascun flag booleano/lista qui letto — questo metodo
     * traduce SOLO quali sono veri in un elenco di codici leggibili, mai
     * una nuova regola. Stesso pattern già usato da
     * percorsiReadinessSummary() per il campo 'codes'.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function orderHealthCodes(array $row): array
    {
        return array_values(array_filter([
            $row['missing_position'] !== [] ? 'MISSING_POSITION' : null,
            $row['non_positive_position'] !== [] ? 'NON_POSITIVE_POSITION' : null,
            $row['duplicate_position'] !== [] ? 'DUPLICATE_POSITION' : null,
            $row['published_beyond_gap'] !== [] ? 'PUBLISHED_BEYOND_GAP' : null,
            $row['pillar_outside_reachable_prefix'] ? 'PILLAR_OUTSIDE_REACHABLE_PREFIX' : null,
            $row['complete_with_hidden_remainder'] ? 'COMPLETE_WITH_HIDDEN_REMAINDER' : null,
            $row['chronological_inversions'] !== [] ? 'CHRONOLOGICAL_INVERSIONS' : null,
            $row['scheduled_out_of_order'] !== [] ? 'SCHEDULED_OUT_OF_ORDER' : null,
            $row['dangling_transition'] !== null ? 'DANGLING_TRANSITION' : null,
        ]));
    }

    /**
     * Missione 50 — stesso vocabolario dei tre codici già definiti da
     * PercorsoCoverageAuditService::clusterRow() (pillar_issue), mai
     * un quarto stato inventato qui.
     */
    private function pillarIssueLabel(string $pillarIssue): string
    {
        return match ($pillarIssue) {
            'pillar_target_missing' => 'Il pillar assegnato è stato eliminato.',
            'pillar_not_in_path' => 'Il pillar assegnato non fa più parte del Percorso.',
            'pillar_not_publishable' => 'Il pillar assegnato è ancora in bozza/revisione.',
            default => 'Il pillar assegnato non è coerente.',
        };
    }

    /**
     * Mission 37 — Dashboard Priority Model V2. SeoMetadataQualityAuditService
     * analizza TUTTI gli articoli (bozze incluse: il suo dominio non è
     * scoped a pubblico/programmato). Questa dashboard invece limita ogni
     * altra sezione a pubblicato+programmato (vedi test
     * test_draft_articles_are_not_exposed_in_operational_sections) — per
     * restare coerente con quel confine già stabilito, la lista qui è
     * ristretta agli stessi articoli via $articlesById, mai un nuovo
     * controllo di dominio. Priorità HIGH/MEDIUM riusa esattamente la
     * stessa lettura già fatta da Radar (canonical non valido = HIGH,
     * titolo/description duplicati = MEDIUM — vedi
     * EditorialRadarService::opportunities()).
     *
     * @param  array<int, array<string, mixed>>  $seoArticleRows
     * @param  Collection<int, Article>  $articlesById
     * @return array<int, array<string, mixed>>
     */
    private function seoViolations(array $seoArticleRows, $articlesById): array
    {
        return collect($seoArticleRows)
            ->filter(fn (array $row) => $articlesById->has($row['article_id']))
            ->map(function (array $row) use ($articlesById) {
                $canonicalWarning = $row['canonical']['status'] === 'WARNING';
                // Missione 38 (Fase E — Editorial Quality & Readiness): un
                // canonical duplicato tra due articoli è un conflitto di
                // canonicalizzazione tanto grave quanto uno sintatticamente
                // rotto (i motori di ricerca non sanno quale sia la pagina
                // "vera") — stessa priorità HIGH, mai una terza soglia.
                $duplicateCanonical = $row['duplicate_canonical_url'];
                $duplicateTitle = $row['duplicate_effective_title'];
                $duplicateDescription = $row['duplicate_effective_description'];

                if (! $canonicalWarning && ! $duplicateCanonical && ! $duplicateTitle && ! $duplicateDescription) {
                    return null;
                }

                $reasons = array_values(array_filter([
                    $canonicalWarning ? $row['canonical']['reason'] : null,
                    $duplicateCanonical ? 'URL canonical duplicato tra più articoli.' : null,
                    $duplicateTitle ? 'Titolo SEO effettivo duplicato.' : null,
                    $duplicateDescription ? 'Descrizione SEO effettiva duplicata.' : null,
                ]));

                return [
                    'article_id' => $row['article_id'],
                    'title' => $articlesById->get($row['article_id'])->title,
                    'slug' => $row['slug'],
                    'priority' => ($canonicalWarning || $duplicateCanonical) ? 'HIGH' : 'MEDIUM',
                    'reasons' => $reasons,
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['priority'] === 'HIGH' ? 0 : 1)
            ->values()
            ->all();
    }
}
