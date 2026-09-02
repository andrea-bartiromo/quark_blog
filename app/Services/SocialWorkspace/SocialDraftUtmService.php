<?php

namespace App\Services\SocialWorkspace;

use App\Models\Article;
use App\Models\SocialDraft;
use InvalidArgumentException;

/**
 * Puro, nessuna persistenza, nessuna chiamata di rete. Copre due esigenze
 * che App\Services\SocialDistribution\UtmLinkGenerator non copre: (1)
 * validare/risolvere un destination_url arbitrario (testo libero salvato
 * dalla redazione), non solo ricostruire la rotta canonica di un Article;
 * (2) decorare un URL già esistente preservando query string e fragment,
 * non generarne uno nuovo da zero. Riusa le stesse convenzioni UTM
 * (utm_medium=social, pattern campagna) per coerenza col generatore
 * esistente.
 */
class SocialDraftUtmService
{
    private const UTM_MEDIUM = 'social';

    private const UTM_SOURCE = [
        SocialDraft::CHANNEL_FACEBOOK => 'facebook',
        SocialDraft::CHANNEL_LINKEDIN => 'linkedin',
    ];

    private const CAMPAIGN_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const MAX_CAMPAIGN_LENGTH = 100;

    /**
     * Risolve l'URL di destinazione: quello personalizzato dalla redazione
     * se presente e valido, altrimenti l'URL canonico pubblico
     * dell'articolo. Lancia InvalidArgumentException per qualunque URL non
     * sicuro — mai un fallback silenzioso su un URL pericoloso.
     */
    public function resolveDestinationUrl(Article $article, ?string $customUrl): string
    {
        $candidate = filled($customUrl) ? trim($customUrl) : $article->metaCanonicalUrl();

        if (! $this->isSafeUrl($candidate)) {
            throw new InvalidArgumentException("URL di destinazione non valido o non consentito: {$candidate}");
        }

        return $candidate;
    }

    /**
     * http/https, ben formato, e sullo stesso host dell'applicazione: ogni
     * bozza rappresenta sempre un articolo Kairus (article_id obbligatorio
     * sul modello), quindi il vincolo "host consentito" si applica sempre,
     * non solo in un caso particolare.
     */
    public function isSafeUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host !== '' && $appHost !== '' && $host === $appHost;
    }

    /**
     * Aggiunge/sostituisce i parametri UTM preservando query string e
     * fragment esistenti — mai un secondo blocco "?" o parametri duplicati.
     * use_utm=false restituisce l'URL invariato (comunque già validato da
     * resolveDestinationUrl prima di arrivare qui).
     */
    public function withUtm(string $url, string $channel, bool $useUtm, ?string $campaign, Article $article): string
    {
        if (! $useUtm) {
            return $url;
        }

        $source = self::UTM_SOURCE[$channel] ?? null;

        if ($source === null) {
            throw new InvalidArgumentException("Canale non supportato: {$channel}");
        }

        $parts = parse_url($url);

        if ($parts === false) {
            throw new InvalidArgumentException("URL non valido: {$url}");
        }

        parse_str($parts['query'] ?? '', $query);

        $query['utm_source'] = $source;
        $query['utm_medium'] = self::UTM_MEDIUM;
        $query['utm_campaign'] = $this->normalizeCampaign($campaign, $article, $channel);

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= '?'.http_build_query($query);

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }

    private function normalizeCampaign(?string $campaign, Article $article, string $channel): string
    {
        $value = filled($campaign) ? trim($campaign) : $this->defaultCampaign($article, $channel);

        if (mb_strlen($value) > self::MAX_CAMPAIGN_LENGTH) {
            throw new InvalidArgumentException('Nome campagna troppo lungo (max '.self::MAX_CAMPAIGN_LENGTH.' caratteri).');
        }

        if (preg_match(self::CAMPAIGN_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Nome campagna non valida: solo lettere minuscole, cifre e trattini singoli.');
        }

        return $value;
    }

    /**
     * Deterministico (nessuna data): stesso articolo + stesso canale
     * producono sempre la stessa campagna di default, a differenza del
     * generatore UTM esistente (che include la data) — qui serve stabilità
     * per una bozza rivista più volte prima di essere programmata.
     */
    private function defaultCampaign(Article $article, string $channel): string
    {
        return $channel.'-'.$article->slug;
    }
}
