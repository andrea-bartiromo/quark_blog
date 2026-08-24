<?php

namespace App\Services\Search;

use App\Models\Article;
use App\Models\Category;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Services\ContentClusters\ContentClusterPublicSequence;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * TROVA — ricerca "entità" complementare ad ArticleSearchService: categorie
 * e Percorsi che corrispondono alla query, non articoli (quelli restano di
 * competenza esclusiva di ArticleSearchService, mai ri-rankati qui).
 *
 * Recuperato (Mission 07) dal lavoro isolato in PR #319, che a sua volta
 * aveva già corretto la falla di sicurezza pubblica di PR #291: la prima
 * versione considerava un Percorso "trovabile" se aveva ALMENO UN membro
 * pubblicato ovunque nella sequenza, ignorando l'ordine editoriale — un
 * Percorso con un gap iniziale (un membro non pubblico PRIMA di uno
 * pubblicato) sarebbe comunque comparso nei risultati, mentre ogni altro
 * consumer pubblico dei Percorsi (ContentClusterController, home,
 * sitemap, ArticlePathNavigation, iscrizioni) si ferma al primo membro non
 * pubblico (vedi ContentClusterPublicSequence). PR #319 ha già corretto
 * questo, iniettando ContentClusterPublicSequence e richiedendo un prefisso
 * pubblico continuo non vuoto — qui recuperato invariato.
 *
 * Convergenza aggiuntiva di questa missione: PR #319 era stato scritto
 * PRIMA che "Percorsi Scheduled Activation V1" (questa stessa sessione)
 * sostituisse ContentCluster::active() con ContentCluster::publiclyVisible()
 * in ogni altro consumer pubblico (vedi ContentClusterController, Home,
 * Seo, ArticlePathNavigation, ContentClusterSubscriptionController) — un
 * Percorso is_active=true ma con publish_at futuro (programmato, non
 * ancora pubblico) sarebbe stato comunque trovabile qui. Corretto usando
 * publiclyVisible() al posto di active(), stesso allineamento già
 * applicato altrove.
 *
 * Category non ha un equivalente scheduling/publiclyVisible(): la pagina
 * pubblica /categoria/{slug} (ArticleController::category()) non filtra
 * mai su is_active — accetta qualunque categoria esistente (o slug legacy
 * di config) con almeno un articolo pubblicato — quindi non filtrare qui
 * su Category::active() converge con quel comportamento pubblico
 * esistente, non lo contraddice.
 *
 * Mission 30 — TROVA Content Graph Enrichment. Aggiunge un terzo gruppo,
 * `concepts`, ora che il Content Graph (Missioni 16-25) è su `main` con un
 * contratto di sicurezza pubblica già certificato
 * (ContentGraphPublicSafetyContractTest): solo Concept::active() (mai
 * draft/inactive, stesso confine già applicato da
 * ContentGraphService::discoverableConceptsForArticle()), e solo concetti
 * collegati ad almeno un articolo realmente pubblico
 * (Article::published()) — stessa richiesta di "contenuto reale dietro il
 * risultato" già applicata a Categorie (whereHas articoli pubblicati) e
 * Percorsi (prefisso pubblico continuo non vuoto), non un criterio nuovo.
 * Gli alias (ConceptAlias) partecipano sia al testo usato per
 * ALL_TOKENS/ANY_TOKEN sia, se corrispondono esattamente alla query,
 * alla classe EXACT — questo è il "miglioramento dell'interpretazione
 * della query" richiesto dalla missione, senza introdurre una quarta
 * classe di match (il set resta EXACT/ALL_TOKENS/ANY_TOKEN, invariato
 * da Missione 28). Nessun campo del risultato espone `short_definition`,
 * `status` o l'elenco degli articoli collegati: stesso contratto già
 * applicato da result() per Categorie/Percorsi (solo type/id/label/slug/
 * match_class/match_rank). Nessun consumer pubblico chiama ancora questo
 * servizio (SearchController non lo referenzia) — l'estensione resta
 * dietro il confine di servizio testato richiesto dalla missione, non
 * un'esposizione pubblica.
 */
