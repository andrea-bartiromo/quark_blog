<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NewsletterPreviewController extends Controller
{
    public function preview()
    {
        $topRead = Article::published()
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('views')->limit(3)->get();

        if ($topRead->count() < 3) {
            $topRead = Article::published()->orderByDesc('views')->limit(3)->get();
        }

        $latest = Article::published()
            ->whereNotIn('id', $topRead->pluck('id'))
            ->orderByDesc('published_at')->limit(2)->get();

        $articles = $topRead->merge($latest);

        return view('admin.newsletter-preview', compact('articles'));
    }

    public function send(Request $request)
    {
        // Invoca il comando newsletter:send
        \Illuminate\Support\Facades\Artisan::call('newsletter:send');
        $output = \Illuminate\Support\Facades\Artisan::output();

        return redirect()->route('admin.newsletter')
            ->with('success', 'Newsletter inviata! ' . trim($output));
    }
}