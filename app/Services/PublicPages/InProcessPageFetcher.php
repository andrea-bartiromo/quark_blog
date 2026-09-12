<?php

namespace App\Services\PublicPages;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cantiere 22/23 (programma 100-cantieri Kairus). Un GET in-process
 * verso una rotta di QUESTA stessa applicazione — stesso meccanismo di
 * `Illuminate\Foundation\Testing\Concerns\MakesHttpRequests::call()`
 * (`HttpKernel::handle()` + `terminate()`), mai una vera chiamata di
 * rete in uscita. Estratto come classe dedicata perché sia
 * `PublicPageSeoAudit` (Cantiere 22) sia gli audit successivi che
 * verificano più pagine reali (Cantiere 23) hanno bisogno esattamente
 * dello stesso meccanismo — mai una sua duplicazione.
 *
 * Ogni richiesta porta l'header `X-Kairus-Internal-Audit`: senza un
 * modo per i controller di distinguere un GET di audit da una visita
 * reale, un audit ripetibile a piacere finirebbe per contaminare
 * analytics reali (vedi il finding Codex, PR #570, corretto in
 * `ArticleController::show()`) — ogni nuovo controller con un
 * effetto collaterale analogo deve controllare lo stesso header, mai
 * un'euristica sullo User-Agent.
 */
class InProcessPageFetcher
{
    public function fetch(string $url): Response
    {
        $kernel = app(HttpKernel::class);
        $request = Request::create($url, 'GET');
        $request->headers->set('X-Kairus-Internal-Audit', '1');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
