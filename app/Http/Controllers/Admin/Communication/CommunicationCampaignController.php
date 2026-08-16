<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommunicationCampaignRequest;
use App\Http\Requests\Admin\UpdateCommunicationCampaignRequest;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignActivityLog;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\CommunicationTemplate;
use App\Models\Project;
use App\Services\Communication\CampaignRenderer;
use App\Services\Communication\RecipientSnapshotService;
use Illuminate\Http\Request;

class CommunicationCampaignController extends Controller
{
    /**
     * Dimensione pagina del selettore destinatario dell'anteprima
     * (ricerca server-side per email parziale, N2.1) — mai un preload di
     * tutti gli iscritti confermati, indipendentemente dalla scala.
     * L'anteprima resta uno strumento di anteprima, non di targeting/
     * segmentazione: la ricerca serve solo a trovare UN destinatario per
     * cui vedere il rendering reale.
     */
    private const PREVIEW_RECIPIENT_OPTIONS_LIMIT = 20;

    public function __construct(
        private readonly RecipientSnapshotService $recipientSnapshot,
        private readonly CampaignRenderer $campaignRenderer,
    ) {}

    /**
     * Elenco Campagne. Ricerca/filtri/ordinamento/paginazione sono quelli
     * introdotti in sola lettura nel Blocco 1 (B1); qui si aggiungono solo
     * le azioni di scrittura (Blocco 2 B2): Apri, Modifica, Duplica,
     * Elimina — mai Invia/Programma/Test, fuori perimetro di questo blocco.
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

    public function create(Request $request)
    {
        $campaign = new CommunicationCampaign;
        [$selectedTemplateId, $selectedTemplateVersionId, $prefill] = $this->resolveTemplateSelection($request, $campaign);

        return view('admin.communication.campaigns.form', [
            'campaign' => $campaign,
            'projectOptions' => $this->projectOptions(),
            'templateOptions' => $this->templateOptions(),
            'senderProfileOptions' => $this->senderProfileOptions(),
            'selectedTemplateId' => $selectedTemplateId,
            'selectedTemplateVersionId' => $selectedTemplateVersionId,
            'prefill' => $prefill,
        ]);
    }

    public function store(StoreCommunicationCampaignRequest $request)
    {
        $data = $request->validated();
        $body = $data['body'] ?? null;
        unset($data['body']);

        $data['status'] = CommunicationCampaign::STATUS_DRAFT;
        $data['content'] = ['body' => $body];
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $campaign = CommunicationCampaign::create($data);

        CommunicationCampaignActivityLog::record(
            campaign: $campaign,
            subjectType: 'campaign',
            subjectId: $campaign->id,
            subjectTitle: $campaign->title,
            action: 'Campagna creata',
            userId: auth()->id(),
        );

        return redirect()->route('admin.comunicazione.campaigns.show', $campaign)->with('success', 'Campagna creata.');
    }

    public function show(Request $request, CommunicationCampaign $campaign)
    {
        $campaign->load(['project', 'createdBy', 'updatedBy', 'template', 'templateVersion', 'senderProfile']);

        $activeTab = $request->string('tab')->value() ?: 'overview';

        $activityLog = $activeTab === 'history'
            ? $campaign->activityLogs()->with('user')->orderByDesc('created_at')->orderByDesc('id')->get()
            : null;

        $recipientCounts = $activeTab === 'sends'
            ? [
                'prepared' => $campaign->sends()->count(),
                'eligible_total' => CommunicationSubscriber::confirmed()->count(),
            ]
            : null;

        return view('admin.communication.campaigns.show', [
            'campaign' => $campaign,
            'activeTab' => $activeTab,
            'typeOptions' => CommunicationCampaign::typeOptions(),
            'statusOptions' => CommunicationCampaign::statusOptions(),
            'activityLog' => $activityLog,
            'recipientCounts' => $recipientCounts,
            'canPrepareRecipients' => $this->recipientSnapshot->canPrepare($campaign),
        ]);
    }

    public function edit(Request $request, CommunicationCampaign $campaign)
    {
        [$selectedTemplateId, $selectedTemplateVersionId, $prefill] = $this->resolveTemplateSelection($request, $campaign);

        return view('admin.communication.campaigns.form', [
            'campaign' => $campaign,
            'projectOptions' => $this->projectOptions(),
            'templateOptions' => $this->templateOptions(),
            'senderProfileOptions' => $this->senderProfileOptions(),
            'selectedTemplateId' => $selectedTemplateId,
            'selectedTemplateVersionId' => $selectedTemplateVersionId,
            'prefill' => $prefill,
        ]);
    }

    public function update(UpdateCommunicationCampaignRequest $request, CommunicationCampaign $campaign)
    {
        $before = $campaign->only(['title', 'type', 'project_id']);

        $data = $request->validated();
        $body = $data['body'] ?? null;
        unset($data['body']);

        $data['content'] = ['body' => $body];
        $data['updated_by'] = auth()->id();

        $campaign->update($data);

        CommunicationCampaignActivityLog::record(
            campaign: $campaign,
            subjectType: 'campaign',
            subjectId: $campaign->id,
            subjectTitle: $campaign->title,
            action: 'Campagna modificata',
            userId: auth()->id(),
            oldValue: $before['title'],
            newValue: $campaign->title,
        );

        return redirect()->route('admin.comunicazione.campaigns.show', $campaign)->with('success', 'Campagna aggiornata.');
    }

    /**
     * Copia titolo, tipo, progetto e contenuto della campagna originale.
     * Genera un nuovo UUID (l'hook creating() lo rigenera perché lo si
     * azzera esplicitamente) e riparte sempre da bozza, senza date né
     * riferimenti a invii mai avvenuti — coerente col fatto che replicate()
     * non copia le relazioni hasMany (comm_sends resta vuoto per la copia).
     */
    public function duplicate(CommunicationCampaign $campaign)
    {
        $copy = $campaign->replicate();
        $copy->uuid = null;
        $copy->title = $campaign->title.' (copia)';
        $copy->status = CommunicationCampaign::STATUS_DRAFT;
        $copy->scheduled_at = null;
        $copy->sending_started_at = null;
        $copy->completed_at = null;
        $copy->idempotency_key = null;
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();

        CommunicationCampaignActivityLog::record(
            campaign: $copy,
            subjectType: 'campaign',
            subjectId: $copy->id,
            subjectTitle: $copy->title,
            action: 'Campagna duplicata da «'.$campaign->title.'»',
            userId: auth()->id(),
        );

        return redirect()->route('admin.comunicazione.campaigns.show', $copy)->with('success', 'Campagna duplicata.');
    }

