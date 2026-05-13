<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => 'required|max:100',
            'email' => 'required|email|max:150',
            'oggetto' => 'required|max:120',
            'messaggio' => 'required|min:20|max:2000',
            'privacy' => 'accepted',
        ]);

        $to = env('CONTACT_TO_ADDRESS', config('mail.from.address'));

        try {
            Mail::raw($this->messageBody($data), function ($message) use ($data, $to) {
                $message->to($to)
                    ->replyTo($data['email'], $data['nome'])
                    ->subject('[Quark] Nuovo messaggio: '.$data['oggetto']);
            });

            return redirect()->route('contatti', ['sent' => '1']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'email' => 'Il messaggio non è stato inviato. Errore mail: '.$exception->getMessage(),
            ])->withInput();
        }
    }

    private function messageBody(array $data): string
    {
        return "Nuovo messaggio dal form contatti di Quark\n\n" .
            "Nome: {$data['nome']}\n" .
            "Email: {$data['email']}\n" .
            "Oggetto: {$data['oggetto']}\n\n" .
            "Messaggio:\n{$data['messaggio']}\n\n" .
            "---\nInviato da: " . url('/contatti');
    }
}
