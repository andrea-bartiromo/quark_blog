<?php

namespace App\Services\SearchConsole;

use App\Models\Article;
use Illuminate\Support\Collection;

/**
 * Cantiere 5 (programma "Kairus Organic Discovery"): un gruppo di articoli
 * pubblici che ricevono impression Search Console REALI per la stessa query
 * (normalizzata) nello stesso periodo — possibile cannibalizzazione di
 * ricerca. Segnale distinto e più forte del controllo già esistente su
 * `ArticleSearchProfile::primary_query` dichiarato uguale
 * (ArticleSearchProfileCollisionService/EditorialOpportunityDecisionService,
 * mai duplicato qui): qui la sovrapposizione è osservata nei dati reali di
 * Google, non solo dichiarata in fase di redazione.
 *
 * $competitors è ordinata per impression decrescenti: il primo elemento è
 * l'articolo "probabile primario" (quello con più segnale), gli altri sono
 * candidati a consolidamento/differenziazione. $opportunity è la stessa
 * identità (type|query|page_url) già presente nell'elenco generale di
 * SearchOpportunityScoringService::currentOpportunities() — registrare una
 * decisione da questa pagina passa quindi dalla stessa infrastruttura già
 * esistente (SearchOpportunityDecisionService: baseline, storico
 * append-only, misurazione a 28/90 giorni), mai una seconda struttura di
 * persistenza.
 */
readonly class SearchCannibalizationFinding
{
    /**
     * @param  Collection<int, array{article: Article, page_url: ?string, impressions: int, clicks: int, position: float}>  $competitors
     */
    public function __construct(
        public string $query,
        public Collection $competitors,
        public Article $primaryArticle,
        public SearchOpportunity $opportunity,
    ) {}
}
