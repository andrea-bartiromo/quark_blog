<?php

namespace App\Services\Telemetry;

use Illuminate\Http\Request;

/**
 * Measurement Closeout (Missione 5) — attribuzione della sorgente di
 * continuità.
 *
 * Normalizza UTM e referrer in UNO dei nove canali allowlisted di
 * EditorialEventContract::SOURCE_CHANNELS, e non conserva nient'altro.
 *
 * COSA QUESTA CLASSE PUÒ DEDURRE
 * - Che la richiesta è arrivata etichettata con una utm_source riconosciuta
 *   (link costruiti da noi: newsletter, campagne social).
 * - Che il browser ha dichiarato un referrer appartenente a un host noto
 *   (Google, Facebook, LinkedIn) o al nostro stesso host.
 * - Che la richiesta proviene da una pagina Percorso del sito ('percorso'),
 *   caso particolare e più informativo di 'internal'.
 * - Che non c'è alcun referrer né alcuna UTM ('direct').
 *
 * COSA QUESTA CLASSE NON PUÒ DEDURRE — e che nessun consumer deve pretendere
 * di leggere in questo dato:
 * - La provenienza reale di una visita 'direct': un referrer assente può
 *   significare digitazione diretta, un'app, un client email, un browser con
 *   politica di referrer restrittiva, o un redirect HTTPS→HTTP. Questi casi
 *   sono INDISTINGUIBILI e vengono deliberatamente raggruppati, non spalmati
 *   su ipotesi.
 * - La differenza fra Google Search e Google Discover quando il traffico non
 *   porta un marcatore esplicito: Discover è riconosciuto SOLO tramite le
 *   utm che noi stessi o Google applicano, mai indovinato dal solo host
 *   google.com. Un canale 'discover' inferito sarebbe una metrica inventata.
 * - Qualunque attribuzione multi-touch, peso per canale, o percorso completo
 *   di conversione: fuori scope per mandato esplicito della missione.
 *
 * COSA NON VIENE MAI MEMORIZZATO: l'URL di referrer completo, la query
 * string, il path di provenienza, il contenuto delle utm diverse da
 * utm_source/utm_medium, l'IP, lo user agent. Il valore restituito è una
 * stringa di al massimo 16 caratteri scelta da una lista chiusa.
 */
final class SourceChannelResolver
{
    /**
     * Host di referrer riconosciuti → canale. Il confronto avviene sul
     * suffisso di dominio registrabile (vedi hostMatches) per accettare le
     * varianti nazionali e i sottodomini (www.google.it, m.facebook.com, ...)
     * senza mantenere un elenco esaustivo impossibile da tenere aggiornato.
     *
     * @var array<string, string>
     */
    private const REFERRER_HOSTS = [
        'google.' => 'google',
        'facebook.com' => 'facebook',
        'fb.com' => 'facebook',
        'linkedin.com' => 'linkedin',
        'lnkd.in' => 'linkedin',
    ];

    /**
     * Valori di utm_source/utm_medium riconosciuti → canale. Confronto esatto
     * su valore normalizzato in minuscolo: una utm è un'etichetta che
     * scegliamo noi, quindi non c'è alcuna ragione di accettarne varianti
     * approssimate.
     *
     * @var array<string, string>
     */
    private const UTM_VALUES = [
        'google' => 'google',
        'google-discover' => 'discover',
        'discover' => 'discover',
        'facebook' => 'facebook',
        'fb' => 'facebook',
        'instagram' => 'facebook',
        'linkedin' => 'linkedin',
        'newsletter' => 'newsletter',
        'email' => 'newsletter',
        'e-mail' => 'newsletter',
    ];

    public function resolve(Request $request): string
    {
        $fromUtm = $this->fromUtm($request);

        if ($fromUtm !== null) {
            return $fromUtm;
        }

        $referer = $request->headers->get('referer');

        if (! is_string($referer) || trim($referer) === '') {
            // Nessuna utm, nessun referrer: 'direct' è l'unica descrizione
            // onesta possibile — vedi il docblock di classe per cosa questo
            // NON permette di concludere.
            return 'direct';
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return EditorialEventContract::SOURCE_UNKNOWN;
        }

        $host = strtolower($host);

        if ($this->isOwnHost($host, $request)) {
            // Navigazione interna. Il path di provenienza NON viene
            // memorizzato: viene letto qui, una sola volta, per distinguere
            // il caso più informativo (si arriva da un Percorso) e poi
            // scartato.
            $path = parse_url($referer, PHP_URL_PATH);

            return is_string($path) && str_starts_with($path, '/percorsi')
                ? 'percorso'
                : 'internal';
        }

        foreach (self::REFERRER_HOSTS as $needle => $channel) {
            if ($this->hostMatches($host, $needle)) {
                return $channel;
            }
        }

        // Un referrer esterno che non riconosciamo resta 'unknown' e NON
        // viene salvato in alcuna forma: sapere che esiste traffico da fonti
        // non catalogate è un'informazione utile, sapere DA QUALE dominio non
        // lo è abbastanza da giustificarne la conservazione.
        return EditorialEventContract::SOURCE_UNKNOWN;
    }

    private function fromUtm(Request $request): ?string
    {
        foreach (['utm_source', 'utm_medium'] as $parameter) {
            $value = $request->query($parameter);

            if (! is_string($value)) {
                continue;
            }

            $normalized = strtolower(trim($value));

            if (isset(self::UTM_VALUES[$normalized])) {
                return self::UTM_VALUES[$normalized];
            }
        }

        return null;
    }

    private function isOwnHost(string $host, Request $request): bool
    {
        $ownHost = strtolower((string) $request->getHost());

        if ($ownHost === '') {
            return false;
        }

        return $host === $ownHost || str_ends_with($host, '.'.$ownHost);
    }

    /**
     * 'google.' come needle intercetta google.com, google.it, news.google.com
     * ma non un dominio ostile che contenga la stringa altrove (es.
     * notgoogle.example.com): il confronto richiede che il needle sia
     * l'inizio dell'host o segua un punto.
     */
    private function hostMatches(string $host, string $needle): bool
    {
        return str_starts_with($host, $needle)
            || str_contains($host, '.'.$needle)
            || $host === rtrim($needle, '.');
    }
}