    public function destroy(CommunicationCampaign $campaign)
    {
        $title = $campaign->title;

        CommunicationCampaignActivityLog::record(
            campaign: $campaign,
            subjectType: 'campaign',
            subjectId: $campaign->id,
            subjectTitle: $title,
            action: 'Campagna eliminata',
            userId: auth()->id(),
        );

        $campaign->delete();

        return redirect()->route('admin.comunicazione.campaigns.index')->with('success', 'Campagna eliminata.');
    }

    /**
     * Anteprima reale: stesso CampaignRenderer che il futuro invio dovrà
     * riusare, con un destinatario CONFERMATO reale selezionabile (o un
     * segnaposto esplicito se la campagna non ne ha ancora nessuno). Solo
     * lettura: nessuna riga viene creata/modificata/marcata da questa
     * azione, indipendentemente da quante volte viene aperta.
     *
     * Selettore ricerca (N2.1): server-side, per email parziale, paginato
     * — mai un preload di tutti gli iscritti confermati. `q` è limitato
     * alla lunghezza della colonna email e i metacaratteri LIKE (% _ \)
     * sono escapati esplicitamente, così un carattere `%` digitato
     * dall'admin resta letterale invece di comportarsi da wildcard.
     */
    public function preview(Request $request, CommunicationCampaign $campaign)
    {
        $query = trim((string) $request->string('q'));
        $query = mb_substr($query, 0, 190);

        $recipientOptions = CommunicationSubscriber::confirmed()
            ->when($query !== '', fn ($q) => $q->whereRaw(
                'email LIKE ? ESCAPE ?',
                ['%'.addcslashes($query, '%_\\').'%', '\\']
            ))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->paginate(self::PREVIEW_RECIPIENT_OPTIONS_LIMIT, ['id', 'email'])
            ->withQueryString();

        $requestedId = $request->integer('subscriber_id') ?: null;

        $subscriber = $requestedId
            ? CommunicationSubscriber::confirmed()->find($requestedId)
            : null;

        // Nessuna selezione esplicita valida: rappresentativo = l'iscritto
        // confermato più recente TRA I RISULTATI CORRENTI (rispetta un
        // 'q' attivo), così l'anteprima mostra sempre dati reali coerenti
        // con la ricerca in corso, senza richiedere una scelta manuale.
        if (! $subscriber) {
            $subscriber = $recipientOptions->first()
                ? CommunicationSubscriber::confirmed()->find($recipientOptions->first()->id)
                : null;
        }

        $rendering = $this->campaignRenderer->render($campaign, $subscriber);

        return view('admin.communication.campaigns.preview', [
            'campaign' => $campaign,
            'rendering' => $rendering,
            'recipientOptions' => $recipientOptions,
            'selectedSubscriberId' => $subscriber?->id,
            'recipientQuery' => $query,
        ]);
    }

