<?php

namespace App\Http\Controllers\Redazione;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $myArticles = Article::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $stats = [
            'total' => Article::where('user_id', $user->id)->count(),
            'published' => Article::where('user_id', $user->id)->where('status', 'published')->count(),
            'review' => Article::where('user_id', $user->id)->where('status', 'review')->count(),
            'draft' => Article::where('user_id', $user->id)->where('status', 'draft')->count(),
            'views' => Article::where('user_id', $user->id)->sum('views'),
            'comments' => Comment::whereIn('article_id',
                Article::where('user_id', $user->id)->pluck('id')
            )->where('status', 'approved')->count(),
        ];

        return view('redazione.dashboard', compact('myArticles', 'stats'));
    }
}
