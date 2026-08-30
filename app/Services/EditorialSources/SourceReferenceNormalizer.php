<?php

namespace App\Services\EditorialSources;

/**
 * EDITORIAL TRUST (Missione 26) — normalizzazione e validazione dei due
 * riferimenti macchina di una fonte: URL e DOI.
 *
 * Punto unico: la validazione della request, il rendering pubblico e i
 * test leggono tutti da qui. Duplicare una regexp di schema URL in una
 * Blade sarebbe esattamente il modo in cui un `javascript:` finisce in
 * produzione dopo una modifica innocua altrove.
 */
class SourceReferenceNormalizer
{
    /**
     * Schemi ammessi per un URL di fonte. Allowlist, mai una denylist:
     * una denylist di `javascript:`/`data:` verrebbe aggirata da
     * `vbscript:`, `blob:`, `filesystem:`, da uno schema futuro non ancora
     * inventato, o da una variante con spazi/newline incorporati.
     *
     * HTTPS è l'unico schema accettato per le fonti NUOVE ("valida HTTPS
     * dove applicabile"): http resta riconosciuto come schema noto in
     * lettura — così una riga legacy eventualmente importata in futuro non
     * diventa un link rotto o, peggio, non linkabile in silenzio — ma non
     * è accettato in scrittura.
     */
    public const WRITABLE_SCHEMES = ['https'];

    public const RENDERABLE_SCHEMES = ['https', 'http'];

    /**
     * Forma canonica di un DOI: prefisso registrante `10.` + 4-9 cifre,
     * `/`, suffisso non vuoto senza spazi. È la forma descritta dal
     * registro DOI/Crossref; volutamente NON accettiamo qualunque cosa
     * contenga uno slash.
     */
    private const DOI_PATTERN = '/^10\.\d{4,9}\/\S+$/';

    /**
     * Riduce un DOI a forma nuda (`10.1234/abcd`) qualunque sia il modo in
     * cui la redazione lo ha incollato: `doi:10...`, `DOI: 10...`,
     * `https://doi.org/10...`, `http://dx.doi.org/10...`.
     *
     * Restituisce null se il valore non è un DOI riconoscibile: mai una
     * stringa "ripulita a metà" che verrebbe poi salvata come se fosse
     * valida.
     */
    public function normalizeDoi(?string $raw): ?string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        // Un DOI non contiene mai spazi interni né a capo: un valore che
        // li contiene è un errore di copia-incolla (o un tentativo di
        // costruire uno schema mascherato), non un DOI da "aggiustare".
        if (preg_match('/\s/', $value) === 1) {
            return null;
        }

        // Prefissi URL noti del resolver ufficiale e del vecchio dx.
        $value = preg_replace(
            '#^https?://(dx\.)?doi\.org/#i',
            '',
            $value
        ) ?? '';

        // Prefisso schema `doi:` (con o senza spazio dopo i due punti —
        // lo spazio è già escluso sopra, resta la forma attaccata).
        $value = preg_replace('/^doi:/i', '', $value) ?? '';

        $value = trim($value);

        if ($value === '' || preg_match(self::DOI_PATTERN, $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * URL pubblico di un DOI già normalizzato. rawurlencode() sui soli
     * segmenti del suffisso: un DOI può legittimamente contenere `/`
     * (gerarchico), che non va codificato, ma non deve poter iniettare
     * un `?`, un `#` o uno spazio nel link finale.
     */
    public function doiUrl(string $normalizedDoi): string
    {
        $encoded = implode('/', array_map(
            'rawurlencode',
            explode('/', $normalizedDoi)
        ));

        return 'https://doi.org/'.$encoded;
    }

    /**
     * Normalizza un URL di fonte per la SCRITTURA. Restituisce null se il
     * valore non è un URL assoluto HTTPS con host reale.
     */
    public function normalizeUrl(?string $raw): ?string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        // Caratteri di controllo (incluso \0, \n, \r, TAB): un browser li
        // ignora dentro uno schema, quindi "java\nscript:alert(1)" passa i
        // controlli ingenui ma esegue. Rifiutati prima di qualunque parsing.
        if (preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            return null;
        }

        if (! $this->hasWritableScheme($value)) {
            return null;
        }

        // filter_var da solo NON basta (accetta schemi arbitrari), ma dopo
        // il controllo di schema è il modo giusto per escludere host
        // malformati e URL sintatticamente invalidi.
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return $value;
    }

    /**
     * Vero se $value è un URL che il rendering pubblico può trasformare in
     * un <a href>. Deliberatamente più permissivo di normalizeUrl() sul
     * solo schema (http incluso) e altrettanto severo su tutto il resto:
     * se un giorno una riga http finisse in tabella per una migrazione
     * dati, deve restare un link visibile, non diventare testo muto.
     */
    public function isRenderableUrl(?string $value): bool
    {
        $value = (string) $value;

        if ($value === '' || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            return false;
        }

        if (! $this->hasScheme($value, self::RENDERABLE_SCHEMES)) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }

    private function hasWritableScheme(string $value): bool
    {
        return $this->hasScheme($value, self::WRITABLE_SCHEMES);
    }

    /**
     * @param  string[]  $schemes
     */
    private function hasScheme(string $value, array $schemes): bool
    {
        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return false;
        }

        return in_array(strtolower($scheme), $schemes, true);
    }

    /**
     * Chiave di confronto per il rilevamento duplicati (Missione 27). Due
     * fonti sono "lo stesso riferimento" se puntano allo stesso DOI, o
     * allo stesso URL a meno di maiuscole nello schema/host e di uno slash
     * finale — differenze che non cambiano la destinazione.
     *
     * Restituisce null quando non c'è alcun riferimento macchina: due
     * fonti senza URL né DOI non vengono mai dichiarate duplicate fra loro
     * sulla base del solo titolo.
     */
    public function duplicateKey(?string $url, ?string $doi): ?string
    {
        $normalizedDoi = $this->normalizeDoi($doi);

        if ($normalizedDoi !== null) {
            return 'doi:'.strtolower($normalizedDoi);
        }

        $value = trim((string) $url);

        if ($value === '' || ! $this->isRenderableUrl($value)) {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $path = rtrim($parts['path'] ?? '', '/');

        return 'url:'.strtolower($parts['scheme'] ?? '').'://'
            .strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$path
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
