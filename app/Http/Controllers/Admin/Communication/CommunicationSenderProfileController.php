<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommunicationSenderProfileRequest;
use App\Http\Requests\Admin\UpdateCommunicationSenderProfileRequest;
use App\Models\CommunicationSenderProfile;
use Illuminate\Http\Request;

class CommunicationSenderProfileController extends Controller
{
    public function index(Request $request)
    {
        $senderProfiles = CommunicationSenderProfile::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('q').'%');
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.communication.sender-profiles.index', [
            'senderProfiles' => $senderProfiles,
            'statusOptions' => CommunicationSenderProfile::statusOptions(),
        ]);
    }

    public function create()
    {
        return view('admin.communication.sender-profiles.form', [
            'senderProfile' => new CommunicationSenderProfile,
            'providerOptions' => CommunicationSenderProfile::providerOptions(),
        ]);
    }

    public function store(StoreCommunicationSenderProfileRequest $request)
    {
        $data = $request->validated();
        // Una checkbox non spuntata non viene inviata dal browser: senza
        // questa coercizione esplicita is_default resterebbe assente da
        // $data invece di essere false — stesso principio già in uso per
        // Project::is_default_editorial.
        $data['is_default'] = $request->boolean('is_default');
        $data['status'] = CommunicationSenderProfile::STATUS_ACTIVE;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $senderProfile = CommunicationSenderProfile::create($data);

        return redirect()->route('admin.comunicazione.sender-profiles.show', $senderProfile)->with('success', 'Mittente creato.');
    }

    public function show(CommunicationSenderProfile $senderProfile)
    {
        return view('admin.communication.sender-profiles.show', [
            'senderProfile' => $senderProfile,
            'campaignsCount' => $senderProfile->campaigns()->count(),
        ]);
    }

    public function edit(CommunicationSenderProfile $senderProfile)
    {
        return view('admin.communication.sender-profiles.form', [
            'senderProfile' => $senderProfile,
            'providerOptions' => CommunicationSenderProfile::providerOptions(),
        ]);
    }

    public function update(UpdateCommunicationSenderProfileRequest $request, CommunicationSenderProfile $senderProfile)
    {
        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');
        $data['updated_by'] = auth()->id();

        $senderProfile->update($data);

        return redirect()->route('admin.comunicazione.sender-profiles.show', $senderProfile)->with('success', 'Mittente aggiornato.');
    }

    public function archive(CommunicationSenderProfile $senderProfile)
    {
        $senderProfile->update(['status' => CommunicationSenderProfile::STATUS_ARCHIVED, 'updated_by' => auth()->id()]);

        return redirect()->route('admin.comunicazione.sender-profiles.show', $senderProfile)->with('success', 'Mittente archiviato.');
    }

    /**
     * Un mittente usato da almeno una campagna non può essere eliminato:
     * archiviarlo è sempre sicuro (nessun dato perso) — stesso pattern già
     * in uso in CommunicationTemplateController::destroy().
     */
    public function destroy(CommunicationSenderProfile $senderProfile)
    {
        if ($senderProfile->campaigns()->exists()) {
            return back()->with('error', 'Questo mittente è usato da almeno una campagna: archivialo invece di eliminarlo.');
        }

        $senderProfile->delete();

        return redirect()->route('admin.comunicazione.sender-profiles.index')->with('success', 'Mittente eliminato.');
    }
}
