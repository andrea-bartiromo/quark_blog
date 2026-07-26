<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\SpecialPage;
use App\Models\User;
use App\Services\Concerns\ScansJsonContentLeaves;

/**
 * Rileva dove un Media e effettivamente utilizzato nel sito, riusando la
 * stessa mappa di campi gia censita da MediaReferenceService (copertine
 * articolo, banner annunci, foto autore, immagini categoria, contenuti JSON
 * delle pagine speciali) ma con una forma pensata per la griglia della
 * Libreria media: una singola chiamata batch copre l'intera pagina corrente
 * (una query per modello coinvolto, mai una per card) invece del preflight
 * per-singolo-file pensato per lo spostamento.
 *
 * Il confronto e sempre per uguaglianza esatta sul disk_name (o sul suo
 * valore virtuale ricostruito, es. "categories/{image}"): non vengono mai
 * usati confronti "contiene" sul nome file, cosi da evitare falsi positivi
 * tra file dal nome simile. L'unica eccezione e la scansione dei campi di
 * testo libero (Article.body, Ad.html_code), dove il confronto "contiene"
 * e il comportamento corretto: il file potrebbe essere incollato come URL
 * dentro il testo, non in un campo strutturato.
 */
class MediaUsageService
{
    use ScansJsonContentLeaves;

