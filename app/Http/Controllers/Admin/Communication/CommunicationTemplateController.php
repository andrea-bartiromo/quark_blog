<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommunicationTemplateRequest;
use App\Http\Requests\Admin\UpdateCommunicationTemplateRequest;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = CommunicationTemplate::query()
            ->with('activeVersion')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('q').'%');
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.communication.templates.index', [
            'templates' => $templates,
            'statusOptions' => CommunicationTemplate::statusOptions(),
        ]);
    }

    public function create()
    {
        return view('admin.communication.templates.form', [
            'template' => new CommunicationTemplate,
            'version' => new CommunicationTemplateVersion,
            'typeOptions' => CommunicationCampaign::typeOptions(),
        ]);
    }

    public function store(StoreCommunicationTemplateRequest $request)
    {
        $data = $request->validated();

        $template = DB::transaction(function () use ($data) {
            $template = CommunicationTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? null,
                'status' => CommunicationTemplate::STATUS_ACTIVE,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $version = $this->createVersion($template, $data);

            $template->update(['active_version_id' => $version->id]);

            return $template;
        });

        return redirect()->route('admin.comunicazione.templates.show', $template)->with('success', 'Template creato.');
    }

    public function show(CommunicationTemplate $template)
    {
        $template->load(['activeVersion', 'versions.createdBy', 'createdBy', 'updatedBy']);

        return view('admin.communication.templates.show', [
            'template' => $template,
            'campaignsCount' => $template->campaigns()->count(),
        ]);
    }

    public function edit(CommunicationTemplate $template)
    {
        $template->load('activeVersion');

        return view('admin.communication.templates.form', [
            'template' => $template,
            'version' => $template->activeVersion ?? new CommunicationTemplateVersion,
            'typeOptions' => CommunicationCampaign::typeOptions(),
        ]);
    }

    /**
     * Non sovrascrive mai la versione corrente: ogni salvataggio crea una
     * nuova riga in comm_template_versions e sposta il puntatore
     * active_version_id — le campagne che referenziano una versione
     * precedente restano ancorate a quella (Blocco B3.2).
     */
    public function update(UpdateCommunicationTemplateRequest $request, CommunicationTemplate $template)
    {
        $data = $request->validated();

        $version = DB::transaction(function () use ($template, $data) {
            $template->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? null,
                'status' => $data['status'],
                'updated_by' => auth()->id(),
            ]);

            $version = $this->createVersion($template, $data);

            $template->update(['active_version_id' => $version->id]);

            return $version;
        });

        return redirect()->route('admin.comunicazione.templates.show', $template)->with('success', "Nuova versione creata (v{$version->version_number}).");
    }

    /**
     * Copia il template intero (non solo una versione): nome, descrizione,
     * tipo e il contenuto della versione attiva, in un template
     * indipendente con la propria versione 1.
     */
    public function duplicate(CommunicationTemplate $template)
    {
        $copy = DB::transaction(function () use ($template) {
            $source = $template->activeVersion;

            $copy = CommunicationTemplate::create([
                'name' => $template->name.' (copia)',
                'description' => $template->description,
                'type' => $template->type,
                'status' => CommunicationTemplate::STATUS_ACTIVE,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $version = CommunicationTemplateVersion::create([
                'template_id' => $copy->id,
                'version_number' => 1,
                'subject' => $source?->subject,
                'preheader' => $source?->preheader,
                'content' => $source?->content ?? ['body' => null],
                'metadata' => $source?->metadata,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $copy->update(['active_version_id' => $version->id]);

            return $copy;
        });

        return redirect()->route('admin.comunicazione.templates.show', $copy)->with('success', 'Template duplicato.');
    }

    /**
     * "Duplica da questa versione" (Blocco B3.4): non è un rollback
     * distruttivo — copia il contenuto di una versione passata in una
     * nuova versione, che diventa quella attiva. Le versioni intermedie
     * restano intatte nella cronologia.
     */
    public function duplicateVersion(CommunicationTemplate $template, CommunicationTemplateVersion $version)
    {
        abort_unless($version->template_id === $template->id, 404);

        $nextVersionNumber = DB::transaction(function () use ($template, $version) {
            $nextVersionNumber = $this->nextVersionNumber($template);

            $newVersion = CommunicationTemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => $nextVersionNumber,
                'subject' => $version->subject,
                'preheader' => $version->preheader,
                'content' => $version->content,
                'metadata' => $version->metadata,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $template->update(['active_version_id' => $newVersion->id, 'updated_by' => auth()->id()]);

            return $nextVersionNumber;
        });

        return redirect()->route('admin.comunicazione.templates.show', $template)
            ->with('success', "Versione {$version->version_number} duplicata come nuova versione {$nextVersionNumber}.");
    }

    public function archive(CommunicationTemplate $template)
    {
        $template->update(['status' => CommunicationTemplate::STATUS_ARCHIVED, 'updated_by' => auth()->id()]);

        return redirect()->route('admin.comunicazione.templates.show', $template)->with('success', 'Template archiviato.');
    }

    /**
     * Un template usato da almeno una campagna non può essere eliminato:
     * archiviarlo è sempre sicuro (nessun dato perso), eliminarlo lo
     * sarebbe solo se nessuna campagna lo referenzia ancora.
     */
    public function destroy(CommunicationTemplate $template)
    {
        if ($template->campaigns()->exists()) {
            return back()->with('error', 'Questo template è usato da almeno una campagna: archivialo invece di eliminarlo.');
        }

        $template->delete();

        return redirect()->route('admin.comunicazione.templates.index')->with('success', 'Template eliminato.');
    }

    public function preview(Request $request, CommunicationTemplate $template)
    {
        $versionId = $request->query('versione');
        $version = $versionId ? $template->versions()->findOrFail($versionId) : $template->activeVersion;

        abort_if(! $version, 404);

        return view('admin.communication.templates.preview', [
            'template' => $template,
            'version' => $version,
        ]);
    }

    /**
     * Blocca la riga del template (row lock, no-op su SQLite) prima di
     * leggere il numero di versione massimo, così due salvataggi
     * concorrenti sullo stesso template non calcolano mai lo stesso
     * prossimo numero di versione.
     */
    private function nextVersionNumber(CommunicationTemplate $template): int
    {
        CommunicationTemplate::whereKey($template->id)->lockForUpdate()->first();

        return ($template->versions()->max('version_number') ?? 0) + 1;
    }

    private function createVersion(CommunicationTemplate $template, array $data): CommunicationTemplateVersion
    {
        return CommunicationTemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => $this->nextVersionNumber($template),
            'subject' => $data['subject'],
            'preheader' => $data['preheader'] ?? null,
            'content' => ['body' => $data['body'] ?? null],
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
