<?php

namespace App\Services;

use App\Exceptions\NewsletterReconfirmationIneligibleException;
use App\Mail\NewsletterReconfirmationMail;
use App\Models\Newsletter;
use App\Models\NewsletterReconfirmation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Recupero prudente degli iscritti pendenti (confirmed=false). Ogni
 * metodo qui è raggiungibile SOLO da un'azione admin esplicita
 * (Admin\NewsletterController) o da un comando di pulizia schedulato —
 * mai da un hook automatico che riattiva un indirizzo non confermato.
 *
 * Regola invariante su tutta questa classe: nessun metodo qui tocca mai
 * Newsletter::subscribe()/NewsletterController::confirm() (il double
 * opt-in originale) o la colonna newsletter.token — un iscritto pendente
 * "nuovo" (mai sollecitato) e uno "risollecitato" restano indistinguibili
 * per quel flusso, esattamente come prima di questa funzionalità.
 */
class NewsletterReconfirmationService
{
    public function send(Newsletter $subscriber): NewsletterReconfirmation
    {
        if ($subscriber->confirmed) {
            throw new NewsletterReconfirmationIneligibleException(
                NewsletterReconfirmationIneligibleException::ALREADY_CONFIRMED
            );
        }

        $maxAttempts = (int) config('newsletter.reconfirmation.max_attempts');
        $attemptsSoFar = $subscriber->reconfirmations()->count();

        if ($attemptsSoFar >= $maxAttempts) {
            throw new NewsletterReconfirmationIneligibleException(
                NewsletterReconfirmationIneligibleException::MAX_ATTEMPTS_REACHED
            );
        }

        $lastAttempt = $subscriber->reconfirmations()->latest('sent_at')->first();

        if ($lastAttempt) {
            $cooldownHours = (int) config('newsletter.reconfirmation.cooldown_hours');
            $cooldownEndsAt = $lastAttempt->sent_at->clone()->addHours($cooldownHours);

            if ($cooldownEndsAt->isFuture()) {
                throw new NewsletterReconfirmationIneligibleException(
                    NewsletterReconfirmationIneligibleException::COOLDOWN_ACTIVE
                );
            }
        }

        $expiresAfterDays = (int) config('newsletter.reconfirmation.expires_after_days');

        $reconfirmation = DB::transaction(function () use ($subscriber, $expiresAfterDays) {
            // Un nuovo invio invalida ogni link precedente ancora non
            // consumato: al più un solo link di riconferma è mai valido
            // per lo stesso iscritto in un dato momento — evita che un
            // vecchio link riesumato da una casella di posta confermi
            // ancora dopo che ne è stato inviato uno più recente.
            $subscriber->reconfirmations()->unconfirmed()->update(['expires_at' => now()]);

            return $subscriber->reconfirmations()->create([
                'token' => Str::random(64),
                'sent_at' => now(),
                'expires_at' => now()->addDays($expiresAfterDays),
            ]);
        });

        $confirmUrl = route('newsletter.reconfirm', ['token' => $reconfirmation->token]);

        try {
            Mail::to($subscriber->email)->send(
                new NewsletterReconfirmationMail($subscriber, $confirmUrl, $expiresAfterDays)
            );
        } catch (\Exception $e) {
            Log::warning('Newsletter reconfirmation email non inviata: '.$e->getMessage());
        }

        return $reconfirmation;
    }

    /**
     * Consuma un token di riconferma. Idempotente per costruzione: un
     * token già confermato o scaduto restituisce null senza alcun
     * effetto — visitare due volte lo stesso link (doppio clic, email
     * client che pre-carica i link) non causa una doppia conferma né un
     * errore.
     */
    public function confirm(string $token): ?Newsletter
    {
        return DB::transaction(function () use ($token) {
            $reconfirmation = NewsletterReconfirmation::where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $reconfirmation || $reconfirmation->isConfirmed() || $reconfirmation->isExpired()) {
                return null;
            }

            $subscriber = $reconfirmation->newsletter;

            // Riga orfana (newsletter cancellata dopo l'invio) o iscritto
            // già confermato per un'altra via nel frattempo: nessuna
            // azione, mai una riattivazione implicita.
            if (! $subscriber || $subscriber->confirmed) {
                return null;
            }

            $reconfirmation->update(['confirmed_at' => now()]);
            $subscriber->update(['confirmed' => true, 'token' => null]);

            return $subscriber;
        });
    }

    /**
     * ID degli iscritti pendenti eleggibili per la pulizia: a cui è stato
     * dato almeno un sollecito di riconferma e che non hanno mai risposto
     * in tempo — mai un pendente che non è mai stato sollecitato (a quello
     * va prima offerta la possibilità di riconfermare, non cancellato a
     * priori). Nessun effetto collaterale: sola lettura, condivisa da
     * deleteExpiredPending() e da qualunque anteprima/dry-run che debba
     * mostrare lo stesso insieme senza cancellare nulla.
     */
    public function eligibleForExpiredCleanup(): Collection
    {
        return Newsletter::query()
            ->pending()
            ->whereHas('reconfirmations')
            ->whereDoesntHave('reconfirmations', function ($query) {
                $query->unconfirmed()->where('expires_at', '>=', now());
            })
            ->pluck('id');
    }

    /**
     * Elimina gli iscritti pendenti eleggibili (vedi
     * eligibleForExpiredCleanup()). Idempotente per costruzione: una
     * seconda chiamata, di seguito o in una schedulazione sovrapposta,
     * non trova più nulla di eleggibile e non cancella nulla — non solo
     * withoutOverlapping() a livello di scheduler, ma la query stessa non
     * ha effetto su righe già rimosse.
     */
    public function deleteExpiredPending(): int
    {
        $eligibleIds = $this->eligibleForExpiredCleanup();

        if ($eligibleIds->isEmpty()) {
            return 0;
        }

        $deleted = Newsletter::whereIn('id', $eligibleIds)->delete();

        // Traccia dell'evento di cancellazione (mai l'indirizzo email, solo
        // l'ID interno) — la sola prova che resta di QUALI righe sono state
        // rimosse, dato che la cancellazione stessa non è ricostruibile da
        // un rollback della migration (che rimuove solo la tabella di audit
        // newsletter_reconfirmations, non ripristina le righe di newsletter
        // già cancellate). Vedi docs/NEWSLETTER_RECONFIRMATION_CLEANUP_RUNBOOK.md.
        Log::info('Rimozione iscritti newsletter pendenti scaduti.', [
            'subscriber_ids' => $eligibleIds->all(),
            'count' => $deleted,
        ]);

        return $deleted;
    }
}
