<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use App\Services\NewsletterReconfirmationService;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request, NewsletterReconfirmationService $service)
    {
        if ($request->input('website') !== '' && $request->input('website') !== null) {
            return redirect('/');
        }

        $request->validate([
            'email' => [
                'required', 'max:150',
                function ($attribute, $value, $fail): void {
                    if (is_string($value) && preg_match('/[\r\n]/', $value) === 1) {
                        $fail('The :attribute field must be a valid email address.');
                    }
                },
                'email',
            ],
            'source' => ['nullable', 'string', 'in:'.implode(',', Newsletter::SOURCES)],
        ]);

        $subscriber = Newsletter::subscribe($request->string('email')->toString(), $request->input('source'));

        if (! $subscriber->confirmed) {
            $service->sendInitialConfirmation($subscriber);
        }

        // Risposta neutra: non rivela se l'indirizzo fosse già attivo.
        return redirect('/?newsletter=ok');
    }

    /**
     * Reinvio pubblico, neutro e limitato a un invio al giorno per i soli
     * pending. Per indirizzi assenti o già attivi restituisce lo stesso
     * redirect senza enumerazione.
     */
    public function resendConfirmation(Request $request, NewsletterReconfirmationService $service)
    {
        $request->validate([
            'email' => ['required', 'max:150', 'email'],
        ]);

        $subscriber = Newsletter::where('email', $request->string('email')->toString())
            ->where('confirmed', false)
            ->first();

        if ($subscriber) {
            $last = $subscriber->consentEvents()
                ->where('event_type', \App\Models\NewsletterConsentEvent::INITIAL_CONFIRMATION_SENT)
                ->latest('occurred_at')
                ->first();

            $cooldown = (int) config('newsletter.reconfirmation.manual_resend_cooldown_hours');

            if (! $last || ! $last->occurred_at->clone()->addHours($cooldown)->isFuture()) {
                $subscriber->update(['token' => \Illuminate\Support\Str::random(64)]);
                $service->sendInitialConfirmation($subscriber->fresh());
            }
        }

        return back()->with('newsletter_resend', true);
    }

    public function confirm(Request $request, NewsletterReconfirmationService $service)
    {
        $token = $request->query('token');

        if (! is_string($token) || trim($token) === '') {
            abort(404);
        }

        $subscriber = $service->confirmInitial(trim($token));

        if (! $subscriber) {
            abort(404);
        }

        return view('newsletter-confirmed');
    }

    public function reconfirm(Request $request, NewsletterReconfirmationService $service)
    {
        $token = $request->query('token');

        if (! is_string($token) || trim($token) === '') {
            return view('newsletter-reconfirmed', ['confirmed' => false]);
        }

        return view('newsletter-reconfirmed', ['confirmed' => $service->confirm(trim($token)) !== null]);
    }

    public function unsubscribe(Request $request)
    {
        $token = $request->input('token');
        $subscriber = $token ? Newsletter::where('unsubscribe_token', $token)->first() : null;

        if (! $subscriber) {
            return view('newsletter-unsubscribed', ['notFound' => true]);
        }

        $subscriber->delete();

        return view('newsletter-unsubscribed', ['notFound' => false]);
    }
}
