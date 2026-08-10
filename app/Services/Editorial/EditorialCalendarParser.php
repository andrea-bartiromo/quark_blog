<?php

namespace App\Services\Editorial;

use Carbon\Carbon;

/**
 * Estrae le voci di calendario da un documento Markdown ("Piano Editoriale
 * Kairus — Calendario N articoli" o equivalente) — vedi
 * docs/PROJECT_EDITORIAL_AUTOMATION.md per la grammatica completa con
 * esempi.
 *
 * Contratto deliberatamente riusabile: prende una stringa di contenuto
 * Markdown, mai un ID di documento — nessun accoppiamento a un singolo
 * ProjectDocument o Project. Chi chiama decide da dove viene il testo
 * (tipicamente ProjectDocument::content di un documento marcato
 * is_editorial_calendar, ma il parser stesso non lo sa né gli interessa).
 *
 * Formato riconosciuto per riga (dopo aver rimosso un eventuale marcatore
 * di lista Markdown iniziale, es. "- " o "12. "):
 *
 *     DD/MM/YYYY — Titolo previsto — Filone [stato]
 *
 * - la data tollera "/", "-" o "." come separatore e cifre non
 *   zero-imbottite (es. "8/8/2026");
 * - il trattino tra data e titolo tollera "—", "–", "--" o " - ";
 * - il filone (secondo segmento) è opzionale, separato dal titolo SOLO da
 *   "—" o "–" (mai un trattino singolo/doppio: comparirebbe troppo spesso
 *   dentro titoli reali, es. "GPT-5", spezzandoli per errore);
 * - uno stato tra parentesi tonde o quadre in fondo alla riga, es.
 *   "[pubblicato]" o "(bozza)", è opzionale ed estratto verbatim — mai
 *   validato contro un vocabolario fisso, per non scartare in silenzio uno
 *   stato reale scritto in un modo non previsto;
 * - un'intestazione Markdown (`#`..`######`, o una riga interamente in
 *   **grassetto**) imposta la "sezione" corrente (tipicamente un mese),
 *   applicata a ogni voce successiva fino alla prossima intestazione.
 *
 * Una riga che non inizia (dopo lo strip del marcatore di lista) con un
 * token simile a una data viene considerata prosa e ignorata silenziosamente
 * — mai un errore: è esattamente il modo in cui il parser "ignora le
 * sezioni non calendario" richiesto dalla missione. Una riga che INIZIA con
 * un token simile a una data ma non può essere interpretata fino in fondo
 * (data invalida, titolo mancante) produce invece un
 * EditorialCalendarParseError esplicito — mai scartata in silenzio, mai
 * un dato inventato per completarla.
 */
class EditorialCalendarParser
{
    private const HEADING_PATTERN = '/^#{1,6}\s+(.+?)\s*#*$/u';

    private const BOLD_HEADING_PATTERN = '/^\*\*(.+?)\*\*$/u';

    private const HORIZONTAL_RULE_PATTERN = '/^(-{3,}|\*{3,}|_{3,})$/';

    private const LIST_MARKER_PATTERN = '/^(?:[-*+]|\d{1,3}[.\)])\s+/u';

    private const DATE_PREFIX_PATTERN = '/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\s*(.*)$/us';

    private const LEADING_SEPARATOR_PATTERN = '/^(?:—|–|--|-)\s*/u';

    private const TRAILING_STATUS_PATTERN = '/\s*[\[(]([^\[\]()]+)[\])]\s*$/u';

    private const TITLE_FILONE_SPLIT_PATTERN = '/\s+(?:—|–)\s+/u';

    public function parse(string $content): EditorialCalendarParseResult
    {
        $lines = preg_split('/\R/u', $content) ?: [];

        $entries = [];
        $errors = [];
        $section = null;
        $position = 0;

        foreach ($lines as $index => $rawLine) {
            $lineNumber = $index + 1;
            $line = trim($rawLine);

            if ($line === '' || preg_match(self::HORIZONTAL_RULE_PATTERN, $line)) {
                continue;
            }

            if (preg_match(self::HEADING_PATTERN, $line, $m)) {
                $section = trim($m[1]);

                continue;
            }

            if (preg_match(self::BOLD_HEADING_PATTERN, $line, $m)) {
                $section = trim($m[1]);

                continue;
            }

            $stripped = preg_replace(self::LIST_MARKER_PATTERN, '', $line, 1) ?? $line;

            if (! preg_match(self::DATE_PREFIX_PATTERN, $stripped, $dateMatch)) {
                // Prosa: non assomiglia a una voce di calendario. Ignorata
                // di proposito, mai un errore.
                continue;
            }

            [$day, $month, $year, $rest] = [$dateMatch[1], $dateMatch[2], $dateMatch[3], $dateMatch[4]];

            $date = $this->parseDate($day, $month, $year);

            if ($date === null) {
                $errors[] = new EditorialCalendarParseError(
                    $lineNumber,
                    $rawLine,
                    "Data non valida: {$day}/{$month}/{$year}."
                );

                continue;
            }

            $rest = preg_replace(self::LEADING_SEPARATOR_PATTERN, '', trim($rest), 1) ?? trim($rest);
            $rest = trim($rest);

            $status = null;
            if (preg_match(self::TRAILING_STATUS_PATTERN, $rest, $statusMatch)) {
                $status = trim($statusMatch[1]);
                $rest = trim(substr($rest, 0, -strlen($statusMatch[0])));
            }

            if ($rest === '') {
                $errors[] = new EditorialCalendarParseError(
                    $lineNumber,
                    $rawLine,
                    'Titolo mancante dopo la data.'
                );

                continue;
            }

            $filone = null;
            $parts = preg_split(self::TITLE_FILONE_SPLIT_PATTERN, $rest, 2);

            if ($parts !== false && count($parts) === 2) {
                [$title, $filone] = $parts;
                $title = trim($title);
                $filone = trim($filone) ?: null;
            } else {
                $title = trim($rest);
            }

            if ($title === '') {
                $errors[] = new EditorialCalendarParseError(
                    $lineNumber,
                    $rawLine,
                    'Titolo mancante dopo la data.'
                );

                continue;
            }

            $position++;

            $entries[] = new EditorialCalendarEntry(
                position: $position,
                date: $date,
                title: $title,
                filone: $filone,
                status: $status,
                section: $section,
                lineNumber: $lineNumber,
                rawLine: $rawLine,
            );
        }

        return new EditorialCalendarParseResult($entries, $errors);
    }

    /**
     * Validazione reale del calendario (non solo formale): checkdate()
     * rifiuta esplicitamente date come 32/13/2026 o 30/02/2026, che
     * Carbon::createFromFormat() da solo accetterebbe silenziosamente
     * facendole "traboccare" nel mese successivo — esattamente il tipo di
     * dato inventato che questo parser non deve mai produrre. Un anno a 2
     * cifre è interpretato come 2000+YY (nessun calendario editoriale di
     * Kairus ricadrà mai nel secolo scorso).
     */
    private function parseDate(string $day, string $month, string $year): ?Carbon
    {
        $day = (int) $day;
        $month = (int) $month;
        $year = strlen($year) === 2 ? 2000 + (int) $year : (int) $year;

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day, 0, 0, 0);
    }
}
