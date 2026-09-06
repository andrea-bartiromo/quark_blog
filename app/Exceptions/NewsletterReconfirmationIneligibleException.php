<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sollevata da NewsletterReconfirmationService::send() quando un
 * iscritto pendente non è idoneo a ricevere un altro sollecito di
 * riconferma in questo momento. $reason è un codice stabile (mai il
 * messaggio tradotto) così il chiamante (controller admin) può mostrare
 * un messaggio onesto e specifico invece di un generico "errore" — non
 * si tratta mai di un fallimento tecnico, ma di un limite applicato di
 * proposito (già confermato, tentativi esauriti, o troppo presto dopo
 * l'ultimo invio).
 */
class NewsletterReconfirmationIneligibleException extends RuntimeException
{
    public const ALREADY_CONFIRMED = 'already_confirmed';

    public const MAX_ATTEMPTS_REACHED = 'max_attempts_reached';

    public const COOLDOWN_ACTIVE = 'cooldown_active';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
