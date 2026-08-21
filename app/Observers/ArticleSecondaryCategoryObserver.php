<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

class ArticleSecondaryCategoryObserver
{
    public function __construct(private readonly Request $request) {}

    public function saved(Article $article): void
    {
        if (! $this->request->routeIs('admin.articles.store', 'admin.articles.update')) {
            return;
        }

        $ids = $this->validatedSecondaryCategoryIds($article);

        $article->belongsToMany(Category::class, 'article_category')
            ->withTimestamps()
            ->sync($ids);
    }

    public function created(Article $article): void
    {
        if (! $this->request->routeIs('admin.articles.duplicate')) {
            return;
        }

        $source = $this->request->route('article');

        if (! $source instanceof Article) {
            return;
        }

        $ids = $source->belongsToMany(Category::class, 'article_category')
            ->pluck('categories.id')
            ->all();

        if ($ids === []) {
            return;
        }

        $article->belongsToMany(Category::class, 'article_category')
            ->withTimestamps()
            ->sync($ids);
    }

    /**
     * La FormRequest applica già i vincoli di prodotto; questo filtro è una
     * seconda barriera difensiva e mantiene l'invariante anche se in futuro
     * il salvataggio Admin cambia implementazione.
     *
     * @return array<int, int>
     */
    private function validatedSecondaryCategoryIds(Article $article): array
    {
        $raw = (array) $this->request->input('secondary_categories', []);

        $ids = collect($raw)
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(2)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Category::query()
            ->active()
            ->whereIn('id', $ids)
            ->where('slug', '!=', $article->category)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
