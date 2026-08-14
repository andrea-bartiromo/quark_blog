<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\NewsletterClick;
use App\Models\NewsletterOpen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsletterTrackingController extends Controller
{
    public function open(Request $request, Newsletter $subscriber)
    {
        try {
            NewsletterOpen::create([
                'newsletter_id' => $subscriber->id,
                'email' => $subscriber->email,
                'ip_hash' => hash('sha256', $request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'opened_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Newsletter open tracking failed.', [
                'newsletter_id' => $subscriber->id,
                'exception' => $e::class,
            ]);
        }

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

        try {
            NewsletterClick::create([
                'newsletter_subscriber_id' => $subscriber->id,
                'article_id' => $article->id,
                'email' => $subscriber->email,
                'ip_hash' => hash('sha256', $request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'url' => $url,
                'clicked_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Newsletter click tracking failed.', [
                'newsletter_id' => $subscriber->id,
                'article_id' => $article->id,
                'exception' => $e::class,
            ]);
        }

        return redirect()->away($url);
    }
}
