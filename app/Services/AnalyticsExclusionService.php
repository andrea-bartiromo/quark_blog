<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Analytics Hygiene: decide se lo script Google Analytics 4 debba essere
 * caricato per la richiesta corrente, e gestisce il cookie first-party che
 * marca un browser come "traffico interno".
 *
 * Principio guida (missione Analytics Hygiene): preferire il NON
 * caricamento/non inizializzazione di GA4 rispetto all'invio di eventi
 * marcati male e filtrati solo dopo — quindi questa classe e' l'unico
 * punto da cui layouts/partials/head.blade.php decide se emettere lo
 * <script> di gtag.js. Nessuna riga di gtag.js viene mai renderizzata per
 * una richiesta esclusa: non e' un filtro applicato dopo l'invio, e' un
 * mancato invio.
 *
 * Deliberatamente NON basata sull'IP: IP dinamici, reti mobili, VPN o
 * cambi di connessione renderebbero quel criterio fragile. Il segnale
 * primario e' un cookie first-party persistente, attivabile solo da un
 * amministratore autenticato (vedi Admin\AnalyticsExclusionController) e
 * reversibile in ogni momento dalla pagina profilo, senza alcun intervento
 * sul database: il cookie stesso e' l'unico stato, la sua sola presenza
 * (valore non vuoto) significa "escluso".
 *
 * Deliberatamente un cookie, non la sessione Laravel: il proprietario
 * visita spesso il sito pubblico anche SENZA essere autenticato in quel
 * momento (la sessione admin scade, o naviga in incognito/da un altro
 * dispositivo dopo essersi autenticato altrove) — un flag legato alla
 * sola sessione applicativa smetterebbe di proteggere esattamente nei casi
 * che la missione vuole coprire. Il cookie sopravvive al logout e a
 * qualunque scadenza di sessione.
 */
class AnalyticsExclusionService
{
    public const COOKIE_NAME = 'kairus_analytics_excluded';

    /**
     * Vero se per QUESTA richiesta lo script GA4 deve essere emesso.
     * Falso in ognuno di questi casi, in ordine di verifica:
     * - nessun Measurement ID configurato (fallback esplicito, mai un
     *   tag gtag('config', '', ...) con ID vuoto/invalido);
     * - Analytics non abilitato per l'ambiente corrente (vedi
     *   isEnabledForEnvironment());
     * - il browser della richiesta corrente porta il cookie di esclusione.
     */
    public function shouldLoadAnalytics(Request $request): bool
    {
        if (blank(config('analytics.measurement_id'))) {
            return false;
        }

        if (! $this->isEnabledForEnvironment()) {
            return false;
        }

        return ! $this->isExcluded($request);
    }

    /**
     * true/false = override esplicito da config('analytics.enabled') (che
     * legge ANALYTICS_ENABLED). null = comportamento automatico: solo
     * l'ambiente "production" abilita Analytics, cosi' locale, testing ed
     * eventuale staging non taggato "production" restano sempre sicuri di
     * default, senza richiedere alcuna configurazione esplicita.
     */
    public function isEnabledForEnvironment(): bool
    {
        $override = config('analytics.enabled');

        if ($override !== null) {
            return filter_var($override, FILTER_VALIDATE_BOOLEAN);
        }

        return config('app.env') === 'production';
    }

    /**
     * true se il browser della richiesta porta il cookie di esclusione.
     * Basta la presenza di un valore non vuoto: il contenuto (un
     * timestamp ISO 8601, vedi exclude()) serve solo per mostrare
     * all'amministratore da quando l'esclusione e' attiva, mai per
     * validare "chi" ha attivato l'esclusione — il cookie e' un flag di
     * browser, non un'identita'.
     */
    public function isExcluded(Request $request): bool
    {
        return filled($request->cookie(self::COOKIE_NAME));
    }

    /**
     * Timestamp ISO 8601 di quando l'esclusione e' stata attivata su
     * questo browser, o null se non escluso. Usato solo per la UI
     * ("Escluso dal ..."), mai per decidere se escludere (vedi
     * isExcluded()).
     */
    public function excludedSince(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE_NAME);

        return filled($value) ? $value : null;
    }

    /**
     * Cookie da accodare alla risposta per marcare QUESTO browser come
     * traffico interno. HttpOnly: l'attivazione avviene sempre tramite la
     * rotta admin protetta, mai da JavaScript lato client, quindi
     * impedire la lettura/scrittura via document.cookie riduce la
     * superficie di manomissione senza perdere alcuna funzionalita'.
     * Secure rispecchia lo schema della richiesta corrente (non un valore
     * fisso): true in produzione, dove ForceHttpsUrlScheme garantisce
     * sempre HTTPS; false in locale, dove forzare Secure impedirebbe al
     * cookie di essere impostato/inviato su HTTP.
     */
    public function exclude(Request $request): Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            now()->toIso8601String(),
            config('analytics.exclusion_cookie_days') * 24 * 60,
            '/',
            null,
            $request->secure(),
            true,
            false,
            'lax'
        );
    }

    /**
     * Cookie di rimozione per riattivare Analytics su questo browser.
     */
    public function reactivate(): Cookie
    {
        return cookie()->forget(self::COOKIE_NAME);
    }
}
