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
 * http/https valido oppure un identificatore DOI: qualunque altra riga
 * (testo descrittivo, URL con schema non sicuro, testo misto a URL/DOI,
 * markup ostile) resta testo semplice — mai perso, mai promosso a link per
 * inferenza. Deliberatamente NON estrae un URL/DOI incorporato dentro una
 * riga di testo più lunga (es. "Fonte: https://...") — vedi
 * docs/TRUST_LAYER_PUBLIC_SOURCES_V1.md, sezione sulla sintesi con la PR
 * parallela #508: un'estrazione parziale via regex aumenta la superficie
 * di link fuorvianti costruiti ad arte per un guadagno minimo.
 */
class ArticlePrimarySourcesParser
{
    private const DOI_PATTERN = '~^(?:https?://(?:dx\.)?doi\.org/)?(10\.\d{4,9}/[-._;()/:A-Za-z0-9]+)$~';

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
        // Il pattern DOI va verificato prima del controllo URL generico:
        // un https://dx.doi.org/... è già di per sé un URL assoluto valido,
        // ma qui va normalizzato a doi.org (senza "dx.") invece di essere
        // pubblicato con l'host legacy.
        if (preg_match(self::DOI_PATTERN, $line, $match) === 1) {
            $doi = rtrim($match[1], '.,;:)]');

            return ['type' => 'link', 'text' => $line, 'url' => 'https://doi.org/'.$doi];
        }

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
