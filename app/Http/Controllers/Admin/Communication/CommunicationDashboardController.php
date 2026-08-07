<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;

class CommunicationDashboardController extends Controller
{
    /**
     * Dashboard Comunicazione — solo lettura (Blueprint UX1). Nessuna azione
     * di scrittura: coda invii, provider e statistiche restano fuori
     * perimetro finché i blocchi successivi non introducono il motore di
     * invio e comm_sender_profiles.
     */
    public function index()
    {
        return view('admin.communication.dashboard', [
            'draftCount' => CommunicationCampaign::status(CommunicationCampaign::STATUS_DRAFT)->count(),
            'scheduledNext7Count' => CommunicationCampaign::status(CommunicationCampaign::STATUS_SCHEDULED)
                ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
                ->count(),
            'completedLast30Count' => CommunicationCampaign::status(CommunicationCampaign::STATUS_COMPLETED)
                ->where('completed_at', '>=', now()->subDays(30))
                ->count(),
            // "Errori aperti" in questo blocco è il conteggio grezzo dei Send
            // falliti: la gestione dedicata (tipi di errore, retry) arriva
            // in un blocco successivo (Blueprint UX9).
            'openErrorsCount' => CommunicationSend::status(CommunicationSend::STATUS_FAILED)->count(),
            'upcomingCampaigns' => CommunicationCampaign::status(CommunicationCampaign::STATUS_SCHEDULED)
                ->whereNotNull('scheduled_at')
                ->orderBy('scheduled_at')
                ->limit(10)
                ->get(),
            'recentSentCampaigns' => CommunicationCampaign::status(CommunicationCampaign::STATUS_COMPLETED)
                ->orderByDesc('completed_at')
                ->limit(5)
                ->get(),
            'sendingCampaign' => CommunicationCampaign::status(CommunicationCampaign::STATUS_SENDING)
                ->orderByDesc('sending_started_at')
                ->first(),
        ]);
    }
}
