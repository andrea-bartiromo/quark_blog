<?php

namespace App\Services\Communication;

use App\Contracts\EmailDeliveryProvider;
use Closure;
use Illuminate\Support\Str;

/**
 * Provider fake pensato per test e dry-run: registra ogni tentativo in
 * memoria (mai su disco/DB/rete) e restituisce esiti configurabili —
 * una coda di risultati canned (willReturn) o un resolver arbitrario
 * (resolveUsing), per simulare accepted/rejected/transient/permanent
 * failure e testare l'intera state machine di delivery senza mai
 * toccare un vero provider. Default: accetta sempre.
 *
 * Nessuna I/O di rete in questa classe — verificato anche da un test
 * dedicato (RecordingEmailProviderTest::
 * test_provider_source_contains_no_network_or_mail_calls) che analizza
 * il sorgente di questo file e di NullEmailProvider.
 */
class RecordingEmailProvider implements EmailDeliveryProvider
{
    /** @var list<RenderedCampaignMessage> */
    private array $attempts = [];

    /** @var list<DeliveryResult> */
    private array $results = [];

    /** @var list<DeliveryResult> */
    private array $queuedResults = [];

    private ?Closure $resultResolver = null;

    public function deliver(RenderedCampaignMessage $message): DeliveryResult
    {
        $this->attempts[] = $message;

        $result = $this->resolveResult($message);
        $this->results[] = $result;

        return $result;
    }

    /**
     * Accoda risultati canned, consumati in ordine FIFO a ogni deliver()
     * successiva. Se la coda si esaurisce, torna al default (accepted).
     */
    public function willReturn(DeliveryResult ...$results): static
    {
        $this->queuedResults = array_merge($this->queuedResults, $results);

        return $this;
    }

    /**
     * Sostituisce completamente la logica di risoluzione dell'esito per
     * ogni deliver(): utile per simulare condizioni dipendenti dal
     * messaggio stesso (es. "fallisci sempre per questo destinatario").
     * Ha precedenza sulla coda willReturn().
     */
    public function resolveUsing(Closure $resolver): static
    {
        $this->resultResolver = $resolver;

        return $this;
    }

    /**
     * @return list<RenderedCampaignMessage>
     */
    public function attempts(): array
    {
        return $this->attempts;
    }

    public function attemptCount(): int
    {
        return count($this->attempts);
    }

    /**
     * @return list<DeliveryResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function reset(): void
    {
        $this->attempts = [];
        $this->results = [];
        $this->queuedResults = [];
        $this->resultResolver = null;
    }

    private function resolveResult(RenderedCampaignMessage $message): DeliveryResult
    {
        if ($this->resultResolver !== null) {
            return ($this->resultResolver)($message);
        }

        if ($this->queuedResults !== []) {
            return array_shift($this->queuedResults);
        }

        return new DeliveryResult(
            status: DeliveryResult::STATUS_ACCEPTED,
            providerMessageId: 'fake-'.Str::uuid(),
            idempotencyKey: $message->idempotencyKey,
        );
    }
}
