<?php

namespace App\Http\Middleware;

use App\Services\PublicPages\NotFoundHitTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cantiere 24 (programma 100-cantieri Kairus). Registra ogni 404 in base
 * alla risposta FINALE, non al fatto che un'eccezione sia stata lanciata
 * (Codex, PR #572): un controller che risponde 404 con
 * `->setStatusCode(404)` invece di lanciare/abortire (vedi
 * CommunicationUnsubscribeController) non passa mai dal render() di
 * HttpException, e un hook li' non l'avrebbe mai visto. Controllare lo
 * status della risposta finale copre entrambi i casi con un solo
 * meccanismo, senza doppio conteggio.
 *
 * Middleware globale (kernel), non di gruppo rotta: un path che non
 * corrisponde a NESSUNA rotta genera comunque una risposta 404 che
 * attraversa questo middleware, perche' il kernel lo applica attorno
 * all'intera pipeline (routing incluso), a differenza del gruppo 'web'
 * (che include StartSession) che gira SOLO per una rotta effettivamente
 * risolta. Questa stessa asimmetria e' il motivo per cui l'esclusione
 * redazionale in NotFoundHitTracker::shouldRecord() non copre un path
 * del tutto sconosciuto — vedi il docblock di quella classe per il
 * limite noto e accettato (e perche' un fix generale e' stato scartato).
 */
class RecordNotFoundHits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            app(NotFoundHitTracker::class)->recordHit($request);
        }

        return $response;
    }
}
