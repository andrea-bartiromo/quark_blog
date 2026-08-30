<?php

namespace App\Services\Measurement;

use App\Models\Article;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Measurement Closeout (Missione 3) — finestra temporale ESPLICITA.
 *
 * Ogni servizio di misura di questo gruppo accetta una MeasurementWindow, mai
 * due date sciolte e mai un default implicito "da sempre". Il motivo è che
 * due numeri calcolati su finestre diverse non sono confrontabili, e senza un
 * oggetto che porti con sé la finestra la dashboard finirebbe per mostrarli
 * accanto come se lo fossero.
 *
 * TIMEZONE. I confini sono definiti nel giorno editoriale Europe/Rome
 * (Article::EDITORIAL_TIMEZONE, la stessa costante già usata da
 * ArticleViewTrackingService per i bucket giornalieri) e convertiti in UTC
 * per il confronto con occurred_at. Il confine inferiore è INCLUSIVO
 * (00:00:00.000 del primo giorno), quello superiore ESCLUSIVO (00:00:00.000
 * del giorno successivo all'ultimo): così un evento a 23:59:59 dell'ultimo
 * giorno rientra e nessun evento può cadere in due finestre adiacenti.
 */
final class MeasurementWindow
{
    /**
     * Tetto massimo sull'ampiezza della finestra. Non è una scelta estetica:
     * ogni query di questo gruppo scandisce un indice su occurred_at, e una
     * finestra illimitata renderebbe il costo della dashboard proporzionale
     * alla vita del sito invece che al periodo osservato. 366 giorni coprono
     * un anno editoriale completo (bisestile incluso).
     */
    public const MAX_DAYS = 366;

    private function __construct(
        public readonly CarbonImmutable $startInclusive,
        public readonly CarbonImmutable $endExclusive,
        public readonly string $startDate,
        public readonly string $endDate,
    ) {}

    /**
     * @param  string  $startDate  Y-m-d nel giorno editoriale Europe/Rome.
     * @param  string  $endDate  Y-m-d INCLUSIVO nel giorno editoriale Europe/Rome.
     *
     * @throws InvalidArgumentException su date malformate, invertite o su una
     *                                  finestra oltre MAX_DAYS.
     */
    public static function fromDates(string $startDate, string $endDate): self
    {
        $start = self::parseEditorialDate($startDate);
        $end = self::parseEditorialDate($endDate);

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('La data di fine non può precedere la data di inizio.');
        }

        // +1 perché endDate è inclusivo: 01→01 è una finestra di 1 giorno.
        $days = $start->diffInDays($end) + 1;

        if ($days > self::MAX_DAYS) {
            throw new InvalidArgumentException(
                'Intervallo troppo ampio: massimo '.self::MAX_DAYS.' giorni, richiesti '.$days.'.'
            );
        }

        return new self(
            $start->utc(),
            $end->addDay()->utc(),
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        );
    }

    /**
     * Ultimi N giorni editoriali INCLUSO oggi. Comodità per la dashboard, non
     * una scorciatoia per saltare la validazione: passa comunque da
     * fromDates().
     */
    public static function lastDays(int $days): self
    {
        $days = max(1, min($days, self::MAX_DAYS));
        $today = CarbonImmutable::now(Article::EDITORIAL_TIMEZONE);

        return self::fromDates(
            $today->subDays($days - 1)->format('Y-m-d'),
            $today->format('Y-m-d'),
        );
    }

    public function days(): int
    {
        return (int) $this->startInclusive->diffInDays($this->endExclusive);
    }

    public function timezone(): string
    {
        return Article::EDITORIAL_TIMEZONE;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'timezone' => $this->timezone(),
            'days' => $this->days(),
            'start_inclusive_utc' => $this->startInclusive->toIso8601String(),
            'end_exclusive_utc' => $this->endExclusive->toIso8601String(),
            'boundary_note' => 'Confine iniziale incluso, confine finale escluso: un evento delle 23:59 dell\'ultimo giorno rientra, nessun evento appartiene a due finestre adiacenti.',
        ];
    }

    private static function parseEditorialDate(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date, Article::EDITORIAL_TIMEZONE);

        // createFromFormat accetta anche input "quasi validi" (es. 2026-13-45
        // rifluisce su un'altra data): il confronto con la stringa originale
        // è l'unico modo affidabile di rifiutarli.
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Data non valida: attesa nel formato AAAA-MM-GG.');
        }

        return $parsed->startOfDay();
    }
}