    /**
     * @param  iterable<Media>  $mediaItems
     * @return array<string, list<array<string, mixed>>> disk_name => record di utilizzo
     */
    public function usageForMany(iterable $mediaItems): array
    {
        $diskNames = [];
        foreach ($mediaItems as $media) {
            $diskNames[$media->disk_name] = true;
        }

        return $this->collectUsages(array_keys($diskNames));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usageFor(Media $media): array
    {
        return $this->collectUsages([$media->disk_name])[$media->disk_name] ?? [];
    }

    public function isUsed(Media $media): bool
    {
        return $this->usageFor($media) !== [];
    }

    public function isProtected(Media $media): bool
    {
        return $media->isProtected();
    }

    /**
     * @param  list<string>  $diskNames
     * @return array<string, list<array<string, mixed>>>
     */
    private function collectUsages(array $diskNames): array
    {
        $diskNames = array_values(array_unique($diskNames));
        $usages = array_fill_keys($diskNames, []);

        if ($diskNames === []) {
            return $usages;
        }

        $this->scanArticleCoverImages($diskNames, $usages);
        $this->scanArticleBodies($diskNames, $usages);
        $this->scanAdBannerImages($diskNames, $usages);
        $this->scanAdHtmlCode($diskNames, $usages);
        $this->scanUserPhotos($diskNames, $usages);
        $this->scanCategoryImages($diskNames, $usages);
        $this->scanSpecialPageContents($diskNames, $usages);

        return $usages;
    }

    /**
     * @param  list<string>  $diskNames
     * @param  array<string, list<array<string, mixed>>>  $usages
     */
    private function scanArticleCoverImages(array $diskNames, array &$usages): void
    {
        Article::query()
            ->whereIn('cover_image', $diskNames)
            ->get(['id', 'title', 'status', 'cover_image'])
            ->each(function (Article $article) use (&$usages): void {
                $usages[$article->cover_image][] = $this->record(
                    'article_cover_image',
                    'Copertina',
                    'Articolo',
                    $article->title,
                    $this->articleStatusLabel($article->status),
                    route('admin.articles.edit', $article)
                );
            });
    }

    /**
     * @param  list<string>  $diskNames
     * @param  array<string, list<array<string, mixed>>>  $usages
     */
    private function scanArticleBodies(array $diskNames, array &$usages): void
    {
        Article::query()
            ->where(function ($query) use ($diskNames) {
                foreach ($diskNames as $diskName) {
                    $query->orWhereRaw("body LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($diskName).'%']);
                }
            })
            ->get(['id', 'title', 'status', 'body'])
            ->each(function (Article $article) use ($diskNames, &$usages): void {
                foreach ($diskNames as $diskName) {
                    if (str_contains((string) $article->body, $diskName)) {
                        $usages[$diskName][] = $this->record(
                            'article_body',
                            'Contenuto articolo',
                            'Articolo',
                            $article->title,
                            $this->articleStatusLabel($article->status),
                            route('admin.articles.edit', $article)
                        );
                    }
                }
            });
    }

    /**
     * @param  list<string>  $diskNames
     * @param  array<string, list<array<string, mixed>>>  $usages
     */
    private function scanAdBannerImages(array $diskNames, array &$usages): void
    {
        Ad::query()
            ->whereIn('banner_image', $diskNames)
            ->get(['id', 'name', 'active', 'banner_image'])
            ->each(function (Ad $ad) use (&$usages): void {
                $usages[$ad->banner_image][] = $this->record(
                    'ad_banner_image',
                    'Banner pubblicitario',
                    'Annuncio',
                    $ad->name,
                    $ad->active ? 'Attivo' : 'Non attivo',
                    route('admin.ads')
                );
            });
    }

    /**
     * @param  list<string>  $diskNames
     * @param  array<string, list<array<string, mixed>>>  $usages
     */
    private function scanAdHtmlCode(array $diskNames, array &$usages): void
    {
        Ad::query()
            ->where(function ($query) use ($diskNames) {
                foreach ($diskNames as $diskName) {
                    $query->orWhereRaw("html_code LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($diskName).'%']);
                }
            })
            ->get(['id', 'name', 'active', 'html_code'])
            ->each(function (Ad $ad) use ($diskNames, &$usages): void {
                foreach ($diskNames as $diskName) {
                    if (str_contains((string) $ad->html_code, $diskName)) {
                        $usages[$diskName][] = $this->record(
                            'ad_html_code',
                            'Codice HTML annuncio',
                            'Annuncio',
                            $ad->name,
                            $ad->active ? 'Attivo' : 'Non attivo',
                            route('admin.ads')
                        );
                    }
                }
            });
    }

    /**
     * @param  list<string>  $diskNames
     * @param  array<string, list<array<string, mixed>>>  $usages
     */
    private function scanUserPhotos(array $diskNames, array &$usages): void
    {
        User::query()
            ->whereIn('photo', $diskNames)
            ->get(['id', 'name', 'photo'])
            ->each(function (User $user) use (&$usages): void {
                $usages[$user->photo][] = $this->record(
                    'user_photo',
                    'Foto profilo',
                    'Collaboratore',
                    $user->name,
                    null,
                    route('admin.collaborators.edit', $user)
                );
            });
    }

    /**
     * Category.image memorizza solo il basename: il confronto va fatto sul
     * valore virtuale "categories/{image}" (stessa convenzione gia usata da
     * MediaReferenceService e da Category::getImageUrlAttribute()).
     *
     * @param  list<string>  $diskNames
     * @param  array<string, list<array<string, mixed>>>  $usages
     */
    private function scanCategoryImages(array $diskNames, array &$usages): void
    {
        Category::query()
            ->whereNotNull('image')
            ->get(['id', 'name', 'image', 'is_active'])
            ->each(function (Category $category) use ($diskNames, &$usages): void {
                $virtual = 'categories/'.$category->image;

                if (! in_array($virtual, $diskNames, true)) {
                    return;
                }

                $usages[$virtual][] = $this->record(
                    'category_image',
                    'Copertina categoria',
                    'Categoria',
                    $category->name,
                    $category->is_active ? 'Attiva' : 'Non attiva',
                    route('admin.categories')
                );
            });
    }

    /**
     * Tabella piccola per natura (poche pagine speciali): una lettura
     * completa qui non e una scansione costosa "per card", e la stessa
     * classe di costo di una relazione eager-loaded.
     *
     * @param  list<string>  $diskNames
     * @param  array<string, list<array<string, mixed>>>  $usages
     */
    private function scanSpecialPageContents(array $diskNames, array &$usages): void
    {
        SpecialPage::query()
            ->get(['id', 'slug', 'title', 'content', 'is_active'])
            ->each(function (SpecialPage $page) use ($diskNames, &$usages): void {
                foreach ($this->collectStringLeaves($page->content ?? []) as $leaf) {
                    if (! in_array($leaf['value'], $diskNames, true) || ! $this->isSupportedContentPath($leaf['path'])) {
                        continue;
                    }

                    $isHero = str_starts_with($leaf['path'], 'hero.');

                    $usages[$leaf['value']][] = $this->record(
                        $isHero ? 'special_page_hero' : 'special_page_content',
                        $isHero ? 'Hero' : 'Contenuto pagina',
                        'Pagina speciale',
                        $page->title,
                        $page->is_active ? 'Pubblicata' : 'Non pubblicata',
                        $page->slug === 'turing' ? route('admin.turing') : null
                    );
                }
            });
    }

    private function articleStatusLabel(string $status): string
    {
        return match ($status) {
            'published' => 'Pubblicato',
            'draft' => 'Bozza',
            default => $status,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function record(
        string $type,
        string $usageTypeLabel,
        string $contentType,
        string $title,
        ?string $status,
        ?string $editUrl,
    ): array {
        return [
            'type' => $type,
            'usage_type_label' => $usageTypeLabel,
            'content_type' => $contentType,
            'title' => $title,
            'status' => $status,
            'edit_url' => $editUrl,
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