    /**
     * Newsletter 2.0, primo incremento: crea lo snapshot dei destinatari
     * (comm_sends, status=queued) SENZA inviare alcuna email — vedi
     * RecipientSnapshotService per idempotenza/eleggibilità/concorrenza.
     * Rieseguibile a piacere: ogni run aggiunge solo gli iscritti confermati
     * non ancora presenti, non tocca mai le righe già create.
     */
    public function prepareRecipients(CommunicationCampaign $campaign)
    {
        if (! $this->recipientSnapshot->canPrepare($campaign)) {
            return back()->withErrors([
                'recipients' => 'Impossibile preparare i destinatari per una campagna con stato "'.
                    (CommunicationCampaign::statusOptions()[$campaign->status] ?? $campaign->status).'".',
            ]);
        }

        $result = $this->recipientSnapshot->prepare($campaign);

        CommunicationCampaignActivityLog::record(
            campaign: $campaign,
            subjectType: 'recipients',
            subjectId: $campaign->id,
            subjectTitle: $campaign->title,
            action: "Destinatari preparati: {$result['added']} nuovi, {$result['already_present']} già presenti (totale iscritti confermati: {$result['eligible_total']})",
            userId: auth()->id(),
        );

        return redirect()
            ->route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'sends'])
            ->with('success', "{$result['added']} nuovi destinatari aggiunti. {$result['already_present']} erano già presenti.");
    }

    /**
     * Risolve quale template/versione mostrare nel form e quali campi di
     * contenuto proporre come suggerimento. Non sovrascrive mai un campo
     * che la campagna ha già valorizzato: il template compare solo dove
     * non c'è ancora nulla di scritto (Blocco B3.5).
     *
     * @return array{0: int|null, 1: int|null, 2: array<string, string|null>}
     */
    private function resolveTemplateSelection(Request $request, CommunicationCampaign $campaign): array
    {
        if (! $request->has('template_id')) {
            return [$campaign->template_id, $campaign->template_version_id, []];
        }

        $templateId = $request->query('template_id');

        if (blank($templateId)) {
            return [null, null, []];
        }

        $template = CommunicationTemplate::active()->find($templateId);
        $version = $template?->activeVersion;

        if (! $template || ! $version) {
            return [null, null, []];
        }

        $currentBody = $campaign->content['body'] ?? null;

        $prefill = [
            'subject' => blank($campaign->subject) ? $version->subject : null,
            'preheader' => blank($campaign->preheader) ? $version->preheader : null,
            'body' => blank($currentBody) ? ($version->content['body'] ?? null) : null,
        ];

        return [$template->id, $version->id, $prefill];
    }

    private function templateOptions()
    {
        return CommunicationTemplate::active()->orderBy('name')->get(['id', 'name']);
    }

    private function senderProfileOptions()
    {
        return CommunicationSenderProfile::active()->orderBy('name')->get(['id', 'name', 'from_email']);
    }

    private function projectOptions()
    {
        return Project::orderBy('title')->get(['id', 'title']);
    }
}