class TrovaEntitySearchService
{
    public function __construct(
        private readonly SearchTokenizer $tokenizer,
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    /**
     * Search the publication-safe non-Article surfaces currently available.
     * Article results remain owned by ArticleSearchService and are deliberately
     * not re-ranked here.
     *
     * @return array{categories: Collection<int, array<string, mixed>>, percorsi: Collection<int, array<string, mixed>>, concepts: Collection<int, array<string, mixed>>}
     */
    public function search(string $query): array
    {
        $tokens = array_map($this->normalize(...), $this->tokenizer->tokenize($query));

        if ($tokens === []) {
            return ['categories' => collect(), 'percorsi' => collect(), 'concepts' => collect()];
        }

        return [
            'categories' => $this->searchCategories($query, $tokens),
            'percorsi' => $this->searchPercorsi($query, $tokens),
            'concepts' => $this->searchConcepts($query, $tokens),
        ];
    }

    /** @param  list<string>  $tokens */
    private function searchCategories(string $query, array $tokens): Collection
    {
        return Category::query()
            ->where(function ($categories) {
                $categories
                    ->whereHas('articles', fn ($articles) => $articles->published())
                    ->orWhereHas('secondaryArticles', fn ($articles) => $articles->published());
            })
            ->get(['id', 'name', 'slug', 'description'])
            ->map(fn (Category $category) => $this->result(
                type: 'category',
                id: $category->id,
                label: $category->name,
                slug: $category->slug,
                text: implode(' ', array_filter([$category->name, $category->description])),
                query: $query,
                tokens: $tokens,
            ))
            ->filter()
            ->sortBy(fn (array $result) => [$result['match_rank'], Str::lower($result['label']), $result['id']])
            ->values();
    }

    /** @param  list<string>  $tokens */
    private function searchPercorsi(string $query, array $tokens): Collection
    {
        $clusters = ContentCluster::query()
            ->publiclyVisible()
            ->with('articles')
            ->get(['id', 'name', 'slug', 'short_description', 'description']);

        if ($clusters->isEmpty()) {
            return collect();
        }

        $memberIds = $clusters
            ->flatMap(fn (ContentCluster $cluster) => $cluster->articles->pluck('id'))
            ->unique()
            ->values();

        $publishedIds = Article::query()
            ->published()
            ->whereIn('id', $memberIds)
            ->pluck('id');

        return $clusters
            ->filter(fn (ContentCluster $cluster) => $this->publicSequence->resolveLoaded($cluster, $publishedIds)['articles']->isNotEmpty())
            ->map(fn (ContentCluster $cluster) => $this->result(
                type: 'percorso',
                id: $cluster->id,
                label: $cluster->name,
                slug: $cluster->slug,
                text: implode(' ', array_filter([$cluster->name, $cluster->short_description, $cluster->description])),
                query: $query,
                tokens: $tokens,
            ))
            ->filter()
            ->sortBy(fn (array $result) => [$result['match_rank'], Str::lower($result['label']), $result['id']])
            ->values();
    }

    /** @param  list<string>  $tokens */
    private function searchConcepts(string $query, array $tokens): Collection
    {
        return Concept::query()
            ->active()
            ->whereHas('articleLinks.article', fn ($articles) => $articles->published())
            ->with('aliases')
            ->get(['id', 'name', 'slug', 'short_definition'])
            ->map(function (Concept $concept) use ($query, $tokens) {
                $aliases = $concept->aliases->pluck('alias')->all();

                return $this->result(
                    type: 'concept',
                    id: $concept->id,
                    label: $concept->name,
                    slug: $concept->slug,
                    text: implode(' ', array_filter([$concept->name, $concept->short_definition, ...$aliases])),
                    query: $query,
                    tokens: $tokens,
                    exactCandidates: [$concept->name, ...$aliases],
                );
            })
            ->filter()
            ->sortBy(fn (array $result) => [$result['match_rank'], Str::lower($result['label']), $result['id']])
            ->values();
    }

    /**
     * ANY_TOKEN è deliberatamente "OR tra i token": basta UN token
     * condiviso, non tutti. È lo stesso criterio di inclusione già in
     * produzione in ArticleSearchService::applyTextSearch() (le clausole
     * SQL per-token sono unite con OR, non AND) — un lettore che digita un
     * solo termine significativo si aspetta comunque un risultato, non zero
     * righe. `SearchTokenizer::tokenize()` già scarta stopword italiane e
     * token sotto i 2 caratteri prima che arrivino qui, quindi ANY_TOKEN
     * non può scattare su un articolo/preposizione tronca — solo su un
     * termine genuinamente significativo condiviso da entrambi i lati.
     *
     * $exactCandidates elenca ogni stringa che, se identica alla query
     * normalizzata, vale come EXACT — di default solo $label (Categorie,
     * Percorsi), ma i Concetti (Missione 30) vi aggiungono i propri alias:
     * un alias esatto deve valere quanto il nome esatto, non retrocedere ad
     * ALL_TOKENS/ANY_TOKEN. Non introduce una quarta classe di match — il
     * set resta EXACT/ALL_TOKENS/ANY_TOKEN.
     *
     * @param  list<string>  $tokens
     * @param  list<string>  $exactCandidates
     * @return array<string, mixed>|null
     */
    private function result(
        string $type,
        int $id,
        string $label,
        string $slug,
        string $text,
        string $query,
        array $tokens,
        array $exactCandidates = [],
    ): ?array {
        $normalizedQuery = $this->normalize($query);
        $normalizedText = $this->normalize($text);
        $normalizedExactCandidates = array_map(
            $this->normalize(...),
            $exactCandidates === [] ? [$label] : $exactCandidates,
        );

        $matchClass = match (true) {
            in_array($normalizedQuery, $normalizedExactCandidates, true) => 'EXACT',
            collect($tokens)->every(fn (string $token) => str_contains($normalizedText, $token)) => 'ALL_TOKENS',
            collect($tokens)->contains(fn (string $token) => str_contains($normalizedText, $token)) => 'ANY_TOKEN',
            default => null,
        };

        if ($matchClass === null) {
            return null;
        }

        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'slug' => $slug,
            'match_class' => $matchClass,
            'match_rank' => match ($matchClass) {
                'EXACT' => 1,
                'ALL_TOKENS' => 2,
                default => 3,
            },
        ];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->value();
    }
}
