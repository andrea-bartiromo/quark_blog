<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\NewsletterClick;
use App\Models\NewsletterOpen;
use Illuminate\Http\Request;
use Throwable;

class NewsletterTrackingController extends Controller
{
    public function open(Request $request, string $subscriber)
    {
        $subscriberModel = ctype_digit($subscriber)
            ? Newsletter::query()->find((int) $subscriber)
            : null;

        if ($subscriberModel !== null) {
            try {
                NewsletterOpen::create([
                    'newsletter_id' => $subscriberModel->id,
                    'email' => $subscriberModel->email,
                    'ip_hash' => hash('sha256', $request->ip()),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'opened_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
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

    public function click(Request $request, string $subscriber, string $article)
    {
        $articleModel = ctype_digit($article)
            ? Article::query()->find((int) $article)
            : null;

        if ($articleModel === null) {
            return redirect()->route('notizie');
        }

        $url = route('articolo', $articleModel->slug);
        $subscriberModel = ctype_digit($subscriber)
            ? Newsletter::query()->find((int) $subscriber)
            : null;

        if ($subscriberModel !== null) {
            try {
                NewsletterClick::create([
                    'newsletter_subscriber_id' => $subscriberModel->id,
                    'article_id' => $articleModel->id,
                    'email' => $subscriberModel->email,
                    'ip_hash' => hash('sha256', $request->ip()),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'url' => $url,
                    'clicked_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->away($url);
    }
}
