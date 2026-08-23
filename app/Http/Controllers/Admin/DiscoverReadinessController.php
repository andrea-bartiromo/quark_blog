<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Discover\DiscoverReadinessService;
use Illuminate\View\View;

/**
 * Sola lettura: mostra l'audit prerequisiti Google Discover per un
 * articolo (Batch 04B, Mission 7). Nessuna scrittura, nessun punteggio,
 * nessuna promessa di inclusione reale in Discover.
 */
class DiscoverReadinessController extends Controller
{
    public function show(Article $article, DiscoverReadinessService $readiness): View
    {
        return view('admin.discover-readiness.show', [
            'article' => $article,
            'checks' => $readiness->evaluate($article),
        ]);
    }
}
