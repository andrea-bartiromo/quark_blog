<?php

namespace App\Services\SearchConsole;

use App\Models\Article;

readonly class SearchOpportunity
{
    public string $key;

    public function __construct(
        public string $type,
        public string $query,
        public ?Article $article,
        public int $impressions,
        public int $clicks,
        public ?float $ctr,
        public ?float $position,
        public float $score,
        public string $explanation,
        public ?string $pageUrl = null,
    ) {
        // Identità stabile del workflow editoriale (Mission 6):
        // un'opportunità viene ricalcolata da zero ad ogni richiesta, senza
        // riga propria — questa chiave (mai persistita qui, solo derivata)
        // è ciò che SearchOpportunityStatusService usa per far sopravvivere
        // uno stato "vista/gestita/ignorata" a un nuovo import dello stesso
        // periodo o di un periodo successivo con la stessa combinazione
        // tipo+query+pagina.
        $this->key = $type.'|'.$query.'|'.($pageUrl ?? '');
    }

    /**
     * Id HTML stabile e URL-safe derivato dalla stessa opportunity_key —
     * usato per collegare una riga di "Decisioni SEO"
     * (EditorialOpportunityDecisionController, dati read-only calcolati
     * senza mai conservare l'oggetto SearchOpportunity originale) alla
     * riga corrispondente in "Opportunità di ricerca"
     * (SearchOpportunityController) tramite un'ancora `#id`, senza
     * duplicare la logica di derivazione della chiave in due punti.
     */
    public static function anchorId(string $key): string
    {
        return 'opportunity-'.substr(hash('sha1', $key), 0, 16);
    }
}
