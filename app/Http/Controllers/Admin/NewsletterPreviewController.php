<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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
        $exitCode = Artisan::call('newsletter:send');
        $output = Artisan::output();

        // Prompt 116-120 (150-prompt program): prima di questo controllo,
        // un invio disattivato (NEWSLETTER_SEND_ENABLED=false) o qualunque
        // altro esito diverso da successo mostrava comunque "Newsletter
        // inviata!" all'editor — un falso segnale di successo su
        // un'azione che in realtà non ha inviato nulla.
        if ($exitCode !== Command::SUCCESS) {
            return redirect()->route('admin.newsletter')
                ->with('warning', 'Newsletter NON inviata. '.trim($output));
        }

        return redirect()->route('admin.newsletter')
            ->with('success', 'Newsletter inviata! '.trim($output));
    }
}
