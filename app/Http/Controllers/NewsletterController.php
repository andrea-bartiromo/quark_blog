<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        if ($request->input('website') !== '') {
            return response()->json(['ok' => true]);
        }

        $request->validate([
            'email'   => 'required|email',
            'privacy' => 'accepted',
        ]);

        $subscriber = Newsletter::subscribe($request->input('email'));

        // TODO: inviare email di conferma con link:
        // route('newsletter.confirm', ['token' => $subscriber->token])

        return response()->json([
            'ok'      => true,
            'message' => 'Controlla la tua email per confermare l\'iscrizione.',
        ]);
    }

    public function confirm(Request $request)
    {
        $subscriber = Newsletter::where('token', $request->input('token'))->firstOrFail();
        $subscriber->update(['confirmed' => true, 'token' => null]);

        return view('newsletter-confirmed');
    }
}
