<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\SpecialPage;
use App\Models\User;
use App\Services\Concerns\ScansJsonContentLeaves;

class MediaReferenceService
{
    use ScansJsonContentLeaves;

    /**
     * Analizza tutti i riferimenti conosciuti al disk_name attuale di un Media
     * e determina quali possono essere aggiornati in sicurezza verso il
     * nuovo disk_name proposto, quali bloccano lo spostamento e quali sono
     * puramente informativi. Nessuna scrittura: solo lettura.
     *
     * @return array{
     *     updatable_references: list<array<string, mixed>>,
     *     blocking_references: list<array<string, mixed>>,
     *     informational_references: list<array<string, mixed>>,
     *     can_move: bool,
     *     total_usage_count: int,
     * }
     */
    public function preflight(Media $media, string $newDiskName): array
    {
        $old = $media->disk_name;

        $updatable = [];
        $blocking = [];

        $this->scanArticleCoverImages($old, $newDiskName, $updatable);
        $this->scanAdBannerImages($old, $newDiskName, $updatable);
        $this->scanUserPhotos($old, $newDiskName, $updatable, $blocking);
        $this->scanCategoryImages($old, $newDiskName, $updatable, $blocking);
        $this->scanSpecialPageContents($old, $newDiskName, $updatable, $blocking);
        $this->scanFreeTextFields($old, $blocking);
        $this->scanStaticProtectedList($old, $blocking);

        $informational = [];
        if ($updatable === [] && $blocking === []) {
            $informational[] = [
                'type' => 'no_usage',
                'model' => null,
                'record_id' => null,
                'field' => null,
                'description' => 'Nessun riferimento strutturato trovato per questo file.',
                'old_value' => $old,
                'new_value' => null,
                'blocking_reason' => null,
            ];
        }

        return [
            'updatable_references' => $updatable,
            'blocking_references' => $blocking,
            'informational_references' => $informational,
            'can_move' => $blocking === [],
            'total_usage_count' => count($updatable) + count($blocking),
        ];
    }

    private function scanArticleCoverImages(string $old, string $new, array &$updatable): void
    {
        Article::where('cover_image', $old)->get(['id', 'title'])->each(
            function (Article $article) use ($old, $new, &$updatable): void {
                $updatable[] = $this->reference(
                    'article_cover_image',
                    Article::class,
                    $article->id,
                    'cover_image',
                    'Copertina articolo "'.$article->title.'"',
                    $old,
                    $new
                );
            }
        );
    }

    private function scanAdBannerImages(string $old, string $new, array &$updatable): void
    {
        Ad::where('banner_image', $old)->get(['id', 'name'])->each(
            function (Ad $ad) use ($old, $new, &$updatable): void {
                $updatable[] = $this->reference(
                    'ad_banner_image',
                    Ad::class,
                    $ad->id,
                    'banner_image',
                    'Banner pubblicitario "'.$ad->name.'"',
                    $old,
                    $new
                );
            }
        );
    }

    /**
     * User.photo puo provenire da due flussi distinti che scrivono nella
     * stessa colonna: Admin\ProfileController salva un disk_name piatto
     * sotto assets/img (stesso formato di Media.disk_name); Redazione\
     * ProfileController salva invece un percorso relativo al disco
     * "public" di Storage (prefisso "photos/"), fuori dalla radice
     * assets/img. Un match esatto con il prefisso "photos/" e quindi
     * ambiguo per costruzione e va bloccato, non aggiornato.
     */
    private function scanUserPhotos(string $old, string $new, array &$updatable, array &$blocking): void
    {
        User::where('photo', $old)->get(['id', 'name', 'photo'])->each(
            function (User $user) use ($old, $new, &$updatable, &$blocking): void {
                if (str_starts_with($user->photo, 'photos/')) {
                    $blocking[] = $this->reference(
                        'user_photo',
                        User::class,
                        $user->id,
                        'photo',
                        'Foto profilo di "'.$user->name.'"',
                        $old,
                        null,
                        'Formato ambiguo: il valore corrisponde alla convenzione dello storage disk "public", non a quella della Libreria media.'
                    );

                    return;
                }

                $updatable[] = $this->reference(
                    'user_photo',
                    User::class,
                    $user->id,
                    'photo',
                    'Foto profilo di "'.$user->name.'"',
                    $old,
                    $new
                );
            }
        );
    }

