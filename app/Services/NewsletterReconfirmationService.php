<?php

namespace App\Services;

use App\Exceptions\NewsletterReconfirmationIneligibleException;
use App\Mail\NewsletterInitialConfirmationMail;
use App\Mail\NewsletterReconfirmationMail;
use App\Models\Newsletter;
use App\Models\NewsletterConsentEvent;
use App\Models\NewsletterReconfirmation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Ciclo canonico di double opt-in.
 *
 * newsletter.confirmed è l'unico stato di consenso; newsletter_reconfirmations
 * conserva i token monouso e newsletter_consent_events è l'audit append-only.
 * In ogni errore il processore non invia né elimina: registra l'anomalia.
 */
class NewsletterReconfirmationService
{
    public function sendInitialConfirmation(Newsletter $subscriber): bool
    {
        return DB::transaction(function () use ($subscriber) {
            $locked = Newsletter::query()->lockForUpdate()->find($subscriber->id);

            if (! $locked || $locked->confirmed || ! is_string($locked->token) || $locked->token === '') {
                return false;
            }

            try {
                Mail::to($locked->email)->send(new NewsletterInitialConfirmationMail(
                    $locked,
                    route('newsletter.confirm', ['token' => $locked->token]),
                    route('newsletter.unsubscribe', ['token' => $locked->unsubscribe_token]),
                ));
            } catch (\Throwable $exception) {
                $this->audit($locked, NewsletterConsentEvent::SEND_FAILED, null, ['stage' => 'initial']);

                Log::warning('Newsletter initial confirmation email non inviata.', [
                    'newsletter_id' => $locked->id,
                    'exception' => $exception->getMessage(),
                ]);

                return false;
            }

            $this->audit($locked, NewsletterConsentEvent::INITIAL_CONFIRMATION_SENT);

            return true;
        });
    }

    /**
     * Invio manuale editoriale: massimo tre promemoria, mai più di uno ogni
     * 24 ore. Il processore automatico impone invece la cadenza di 10 giorni.
     */
    public function send(Newsletter $subscriber, bool $automatic = false): ?NewsletterReconfirmation
    {
        return DB::transaction(function () use ($subscriber, $automatic) {
            $locked = Newsletter::query()->lockForUpdate()->find($subscriber->id);

            if (! $locked || $locked->confirmed) {
                throw new NewsletterReconfirmationIneligibleException(
                    NewsletterReconfirmationIneligibleException::ALREADY_CONFIRMED
                );
            }

            $attempts = $locked->reconfirmations()->count();
            $maxAttempts = (int) config('newsletter.reconfirmation.max_attempts');

            if ($attempts >= $maxAttempts) {
                throw new NewsletterReconfirmationIneligibleException(
                    NewsletterReconfirmationIneligibleException::MAX_ATTEMPTS_REACHED
                );
            }

            $last = $locked->reconfirmations()->latest('sent_at')->first();
            $interval = $automatic
                ? (int) config('newsletter.reconfirmation.reminder_interval_days') * 24
                : (int) config('newsletter.reconfirmation.cooldown_hours');

            if ($last && $last->sent_at->clone()->addHours($interval)->isFuture()) {
                throw new NewsletterReconfirmationIneligibleException(
                    NewsletterReconfirmationIneligibleException::COOLDOWN_ACTIVE
                );
            }

            $token = Str::random(64);
            $expiresAfterDays = (int) config('newsletter.reconfirmation.expires_after_days');
            $attempt = $attempts + 1;

            // Il lock rimane anche mentre il trasporto Mail accetta il
            // messaggio: due esecuzioni concorrenti non possono emettere
            // lo stesso livello. Se il trasporto solleva, la transazione
            // viene mantenuta senza record di invio e l'evento è auditato.
            try {
                Mail::to($locked->email)->send(new NewsletterReconfirmationMail(
                    $locked,
                    route('newsletter.reconfirm', ['token' => $token]),
                    $expiresAfterDays,
                    $attempt,
                ));
            } catch (\Throwable $exception) {
                $this->audit($locked, NewsletterConsentEvent::SEND_FAILED, $attempt, [
                    'stage' => $automatic ? 'automatic_reminder' : 'manual_reminder',
                ]);

                Log::warning('Newsletter reconfirmation email non inviata.', [
                    'newsletter_id' => $locked->id,
                    'attempt' => $attempt,
                    'exception' => $exception->getMessage(),
                ]);

                return null;
            }

            $locked->reconfirmations()->unconfirmed()->update(['expires_at' => now()]);

            $record = $locked->reconfirmations()->create([
                'token' => $token,
                'sent_at' => now(),
                'expires_at' => now()->addDays($expiresAfterDays),
            ]);

            $this->audit($locked, NewsletterConsentEvent::REMINDER_SENT, $attempt, [
                'automatic' => $automatic,
            ]);

            return $record;
        });
    }

