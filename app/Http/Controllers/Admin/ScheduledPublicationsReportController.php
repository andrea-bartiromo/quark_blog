<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EditorialOperations\ScheduledArticlesCertificationService;
use Illuminate\View\View;

/**
 * Cantiere 37 (programma 100-cantieri Kairus). "Report pubblicazioni
 * programmate" — la stessa identica certificazione già prodotta da
 * editorial:scheduled-certification (sola lettura, finestra futura
 * configurabile), mai raggiungibile prima d'ora da una pagina web
 * admin: un editore doveva ricordarsi il comando CLI e passare
 * esplicitamente --days=30 per ottenere la finestra nominata dal
 * titolo di questo cantiere (il default del comando resta 14, invariato
 * per non alterarne il comportamento consolidato). Qui la finestra di
 * 30 giorni è semplicemente il default della pagina.
 */
class ScheduledPublicationsReportController extends Controller
{
    private const DEFAULT_WINDOW_DAYS = 30;

    public function index(ScheduledArticlesCertificationService $report): View
    {
        return view('admin.scheduled-publications-report', [
            'report' => $report->report(now(), self::DEFAULT_WINDOW_DAYS),
            'windowDays' => self::DEFAULT_WINDOW_DAYS,
        ]);
    }
}
