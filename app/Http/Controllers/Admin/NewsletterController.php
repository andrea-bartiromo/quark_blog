<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;

class NewsletterController extends Controller
{
    public function index()
    {
        return view('admin.newsletter', [
            'subscribers' => Newsletter::latest()->paginate(50),
            'total' => Newsletter::count(),
            'confirmed' => Newsletter::where('confirmed', true)->count(),
        ]);
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

        $filename = 'newsletter-illaboratorio-'.now()->format('Ymd-His').'.csv';

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