    public function confirm(string $token): ?Newsletter
    {
        return DB::transaction(function () use ($token) {
            $reconfirmation = NewsletterReconfirmation::where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $reconfirmation || $reconfirmation->isConfirmed() || $reconfirmation->isExpired()) {
                return null;
            }

            $subscriber = Newsletter::query()->lockForUpdate()->find($reconfirmation->newsletter_id);

            if (! $subscriber || $subscriber->confirmed) {
                return null;
            }

            $reconfirmation->update(['confirmed_at' => now()]);
            $subscriber->update(['confirmed' => true, 'token' => null]);
            $this->audit($subscriber, NewsletterConsentEvent::CONFIRMED, $subscriber->reconfirmations()->count(), [
                'via' => 'reminder',
            ]);

            return $subscriber;
        });
    }

    public function confirmInitial(string $token): ?Newsletter
    {
        return DB::transaction(function () use ($token) {
            $subscriber = Newsletter::query()->where('token', $token)->lockForUpdate()->first();

            if (! $subscriber || $subscriber->confirmed) {
                return null;
            }

            $subscriber->update(['confirmed' => true, 'token' => null]);
            $this->audit($subscriber, NewsletterConsentEvent::CONFIRMED, $subscriber->reconfirmations()->count(), [
                'via' => 'initial',
            ]);

            return $subscriber;
        });
    }

    /**
     * Entry point dell'automazione. Restituisce contatori e non esegue
     * scritture né Mail con --dry-run.
     */
    public function process(bool $dryRun = false): array
    {
        $result = ['reminders' => 0, 'deletions' => 0, 'skipped' => 0, 'failures' => 0];

        if (! config('newsletter.reconfirmation.automation_enabled')) {
            return $result;
        }

        Newsletter::pending()->orderBy('id')->pluck('id')->each(function (int $id) use (&$result, $dryRun): void {
            $outcome = $this->processSubscriber($id, $dryRun);

            if (array_key_exists($outcome, $result)) {
                $result[$outcome]++;
            }
        });

        return $result;
    }

    private function processSubscriber(int $subscriberId, bool $dryRun): string
    {
        return DB::transaction(function () use ($subscriberId, $dryRun): string {
            $subscriber = Newsletter::query()->lockForUpdate()->find($subscriberId);

            if (! $subscriber || $subscriber->confirmed) {
                return 'skipped';
            }

            $initialDueAt = $subscriber->created_at->clone()
                ->addDays((int) config('newsletter.reconfirmation.initial_wait_days'));
            $attempts = $subscriber->reconfirmations()->orderBy('sent_at')->get();
            $count = $attempts->count();

            if ($count === 0) {
                if ($initialDueAt->isFuture()) {
                    return 'skipped';
                }

                if ($dryRun) {
                    return 'reminders';
                }

                try {
                    return $this->send($subscriber, true) ? 'reminders' : 'failures';
                } catch (NewsletterReconfirmationIneligibleException) {
                    return 'skipped';
                }
            }

            $last = $attempts->last();
            $nextDueAt = $last->sent_at->clone()
                ->addDays((int) config('newsletter.reconfirmation.reminder_interval_days'));

            if ($count < (int) config('newsletter.reconfirmation.max_attempts')) {
                if ($nextDueAt->isFuture()) {
                    return 'skipped';
                }

                if ($dryRun) {
                    return 'reminders';
                }

                try {
                    return $this->send($subscriber, true) ? 'reminders' : 'failures';
                } catch (NewsletterReconfirmationIneligibleException) {
                    return 'skipped';
                }
            }

            $deleteDueAt = $last->sent_at->clone()
                ->addDays((int) config('newsletter.reconfirmation.delete_after_last_reminder_days'));

            if ($deleteDueAt->isFuture()) {
                return 'skipped';
            }

            if ($dryRun) {
                return 'deletions';
            }

            $this->audit($subscriber, NewsletterConsentEvent::DELETED_UNCONFIRMED, $count, [
                'reason' => 'three_reminders_without_confirmation',
            ]);
            $subscriber->delete();

            return 'deletions';
        });
    }

    public function eligibleForExpiredCleanup(): Collection
    {
        return Newsletter::pending()
            ->whereHas('reconfirmations', function ($query): void {
                $query->havingRaw('COUNT(*) >= ?', [(int) config('newsletter.reconfirmation.max_attempts')]);
            })
            ->pluck('id');
    }

    /**
     * Compatibilità del comando legacy: ora delega al processore, quindi
     * non può cancellare prima della quarta soglia (giorno 40).
     */
    public function deleteExpiredPending(): int
    {
        return $this->process(false)['deletions'];
    }

    private function audit(Newsletter $subscriber, string $event, ?int $attempt = null, array $metadata = []): void
    {
        NewsletterConsentEvent::create([
            'newsletter_id' => $subscriber->id,
            'email_hash' => hash_hmac('sha256', mb_strtolower($subscriber->email), (string) config('app.key')),
            'event_type' => $event,
            'attempt_number' => $attempt,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