    /**
     * Category.image memorizza solo il basename: il prefisso "categories/"
     * e implicito e ricostruito da Category::getImageUrlAttribute() e da
     * Admin\CategoryController. Il confronto va quindi fatto sul valore
     * virtuale "categories/{image}", e l'aggiornamento e sicuro solo se
     * anche la nuova destinazione resta un file diretto sotto categories/
     * (altrimenti il contratto implicito del campo si romperebbe).
     */
    private function scanCategoryImages(string $old, string $new, array &$updatable, array &$blocking): void
    {
        Category::query()->whereNotNull('image')->get(['id', 'name', 'image'])->each(
            function (Category $category) use ($old, $new, &$updatable, &$blocking): void {
                $virtualOld = 'categories/'.$category->image;

                if ($virtualOld !== $old) {
                    return;
                }

                if (dirname($new) !== 'categories') {
                    $blocking[] = $this->reference(
                        'category_image',
                        Category::class,
                        $category->id,
                        'image',
                        'Immagine categoria "'.$category->name.'"',
                        $old,
                        null,
                        'La destinazione non e una posizione diretta sotto "categories/": il campo memorizza solo il basename e perderebbe informazione.'
                    );

                    return;
                }

                $updatable[] = $this->reference(
                    'category_image',
                    Category::class,
                    $category->id,
                    'image',
                    'Immagine categoria "'.$category->name.'"',
                    $old,
                    basename($new)
                );
            }
        );
    }

    private function scanSpecialPageContents(string $old, string $new, array &$updatable, array &$blocking): void
    {
        SpecialPage::query()->get(['id', 'slug', 'content'])->each(
            function (SpecialPage $page) use ($old, $new, &$updatable, &$blocking): void {
                $leaves = $this->collectStringLeaves($page->content ?? []);

                foreach ($leaves as $leaf) {
                    if ($leaf['value'] !== $old) {
                        continue;
                    }

                    if ($this->isSupportedContentPath($leaf['path'])) {
                        $updatable[] = $this->reference(
                            'special_page_content',
                            SpecialPage::class,
                            $page->id,
                            'content',
                            'Contenuto pagina speciale "'.$page->slug.'" ('.$leaf['path'].')',
                            $old,
                            $new,
                            jsonPath: $leaf['path']
                        );
                    } else {
                        $blocking[] = $this->reference(
                            'special_page_content',
                            SpecialPage::class,
                            $page->id,
                            'content',
                            'Contenuto pagina speciale "'.$page->slug.'" ('.$leaf['path'].')',
                            $old,
                            null,
                            'Riferimento trovato in una chiave JSON non censita tra quelle supportate.'
                        );
                    }
                }
            }
        );
    }

    private function scanFreeTextFields(string $old, array &$blocking): void
    {
        foreach ($this->findFreeTextMentions($old) as $mention) {
            $blocking[] = $this->reference(
                $mention['type'],
                $mention['model'],
                $mention['record_id'],
                $mention['field'],
                $mention['description'],
                $old,
                null,
                $mention['field'] === 'body'
                    ? 'Il testo libero (body) non viene modificato automaticamente: potrebbe contenere il nome file o la sua URL.'
                    : 'Il codice HTML libero non viene modificato automaticamente: potrebbe contenere il nome file o la sua URL.'
            );
        }
    }

