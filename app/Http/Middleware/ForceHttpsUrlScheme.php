<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * route()/url() (quindi anche canonical, og:url, sitemap.xml e feed.xml,
 * che passano tutti dallo stesso generatore) usano di default lo schema
 * della richiesta corrente: una richiesta arrivata su http:// — es. prima
 * che il redirect lato server (public/.htaccess) la intercetti, o durante
 * una finestra di manutenzione — produrrebbe altrimenti URL http,
 * incoerenti col dominio ufficiale https e con quanto Search Console si
 * aspetta come canonical.
 *
 * Applicato per ogni richiesta (non una volta sola all'avvio, come in un
 * service provider) cosi' da leggere sempre il valore corrente di
 * config('app.url') — l'unico modo per restare corretto sia in produzione
 * sia nei test, che possono impostarlo esplicitamente in un singolo test
 * dopo che l'applicazione e' gia' stata avviata.
 *
 * Forzato solo quando APP_URL e' configurato su https: in locale (dove
 * APP_URL e' tipicamente http://localhost) resta inattivo.
 */
class ForceHttpsUrlScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
