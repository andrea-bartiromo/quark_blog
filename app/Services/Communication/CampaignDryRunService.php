<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use Illuminate\Support\Facades\DB;

/**
 * N2.9 — dry-run end-to-end: esegue l'INTERA pipeline di invio reale
 * (CampaignDeliveryOrchestrator::runCampaign() — claim, revalidazione,
 * rendering, chiamata al provider, persistenza esito, retry) esattamente
 * come farebbe un invio vero, con due sole differenze deliberate:
 *
 *   1. Il tipo del parametro è RecordingEmailProvider, non la generica
 *      interfaccia EmailDeliveryProvider — un dry-run con un provider
 *      diverso da un fake non deve nemmeno poter compilare.
 *   2. L'intera esecuzione avviene dentro una transazione DB che viene
 *      SEMPRE annullata, esito compreso — sia in caso di successo che di
 *      eccezione (finally). Nessuna riga di comm_campaigns/comm_sends
 *      risulta mai modificata dopo un dry-run, indipendentemente da
 *      quante volte viene eseguito o da cosa il provider fake restituisce.
 *
 * Il numero riportato (CampaignRunReport) è quindi quello che un invio
 * reale produrrebbe con gli STESSI dati di partenza, senza lasciare
 * alcuna traccia persistente — motivo per cui $campaign viene
 * ricaricata da DB prima di restituire il controllo, cosicché l'istanza
 * in memoria del chiamante non "menta" mostrando uno stato (es.
 * 'completed') mai davvero scritto.
 */
class CampaignDryRunService
{
    public function __construct(
        private readonly CampaignDeliveryOrchestrator $orchestrator,
    ) {}

    public function run(
        CommunicationCampaign $campaign,
        RecordingEmailProvider $provider,
        int $maxAttempts = CampaignDeliveryOrchestrator::DEFAULT_MAX_ATTEMPTS,
    ): CampaignRunReport {
        DB::beginTransaction();

        try {
            $report = $this->orchestrator->runCampaign($campaign, $provider, $maxAttempts);
        } finally {
            DB::rollBack();
            $campaign->refresh();
        }

        return $report ?? new CampaignRunReport;
    }
}
