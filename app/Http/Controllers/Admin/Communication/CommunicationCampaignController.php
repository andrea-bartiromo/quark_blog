<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationCampaign;
use Illuminate\Http\Request;

class CommunicationCampaignController extends Controller
{
    /**
     * Elenco Campagne — solo lettura (Blueprint UX1). Niente creazione,
     * modifica o azioni bulk: arrivano con il wizard (Blueprint UX2) e la
     * pagina di modifica (Blueprint UX3) in blocchi successivi.
     */
    public function index(Request $request)
    {
        $campaigns = CommunicationCampaign::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('subject', 'like', $term));
            })
            ->when($request->query('sort') === 'next-send', fn ($q) => $q->orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at'))
            ->when($request->query('sort') !== 'next-send', fn ($q) => $q->orderByDesc('created_at'))
            ->paginate(15)
            ->withQueryString();

        return view('admin.communication.campaigns.index', [
            'campaigns' => $campaigns,
            'typeOptions' => CommunicationCampaign::typeOptions(),
            'statusOptions' => CommunicationCampaign::statusOptions(),
        ]);
    }

    public function show(CommunicationCampaign $campaign)
    {
        return view('admin.communication.campaigns.show', [
            'campaign' => $campaign,
            'typeOptions' => CommunicationCampaign::typeOptions(),
            'statusOptions' => CommunicationCampaign::statusOptions(),
        ]);
    }
}
