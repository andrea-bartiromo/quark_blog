<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Services\ArticleViewTrackingService;

class ArticleController extends Controller
{
    public function index()
    {
        return view('notizie', [
            'articles' => Article::published()
                ->with('author')
                ->paginate(12),

            'mostRead' => Article::published()
                ->orderByDesc('views')
                ->limit(5)
                ->get(),
        ]);
    }

    public function category(string $slug)
    {
        $categoryModel = Category::where('slug', $slug)->first();
        $categories = Category::options(false);

        abort_unless($categoryModel || array_key_exists($slug, $categories), 404);

        return view('categoria', [
            'slug' => $slug,
            'categoryModel' => $categoryModel,
            'categoryLabel' => $categoryModel?->name ?? $categories[$slug],
            'categoryDescription' => $categoryModel?->description,
            'categoryImage' => $categoryModel?->image,
            'category' => $slug,

            'articles' => Article::published()
                ->byCategory($slug)
                ->with('author')
                ->paginate(12),

            'mostRead' => Article::published()
                ->orderByDesc('views')
                ->limit(5)
                ->get(),
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::published()
            ->where('slug', $slug)
            ->with('author', 'comments')
            ->firstOrFail();

        $sessionKey = 'article_viewed_'.$article->id;

        if (! session()->has($sessionKey)) {
            app(ArticleViewTrackingService::class)->recordView($article);

            session()->put($sessionKey, true);
        }

        return view('articolo', [
            'article' => $article,

            'related' => $article->related(),

            'mostRead' => Article::published()
                ->orderByDesc('views')
                ->limit(5)
                ->get(),
        ]);
    }
}
