<?php

namespace App\Services\Communication;

use App\Contracts\EmailDeliveryProvider;
use Illuminate\Support\Str;

/**
 * Provider "no-op": accetta sempre, senza registrare nulla in memoria e
 * senza alcuna I/O — utile ovunque serva soltanto che la pipeline
 * completi senza voler ispezionare i singoli tentativi (es. un
 * benchmark di scala). Nessuna connessione di rete, nessuna scrittura
 * su disco, nessun log. Per i test che devono ISPEZIONARE i tentativi,
 * usare RecordingEmailProvider.
 */
class NullEmailProvider implements EmailDeliveryProvider
{
    public function deliver(RenderedCampaignMessage $message): DeliveryResult
    {
        return new DeliveryResult(
            status: DeliveryResult::STATUS_ACCEPTED,
            providerMessageId: 'null-'.Str::uuid(),
            idempotencyKey: $message->idempotencyKey,
        );
    }
}
