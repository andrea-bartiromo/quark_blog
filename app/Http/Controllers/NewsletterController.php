<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use App\Services\Telemetry\EditorialContinuityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        // Honeypot anti-bot
        if ($request->input('website') !== '' && $request->input('website') !== null) {
            return redirect('/');
        }

        $request->validate([
            'email' => [
                'required',
                'max:150',
                // Guardia esplicita, indipendente dalla versione di
                // laravel/framework installata: la regola 'email' rifiuta
                // CR/LF solo a partire dal fix di GHSA-5vg9-5847-vvmq
                // (>=13.10.0/<=12.60.0, vedi ValidatesAttributes::
                // validateEmail()) — un local-part quoted contenente CR/LF
                // grezzi è altrimenti sintatticamente valido per il parser
                // RFC822 e passerebbe come indirizzo "valido". Qui il
                // rifiuto non dipende dalla riga esatta del vendor
                // risolta da composer.lock su una data macchina.
                function ($attribute, $value, $fail) {
                    if (is_string($value) && preg_match('/[\r\n]/', $value) === 1) {
                        $fail('The :attribute field must be a valid email address.');
                    }
                },
                'email',
            ],
            'source' => ['nullable', 'string', 'in:'.implode(',', Newsletter::SOURCES)],
        ]);

        $subscriber = Newsletter::subscribe($request->input('email'), $request->input('source'));

        // Measurement Closeout (Missione 1): "iscrizione Newsletter" e
        // "sorgente della sessione" figuravano nella matrice degli eventi da
        // misurare, ma nessun producer li correlava — Newsletter::source
        // registra solo IL PUNTO DELLA PAGINA da cui si è iscritto (popup,
        // sidebar...), mai il canale di provenienza della visita. Questo
        // evento aggiunge la seconda dimensione senza toccare la prima.
        //
        // Nessun dato dell'iscritto viene passato: né l'id, né l'email, né i
        // token. Il fatto editoriale da misurare è "una sessione con questa
        // sorgente ha convertito", non chi fosse.
        app(EditorialContinuityRecorder::class)->recordNewsletterSubscription();

        // Invia email di conferma
        try {
            Mail::send([], [], function ($message) use ($subscriber) {
                $confirmUrl = route('newsletter.confirm', ['token' => $subscriber->token]);
                $unsubscribeUrl = route('newsletter.unsubscribe', ['token' => $subscriber->unsubscribe_token]);

                $message->to($subscriber->email)
                    ->subject('🧪 Un ultimo passo — conferma la tua iscrizione a Kairus')
                    ->html("
                            <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:2rem;background:#ffffff;'>

                                <div style='text-align:center;margin-bottom:2rem;'>
                                    <div style='width:64px;height:64px;background:#0d9488;border-radius:50%;
                                                display:inline-flex;align-items:center;justify-content:center;
                                                font-size:1.8rem;margin-bottom:1rem;'>🧪</div>
                                    <h1 style='font-size:1.6rem;color:#111827;margin:0 0 .25rem;font-weight:900;'>Kairus.</h1>
                                    <p style='color:#6b7280;font-size:.82rem;margin:0;'>La scienza spiegata come si deve</p>
                                </div>

                                <h2 style='font-size:1.2rem;color:#111827;margin-bottom:.75rem;'>Ci sei quasi! 🎉</h2>

                                <p style='color:#374151;line-height:1.7;margin-bottom:1rem;'>
                                    Ciao! Hai appena fatto una delle cose più intelligenti della settimana:
                                    iscriverti a Kairus. Ogni settimana riceverai una selezione dei migliori
                                    articoli scientifici — scritti per chi vuole capire davvero come funziona il mondo.
                                </p>

                                <p style='color:#374151;line-height:1.7;margin-bottom:1.5rem;'>
                                    <strong>Un solo passo prima di iniziare:</strong> clicca il pulsante qui sotto
                                    per confermare la tua email. Ci vogliono 2 secondi.
                                </p>

                                <div style='text-align:center;margin-bottom:1.5rem;'>
                                    <a href='{$confirmUrl}'
                                       style='display:inline-block;background:#0d9488;color:#fff;
                                              padding:.85rem 2rem;border-radius:8px;text-decoration:none;
                                              font-weight:700;font-size:1rem;'>
                                        ✅ Sì, voglio ricevere Kairus
                                    </a>
                                </div>

                                <div style='background:#f0fdfa;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.5rem;'>
                                    <p style='margin:0;color:#0f766e;font-size:.82rem;font-weight:600;'>
                                        🔬 Cosa riceverai ogni settimana
                                    </p>
                                    <ul style='margin:.5rem 0 0;padding-left:1.2rem;color:#374151;font-size:.82rem;line-height:1.7;'>
                                        <li>I migliori articoli scientifici selezionati dalla redazione</li>
                                        <li>Notizie su fisica, spazio, salute e tecnologia</li>
                                        <li>Zero spam — solo contenuti che valgono il tuo tempo</li>
                                    </ul>
                                </div>

                                <hr style='border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0;'>

                                <p style='color:#9ca3af;font-size:.72rem;text-align:center;margin:0;line-height:1.6;'>
                                    Se non hai richiesto questa iscrizione, ignora questa email. Non riceverai nulla.<br>
                                    Oppure <a href='{$unsubscribeUrl}' style='color:#9ca3af;'>clicca qui per non ricevere altre email</a>.
                                </p>
                            </div>
                        ");
            });
        } catch (\Exception $e) {
            \Log::warning('Newsletter email non inviata: '.$e->getMessage());
        }

        return redirect('/?newsletter=ok');
    }

    public function confirm(Request $request)
    {
        $token = $request->query('token');

        if (! is_string($token) || trim($token) === '') {
            abort(404);
        }

        $subscriber = Newsletter::where('token', trim($token))->firstOrFail();

        $subscriber->update([
            'confirmed' => true,
            'token' => null,
        ]);

        return view('newsletter-confirmed');
    }

    public function unsubscribe(Request $request)
    {
        $token = $request->input('token');

        // Un token assente/vuoto non deve mai corrispondere a un eventuale
        // iscritto legacy con unsubscribe_token nullo (Newsletter::where()
        // tratterebbe altrimenti un valore null come una whereNull()).
        $subscriber = $token ? Newsletter::where('unsubscribe_token', $token)->first() : null;

        if (! $subscriber) {
            return view('newsletter-unsubscribed', ['notFound' => true]);
        }

        $subscriber->delete();

        return view('newsletter-unsubscribed', ['notFound' => false]);
    }
}
