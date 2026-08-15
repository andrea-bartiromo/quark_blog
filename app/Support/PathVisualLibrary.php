<?php

namespace App\Support;

use App\Models\ContentCluster;

/**
 * Integrazione reale della Kairus Editorial Visual Library (Missione
 * PERCORSI WOW, Pass 2/3 — "Integrazione reale"). Distinta di proposito
 * dalle cover articolo (App\Models\Article::cover_image, usate nella
 * timeline — vedi show.blade.php): questa libreria serve solo le "pause
 * narrative" (visual break) tra le sezioni del Percorso, mai come una
 * seconda serie di thumbnail.
 *
 * Selezione semantica SENZA nuovo schema: `articles.category` è già
 * validato contro esattamente le 6 chiavi di config('laboratorio.categories')
 * (vedi StoreArticleRequest) — le stesse 6 chiavi in cui questa libreria
 * organizza le 24 immagini. La categoria dominante tra gli articoli
 * pubblicati di un Percorso è quindi un segnale già presente nel database,
 * non un'euristica inventata. Un Percorso futuro senza articoli (o senza
 * un segnale di categoria chiaro) non genera un'eccezione: ricade su una
 * scelta deterministica basata sullo slug, esattamente come
 * PathVisualSignature — mai una configurazione per singolo Percorso.
 */
class PathVisualLibrary
{
    private const DIRECTORY = 'percorsi';

    /**
     * Un raggruppamento per categoria, non un mapping 1:1 verso i sei
     * Percorsi reali (che restano casi di prova, mai riferimenti diretti
     * nel codice) — la stessa categoria può servire qualunque Percorso,
     * presente o futuro, i cui articoli vi appartengano in prevalenza.
     */
    private const CATEGORY_IMAGES = [
        'intelligenza-artificiale' => [
            'kairus-ai-knowledge-01.webp',
            'kairus-ai-future-01.webp',
            'kairus-ai-society-01.webp',
            'kairus-human-future-01.webp',
            'kairus-ai-work-01.webp',
        ],
        'spazio' => [
            'kairus-space-cosmos-01.webp',
            'kairus-space-exploration-01.webp',
            'kairus-space-earth-01.webp',
            'kairus-space-darkness-01.webp',
        ],
        'energia' => [
            'kairus-energy-transition-01.webp',
            'kairus-energy-future-01.webp',
            'kairus-energy-storage-01.webp',
        ],
        'salute' => [
            'kairus-health-human-01.webp',
            'kairus-health-future-01.webp',
            'kairus-ai-medicine-01.webp',
            'kairus-ai-surgery-01.webp',
        ],
        'ambiente' => [
            'kairus-nature-regeneration-01.webp',
            'kairus-environment-biodiversity-01.webp',
            'kairus-environment-pollinators-01.webp',
            'kairus-climate-balance-01.webp',
        ],
        'societa' => [
            'kairus-society-change-01.webp',
            'kairus-science-discovery-01.webp',
            'kairus-science-microscopic-01.webp',
            'kairus-exploration-manifesto-01.webp',
        ],
    ];

    /**
     * Filosofia dei break (Parte 4): non un numero arbitrario, ma "quanto
     * viaggio c'è da respirare". Un Percorso con poche tappe non ha spazio
     * per due pause senza sembrare sovraccarico; uno maturo se lo può
     * permettere. Nessuna pausa per un Percorso ancora senza contenuto
     * pubblico — non c'è alcun viaggio da accompagnare.
     */
    public static function breakCountFor(int $publishedArticleCount): int
    {
        if ($publishedArticleCount <= 0) {
            return 0;
        }

        return $publishedArticleCount >= 4 ? 2 : 1;
    }

    /**
     * $count immagini distinte, deterministiche per questo Percorso — mai
     * casuali, mai ripetute tra loro entro lo stesso Percorso (finché
     * $count non supera la dimensione del pool, che con 3-5 immagini per
     * categoria e al più 2 break non accade mai in pratica).
     *
     * @return list<string> nomi file, non URL — vedi self::url()
     */
    public static function imagesFor(ContentCluster $cluster, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $pool = self::CATEGORY_IMAGES[self::categoryFor($cluster)];
        $poolSize = count($pool);
        $count = min($count, $poolSize);

        $start = self::hash(self::seed($cluster)) % $poolSize;

        $selected = [];
        for ($i = 0; $i < $count; $i++) {
            $selected[] = $pool[($start + $i) % $poolSize];
        }

        return $selected;
    }

    public static function url(string $filename): string
    {
        return asset('assets/img/'.self::DIRECTORY.'/'.$filename);
    }

    /**
     * Categoria dominante tra gli articoli pubblicati del Percorso (quella
     * con più occorrenze; a parità vince l'ordine di iterazione stabile di
     * Collection::countBy(), mai casuale). Se il Percorso non ha ancora
     * articoli pubblicati, o le loro categorie non sono tra le 6 note
     * (dato incoerente, mai dovrebbe accadere data la validazione a monte,
     * ma questo metodo non deve mai eccepire per questo), ricade sulla
     * scelta deterministica per slug.
     */
    private static function categoryFor(ContentCluster $cluster): string
    {
        $dominant = $cluster->relationLoaded('articles')
            ? $cluster->articles->where('status', 'published')->pluck('category')
            : $cluster->articles()->published()->pluck('category');

        $dominant = collect($dominant)
            ->filter(fn (?string $category) => $category !== null && array_key_exists($category, self::CATEGORY_IMAGES))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $dominant ?? self::fallbackCategory($cluster);
    }

    private static function fallbackCategory(ContentCluster $cluster): string
    {
        $categories = array_keys(self::CATEGORY_IMAGES);

        return $categories[self::hash(self::seed($cluster)) % count($categories)];
    }

    private static function seed(ContentCluster $cluster): string
    {
        return filled($cluster->slug) ? $cluster->slug : (string) $cluster->id;
    }

    private static function hash(string $seed): int
    {
        return abs(crc32($seed));
    }
}
