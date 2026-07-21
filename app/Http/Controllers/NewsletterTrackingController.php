<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\NewsletterClick;
use App\Models\NewsletterOpen;
use Illuminate\Http\Request;

class NewsletterTrackingController extends Controller
{
    public function open(Request $request, Newsletter $subscriber)
    {
        NewsletterOpen::create([
            'newsletter_id' => $subscriber->id,
            'email' => $subscriber->email,
            'ip_hash' => hash('sha256', $request->ip()),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'opened_at' => now(),
        ]);

        $pixel = base64_decode(
            'R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=='
        );

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function click(Request $request, Newsletter $subscriber, Article $article)
    {
        $url = route('articolo', $article->slug);

        NewsletterClick::create([
            'newsletter_subscriber_id' => $subscriber->id,
            'article_id' => $article->id,
            'email' => $subscriber->email,
            'ip_hash' => hash('sha256', $request->ip()),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'url' => $url,
            'clicked_at' => now(),
        ]);

        return redirect()->away($url);
    }
}
