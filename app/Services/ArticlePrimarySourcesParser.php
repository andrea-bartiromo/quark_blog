<?php

namespace App\Services;

/**
 * Presentation-only: trasforma il testo libero di Article::primary_sources
 * in una lista di elementi da mostrare al lettore, senza mai reinterpretare
 * il contenuto come dato strutturato che non è. Non tocca né normalizza il
 * valore salvato — nessuna scrittura, nessuna migration, nessun cambiamento
 * al contratto di salvataggio (fuori scope di questo servizio).
 *
 * Riconosce come "link" solo una riga che, per intero, è un URL assoluto
 * http/https valido: qualunque altra riga (testo descrittivo, URL con
 * schema non sicuro, testo misto a URL, markup ostile) resta testo
 * semplice — mai perso, mai promosso a link per inferenza.
 */
class ArticlePrimarySourcesParser
{
    /**
     * @return array<int, array{type: string, text: string, url: ?string}>
     */
    public function parse(?string $raw): array
    {
        if (blank($raw)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $items[] = $this->classify($line);
        }

        return $items;
    }

    /**
     * @return array{type: string, text: string, url: ?string}
     */
    private function classify(string $line): array
    {
        if ($this->isSafeAbsoluteUrl($line)) {
            return ['type' => 'link', 'text' => $line, 'url' => $line];
        }

        return ['type' => 'text', 'text' => $line, 'url' => null];
    }

    private function isSafeAbsoluteUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
