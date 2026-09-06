<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NewsletterReconfirmationIneligibleException;
use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use App\Services\NewsletterReconfirmationService;
use Illuminate\Support\Facades\DB;

class NewsletterController extends Controller
{
    public function index()
    {
        $sourceReport = Newsletter::query()
            ->selectRaw("COALESCE(source, 'unknown_legacy') AS source")
            ->selectRaw('COUNT(*) AS signup_count')
            ->selectRaw('SUM(CASE WHEN confirmed = 1 THEN 1 ELSE 0 END) AS confirmed_count')
            ->groupBy(DB::raw("COALESCE(source, 'unknown_legacy')"))
            ->orderBy('source')
            ->get();

        return view('admin.newsletter', [
            'subscribers' => Newsletter::with('reconfirmations')->latest()->paginate(50),
            'total' => Newsletter::count(),
            'confirmed' => Newsletter::where('confirmed', true)->count(),
            'sourceReport' => $sourceReport,
            'maxReconfirmationAttempts' => (int) config('newsletter.reconfirmation.max_attempts'),
        ]);
    }

    /**
     * Invia manualmente UN sollecito di riconferma a UN iscritto
     * pendente — mai un invio massivo, mai automatico. L'eleggibilità
     * (già confermato / tentativi esauriti / troppo presto dall'ultimo
     * invio) è decisa interamente da NewsletterReconfirmationService:
     * qui si traduce solo l'esito in un messaggio onesto per l'editor.
     */
    public function sendReconfirmation(Newsletter $newsletter, NewsletterReconfirmationService $service)
    {
        try {
            $service->send($newsletter);
        } catch (NewsletterReconfirmationIneligibleException $e) {
            return back()->with('error', match ($e->reason) {
                NewsletterReconfirmationIneligibleException::ALREADY_CONFIRMED => 'Questo iscritto ha già confermato: nessun sollecito necessario.',
                NewsletterReconfirmationIneligibleException::MAX_ATTEMPTS_REACHED => 'Raggiunto il numero massimo di solleciti per questo iscritto.',
                NewsletterReconfirmationIneligibleException::COOLDOWN_ACTIVE => 'È stato inviato un sollecito di recente: attendi prima di reinviarlo.',
                default => 'Impossibile inviare il sollecito di riconferma.',
            });
        }

        return back()->with('success', 'Email di riconferma inviata a '.$newsletter->email.'.');
    }

    /**
     * Rimuove i pendenti a cui è già stato dato almeno un sollecito di
     * riconferma e che non hanno risposto entro la scadenza — la stessa
     * regola applicata dal comando di pulizia schedulato
     * (newsletter:reconfirmation-cleanup), qui disponibile anche su
     * richiesta manuale dell'editor.
     */
    public function cleanupExpiredPending(NewsletterReconfirmationService $service)
    {
        $deleted = $service->deleteExpiredPending();

        return back()->with('success', $deleted === 0
            ? 'Nessun pendente scaduto da rimuovere.'
            : $deleted.' '.($deleted === 1 ? 'iscritto pendente scaduto rimosso.' : 'iscritti pendenti scaduti rimossi.'));
    }

    public function export()
    {
        $subscribers = Newsletter::where('confirmed', true)
            ->orderBy('created_at')
            ->get(['email', 'confirmed', 'created_at']);

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8 per Excel
        $csv .= "Email,Stato,Data iscrizione\n";

        foreach ($subscribers as $s) {
            $csv .= sprintf(
                '"%s","%s","%s"'."\n",
                str_replace('"', '""', $s->email),
                $s->confirmed ? 'Confermato' : 'Non confermato',
                $s->created_at->format('d/m/Y H:i')
            );
        }

        $filename = 'newsletter-kairus-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function destroy(Newsletter $newsletter)
    {
        $newsletter->delete();

        return back()->with('success', 'Iscritto rimosso.');
    }
}
