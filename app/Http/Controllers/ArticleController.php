<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index()
    {
        return view('notizie', [
            'articles' => Article::published()->with('author')->paginate(12),
            'mostRead' => Article::published()->orderByDesc('views')->limit(5)->get(),
        ]);
    }

    public function category(string $slug)
    {
        $categories = config('laboratorio.categories');

        abort_unless(array_key_exists($slug, $categories), 404);

        return view('categoria', [
            'slug'     => $slug,
            'categoryLabel' => $categories[$slug],
            'category'      => $slug,
            'articles' => Article::published()->byCategory($slug)->with('author')->paginate(12),
            'mostRead' => Article::published()->orderByDesc('views')->limit(5)->get(),
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::published()->where('slug', $slug)->with('author', 'comments')->firstOrFail();
        $article->incrementViews();

        return view('articolo', [
            'article'  => $article,
            'related'  => $article->related(),
            'mostRead' => Article::published()->orderByDesc('views')->limit(5)->get(),
        ]);
    }
}