    /**
     * Cerca $value come sottostringa letterale nei campi di testo libero
     * conosciuti (article.body, ad.html_code) — pubblico (non solo uso
     * interno di scanFreeTextFields()) perche' MediaWebpCleanupService
     * (FASE 12) deve poter fare la stessa ricerca su un file che non ha
     * (o non ha piu') un record Media proprio: unica implementazione di
     * "il nome/percorso potrebbe essere incollato in testo libero", mai
     * due definizioni.
     *
     * @return list<array{type: string, model: class-string, record_id: int, field: string, description: string}>
     */
    public function findFreeTextMentions(string $value): array
    {
        $escaped = $this->escapeLike($value);
        $mentions = [];

        Article::query()
            ->whereRaw("body LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
            ->get(['id', 'title'])
            ->each(function (Article $article) use (&$mentions): void {
                $mentions[] = [
                    'type' => 'article_body',
                    'model' => Article::class,
                    'record_id' => $article->id,
                    'field' => 'body',
                    'description' => 'Testo libero dell\'articolo "'.$article->title.'"',
                ];
            });

        Ad::query()
            ->whereRaw("html_code LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
            ->get(['id', 'name'])
            ->each(function (Ad $ad) use (&$mentions): void {
                $mentions[] = [
                    'type' => 'ad_html_code',
                    'model' => Ad::class,
                    'record_id' => $ad->id,
                    'field' => 'html_code',
                    'description' => 'Codice HTML libero dell\'annuncio "'.$ad->name.'"',
                ];
            });

        return $mentions;
    }

    /**
     * Verifica se $diskName e' referenziato da un qualunque campo
     * strutturato conosciuto (copertina articolo, banner pubblicitario,
     * foto profilo, immagine categoria, contenuto pagina speciale),
     * indipendentemente dal fatto che esista o meno un Media che possiede
     * quello stesso disk_name.
     *
     * Diversa da preflight(): preflight() presuppone un Media reale e
     * confronta il SUO disk_name attuale; questo metodo serve invece
     * MediaWebpCleanupService (FASE 12), che deve valutare un file che,
     * per costruzione, NON ha (piu') un proprio Media (e' un originale
     * gia' migrato). Un confronto per solo nome file tra originale e la
     * sua ipotetica controparte WebP non basta a provare che quel WebP
     * sia stato prodotto DA quell'originale — potrebbero coesistere per
     * puro caso di stesso basename in file diversi — quindi ogni
     * riferimento strutturato residuo al nome dell'originale, anche senza
     * un Media proprietario, deve bloccare la candidatura alla rimozione.
     */
    public function hasAnyStructuredReference(string $diskName): bool
    {
        if (Article::where('cover_image', $diskName)->exists()) {
            return true;
        }

        if (Ad::where('banner_image', $diskName)->exists()) {
            return true;
        }

        if (User::where('photo', $diskName)->exists()) {
            return true;
        }

        if (dirname($diskName) === 'categories' && Category::where('image', basename($diskName))->exists()) {
            return true;
        }

        return SpecialPage::query()
            ->get(['content'])
            ->contains(function (SpecialPage $page) use ($diskName): bool {
                foreach ($this->collectStringLeaves($page->content ?? []) as $leaf) {
                    if ($leaf['value'] === $diskName) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function scanStaticProtectedList(string $old, array &$blocking): void
    {
        if (in_array($old, config('media.protected_disk_names', []), true)) {
            $blocking[] = $this->reference(
                'static_reference',
                null,
                null,
                null,
                'Riferimento statico protetto (config/media.php)',
                $old,
                null,
                'Il disk_name e hardcoded in file versionati del repository (controller, viste, seeder) ed e protetto esplicitamente in config/media.php.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(
        string $type,
        ?string $model,
        ?int $recordId,
        ?string $field,
        string $description,
        string $oldValue,
        ?string $newValue,
        ?string $blockingReason = null,
        ?string $jsonPath = null,
    ): array {
        return [
            'type' => $type,
            'model' => $model,
            'record_id' => $recordId,
            'field' => $field,
            'json_path' => $jsonPath,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'blocking_reason' => $blockingReason,
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
