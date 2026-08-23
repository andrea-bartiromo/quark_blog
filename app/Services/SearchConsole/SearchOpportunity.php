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
}
