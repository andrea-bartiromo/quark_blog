<?php

namespace App\Services\SocialWorkspace;

use App\Models\Article;
use DateTimeZone;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Puro: converte un input data/ora redazionale (Europe/Rome) nell'istante
 * UTC da salvare, rifiutando esplicitamente gli unici due casi in cui un
 * orario locale non ha un significato univoco — non affidandosi al
 * comportamento silenzioso di default di PHP/Carbon (che normalizza in
 * avanti un orario inesistente e sceglie deterministicamente ma senza
 * avviso un'interpretazione per un orario ambiguo). Scelta conservativa
 * esplicitamente autorizzata: mai indovinare, sempre un errore leggibile
 * che chiede un orario diverso.
 */
class SocialDraftScheduleTimeResolver
{
    /**
     * @throws InvalidArgumentException
     */
    public function toUtc(string $date, string $time): Carbon
    {
        $timezone = new DateTimeZone(Article::EDITORIAL_TIMEZONE);

        $naive = \DateTime::createFromFormat('!Y-m-d H:i', "{$date} {$time}", new DateTimeZone('UTC'));

        if ($naive === false) {
            throw new InvalidArgumentException('Data od ora non valide.');
        }

        $referenceTs = $naive->getTimestamp();

        // Finestra di 4 ore: più che sufficiente, il passaggio ora
        // legale/solare in Europe/Rome sposta l'orologio di 1 ora sola.
        $transitions = $timezone->getTransitions($referenceTs - 14400, $referenceTs + 14400);

        $validCandidates = [];

        foreach ($transitions as $index => $transition) {
            $candidateUtcTs = $referenceTs - $transition['offset'];
            $segmentEnd = $transitions[$index + 1]['ts'] ?? PHP_INT_MAX;

            // Il candidato è valido solo se, applicando l'offset di questa
            // transizione, l'istante UTC risultante ricade davvero nel
            // periodo in cui quella transizione è in vigore — altrimenti è
            // un'ipotesi che non si applica a se stessa.
            if ($candidateUtcTs >= $transition['ts'] && $candidateUtcTs < $segmentEnd) {
                $validCandidates[$candidateUtcTs] = true;
            }
        }

        $count = count($validCandidates);

        if ($count === 0) {
            throw new InvalidArgumentException(
                "L'orario {$date} {$time} non esiste nel fuso orario Europe/Rome (cade nel passaggio all'ora legale/solare). Scegli un orario diverso."
            );
        }

        if ($count > 1) {
            throw new InvalidArgumentException(
                "L'orario {$date} {$time} è ambiguo nel fuso orario Europe/Rome (si ripete al passaggio dall'ora legale all'ora solare). Scegli un orario diverso, non nell'ora ripetuta."
            );
        }

        $utcTimestamp = array_key_first($validCandidates);

        return Carbon::createFromTimestampUTC($utcTimestamp);
    }

    /**
     * Direzione opposta: UTC salvato -> stringa leggibile Europe/Rome, per
     * la presentazione in pagina. Sempre univoca (un istante UTC ha un solo
     * orario locale corrispondente), nessuna eccezione possibile.
     */
    public function toEditorialDisplay(Carbon $utc): Carbon
    {
        return $utc->clone()->timezone(Article::EDITORIAL_TIMEZONE);
    }
}
