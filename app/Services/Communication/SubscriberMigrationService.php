<?php

namespace App\Services\Communication;

use App\Models\CommunicationSubscriber;
use App\Models\Newsletter;
use Throwable;

/**
 * Copia gli iscritti dalla tabella `newsletter` (modulo attuale) verso
 * `comm_subscribers` (Sistema Comunicazione), senza mai toccare o eliminare
 * la sorgente. Pensata per essere eseguita più volte in sicurezza: ogni
 * email già presente in comm_subscribers viene saltata, mai duplicata.
 */
class SubscriberMigrationService
{
    /**
     * @return array{copied:int,already_present:int,errors:array<int,array{email:string,message:string}>}
     */
    public function migrate(bool $dryRun = false): array
    {
        $copied = 0;
        $alreadyPresent = 0;
        $errors = [];

        Newsletter::query()->orderBy('id')->chunkById(200, function ($subscribers) use (&$copied, &$alreadyPresent, &$errors, $dryRun) {
            foreach ($subscribers as $subscriber) {
                if (CommunicationSubscriber::where('email', $subscriber->email)->exists()) {
                    $alreadyPresent++;

                    continue;
                }

                if ($dryRun) {
                    $copied++;

                    continue;
                }

                try {
                    CommunicationSubscriber::create([
                        'email' => $subscriber->email,
                        'status' => $subscriber->confirmed
                            ? CommunicationSubscriber::STATUS_CONFIRMED
                            : CommunicationSubscriber::STATUS_PENDING,
                        'token' => $subscriber->token,
                        'unsubscribe_token' => $subscriber->unsubscribe_token,
                        'source' => 'import',
                        // Nessuna data di conferma reale disponibile nello schema
                        // sorgente: resta null piuttosto che essere inventata.
                        'confirmed_at' => null,
                        'created_at' => $subscriber->created_at,
                        'updated_at' => $subscriber->updated_at,
                    ]);

                    $copied++;
                } catch (Throwable $e) {
                    $errors[] = ['email' => $subscriber->email, 'message' => $e->getMessage()];
                }
            }
        });

        return [
            'copied' => $copied,
            'already_present' => $alreadyPresent,
            'errors' => $errors,
        ];
    }
}
