<?php

namespace App\Http\Controllers\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use App\Services\ContentGraph\ContentGraphService;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\ImageService;
use App\Services\MediaRetirementService;
use App\Services\MediaService;
use App\Services\PublicMediaSyncService;
use App\Services\ResponsiveImageVariantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Estende il controller editoriale esistente solo per la discovery per
 * categoria. Le altre azioni (create/store/edit/update/duplicate) restano
 * ereditate senza variazioni.
 */
class ArticleDiscoveryController extends ArticleController
{
    private const PER_PAGE = 25;

    public function __construct(
        ImageService $imageService,
        MediaService $mediaService,
        PublicMediaSyncService $publicMediaSync,
        ArticleLinkSuggestionService $linkSuggestionService,
        private readonly ArticleLinkInsertionService $discoveryLinkInsertionService,
        EditorialQualityChecker $qualityChecker,
        MediaRetirementService $mediaRetirementService,
        ResponsiveImageVariantService $responsiveImageVariants,
        ContentGraphService $contentGraph,
    ) {
        parent::__construct(
            $imageService,
            $mediaService,
            $publicMediaSync,
            $linkSuggestionService,
            $discoveryLinkInsertionService,
            $qualityChecker,
            $mediaRetirementService,
            $responsiveImageVariants,
            $contentGraph,
        );
    }

    public function index(Request $request)
    {
        $searchInput = $request->input('q', '');
        $search = is_string($searchInput)
            ? mb_substr(trim($searchInput), 0, 150)
            : '';

        if ($request->has('q')) {
            $request->query->set('q', $search);
        }

        $status = $request->input('status');
        if (! is_string($status) || ! array_key_exists($status, Article::statusOptions())) {
            $status = null;
        }

        $category = $request->input('category');
        if (! is_string($category) || $category === '' || mb_strlen($category) > 100) {
            $category = null;
        }

        $rawQueryParams = [];
        parse_str((string) $request->server->get('QUERY_STRING', ''), $rawQueryParams);
        $authorInput = $rawQueryParams['author'] ?? null;
        $authorId = null;

        if (is_string($authorInput) && preg_match('/^[1-9][0-9]*$/D', $authorInput) === 1) {
            $validatedAuthorId = filter_var(
                $authorInput,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $authorId = $validatedAuthorId === false ? null : $validatedAuthorId;
        }

        $query = Article::query()->latest()->with('author');

        if ($search !== '') {
            $likeTerm = '%'.$this->escapeLike($search).'%';

            $query->where(function (Builder $query) use ($likeTerm) {
                $query
                    ->whereRaw("title LIKE ? ESCAPE '!'", [$likeTerm])
                    ->orWhereRaw("excerpt LIKE ? ESCAPE '!'", [$likeTerm])
                    ->orWhereRaw("body LIKE ? ESCAPE '!'", [$likeTerm]);
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($category !== null) {
            $query->where(function (Builder $query) use ($category) {
                $query->where('category', $category)
                    ->orWhereHas('secondaryCategories', fn (Builder $secondaryQuery) => $secondaryQuery->where('categories.slug', $category));
            });
        }

        if ($authorId !== null) {
            $query->where('user_id', $authorId);
        }

        $articles = $query->paginate(self::PER_PAGE)->withQueryString();

        if ($articles->total() > 0 && $articles->currentPage() > $articles->lastPage()) {
            $redirectQuery = $request->query();
            unset($redirectQuery['page']);

            if ($articles->lastPage() > 1) {
                $redirectQuery['page'] = $articles->lastPage();
            }

            return redirect()->route('admin.articles', $redirectQuery);
        }

        foreach ($articles as $article) {
            if ($article->isScheduled()) {
                $article->article_links_count = $this->discoveryLinkInsertionService->countArticleLinks((string) $article->body);
            }
        }

        $hasActiveFilters = $search !== '' || $status !== null || $category !== null || $authorId !== null;
        $articlesExistAtAll = ! $articles->isEmpty() || Article::query()->exists();

        return view('admin.articles', [
            'articles' => $articles,
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'authorId' => $authorId,
            'statusOptions' => Article::statusOptions(),
            'categoryOptions' => $this->categoryFilterOptions(),
            'authorOptions' => $this->authorFilterOptions(),
            'hasActiveFilters' => $hasActiveFilters,
            'articlesExistAtAll' => $articlesExistAtAll,
        ]);
    }

    /** @return array<string, string> */
    private function categoryFilterOptions(): array
    {
        $labels = Category::options(false);

        $primarySlugs = Article::query()
            ->select('category')
            ->distinct()
            ->pluck('category');

        $secondarySlugs = Category::query()
            ->whereHas('secondaryArticles')
            ->pluck('slug');

        return $primarySlugs
            ->merge($secondarySlugs)
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $slug) => [$slug => $labels[$slug] ?? $slug])
            ->all();
    }

    /** @return array<int, string> */
    private function authorFilterOptions(): array
    {
        $authorIds = Article::query()->select('user_id')->distinct()->pluck('user_id');

        return User::query()
            ->whereIn('id', $authorIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
