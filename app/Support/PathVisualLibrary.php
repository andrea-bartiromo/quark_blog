<?php

namespace App\Support;

use App\Models\ContentCluster;
use Illuminate\Support\Collection;

/**
 * Integrazione reale della Kairus Editorial Visual Library (Missione
 * KAIRUS PATH VISUAL LANGUAGE). Distinta di proposito dalle cover
 * articolo (App\Models\Article::cover_image, usate nella timeline — vedi
 * show.blade.php): questa libreria fornisce solo asset SEMANTICI —
 * quanti e dove mostrarli è una decisione editoriale del layout
 * (show.blade.php), mai un conteggio derivato dal numero di articoli.
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
 *
 * Due asset distinti, non un elenco quantitativo:
 * - atmosphereImage(): sempre presente, un solo ingresso atmosferico
 *   nell'apertura del Percorso, dalla categoria dominante.
 * - transitionImage(): presente solo quando esiste un vero cambio di
 *   registro — la sequenza pubblicata attraversa più di una categoria.
 *   Un Percorso tematicamente omogeneo (il caso comune: ogni Percorso
 *   reale corrisponde già a una categoria) non ne genera uno — non è un
 *   numero da raggiungere, è un segnale reale o la sua assenza.
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
     * L'ingresso atmosferico del Percorso (Parte 1) — sempre esattamente
     * un'immagine, mai assente, mai in numero variabile: è l'apertura
     * della mappa editoriale, non una pausa fra tante. Selezionata dalla
     * categoria dominante degli articoli pubblicati (o dal fallback per
     * slug se il Percorso non ne ha ancora).
     */
    public static function atmosphereImage(ContentCluster $cluster): string
    {
        $pool = self::CATEGORY_IMAGES[self::categoryFor($cluster)];

        return $pool[self::hash(self::seed($cluster)) % count($pool)];
    }

    /**
     * Il "cambio di registro" (Parte 5) — presente solo quando la
     * sequenza pubblicata attraversa davvero più di una categoria: un
     * segnale reale già nei dati (non un conteggio di articoli travestito
     * da euristica). La categoria di transizione è la prima, in ordine di
     * sequenza, diversa da quella del primo articolo pubblicato — il
     * punto in cui il Percorso cambia effettivamente argomento. Un
     * Percorso tematicamente omogeneo (il caso comune, dato che ogni
     * Percorso reale corrisponde già a una categoria) restituisce null:
     * nessuna immagine, perché non c'è alcuno scarto da segnalare.
     */
    public static function transitionImage(ContentCluster $cluster): ?string
    {
        $categories = self::orderedPublishedCategories($cluster);
        $opening = $categories->first();

        if ($opening === null) {
            return null;
        }

        $shift = $categories->first(fn (string $category) => $category !== $opening);

        if ($shift === null) {
            return null;
        }

        $pool = self::CATEGORY_IMAGES[$shift];
        // Seed distinto da quello dell'atmosfera: la stessa categoria non
        // deve mai restituire la stessa immagine per i due ruoli.
        $start = self::hash(self::seed($cluster).'|transition') % count($pool);

        return $pool[$start];
    }

    public static function url(string $filename): string
    {
        return asset('assets/img/'.self::DIRECTORY.'/'.$filename);
    }

    /**
     * Categorie note (tra le 6 della libreria) degli articoli pubblicati
     * del Percorso, nell'ordine reale della sequenza — lo stesso ordine
     * (position) con cui la timeline li presenta al lettore.
     *
     * @return Collection<int, string>
     */
    private static function orderedPublishedCategories(ContentCluster $cluster): Collection
    {
        $categories = $cluster->relationLoaded('articles')
            ? $cluster->articles->where('status', 'published')->pluck('category')
            : $cluster->articles()->published()->pluck('category');

        return collect($categories)
            ->filter(fn (?string $category) => $category !== null && array_key_exists($category, self::CATEGORY_IMAGES))
            ->values();
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
        $dominant = self::orderedPublishedCategories($cluster)
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
