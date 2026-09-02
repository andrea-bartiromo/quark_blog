<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSocialDraftRequest;
use App\Http\Requests\Admin\UpdateSocialDraftRequest;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\SocialDraft;
use App\Services\SocialWorkspace\SocialDraftCopyBuilder;
use App\Services\SocialWorkspace\SocialDraftStateMachine;
use App\Services\SocialWorkspace\SocialDraftValidationException;
use App\Services\SocialWorkspace\SocialDraftWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Workspace Social Admin V1 — controller sottile: ogni regola di dominio
 * (transizioni, collisioni, timezone, blocco campi dopo approvazione) vive
 * in SocialDraftWorkspaceService. Nessuna chiamata esterna, nessun
 * provider, nessuna scrittura su social_publications.
 */
class SocialDraftController extends Controller
{
    public function __construct(
        private readonly SocialDraftWorkspaceService $workspace,
        private readonly SocialDraftStateMachine $stateMachine,
    ) {}

    public function index(Request $request): View
    {
        $channel = $request->string('channel')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;
        $search = $request->string('q')->toString() ?: null;

        $query = SocialDraft::query()->with(['article:id,title,slug,status']);

        if ($channel && array_key_exists($channel, SocialDraft::CHANNELS)) {
            $query->where('channel', $channel);
        }

        if ($status && in_array($status, [
            SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_REVIEWED,
            SocialDraft::STATUS_APPROVED, SocialDraft::STATUS_SCHEDULED,
            SocialDraft::STATUS_PUBLISHED, SocialDraft::STATUS_FAILED,
        ], true)) {
            $query->where('status', $status);
        }

        if ($from) {
            $query->where('scheduled_at', '>=', $from);
        }

        if ($to) {
            $query->where('scheduled_at', '<=', $to);
        }

        if ($search) {
            $query->whereHas('article', fn ($q) => $q->where('title', 'like', '%'.$search.'%'));
        }

        $drafts = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        // Un'unica query aggiuntiva per marcare le collisioni nella pagina
        // corrente, senza N+1: le combinazioni canale+istante "scheduled"
        // che compaiono più di una volta in tutta la tabella.
        $collidingKeys = SocialDraft::query()
            ->where('status', SocialDraft::STATUS_SCHEDULED)
            ->select('channel', 'scheduled_at', DB::raw('count(*) as total'))
            ->groupBy('channel', 'scheduled_at')
            ->having('total', '>', 1)
            ->get()
            ->map(fn ($row) => $row->channel.'|'.$row->scheduled_at)
            ->all();

        return view('admin.social-drafts.index', [
            'drafts' => $drafts,
            'channelOptions' => SocialDraft::CHANNELS,
            'channel' => $channel,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'hasActiveFilters' => (bool) ($channel || $status || $from || $to || $search),
            'collidingKeys' => $collidingKeys,
        ]);
    }

    public function create(Request $request): View
    {
        $articles = Article::query()
            ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED, Article::STATUS_DRAFT, Article::STATUS_REVIEW])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'title', 'slug', 'status', 'published_at']);

        $preselectedArticle = $request->integer('article_id') ?: null;

        return view('admin.social-drafts.create', [
            'articles' => $articles,
            'channelOptions' => SocialDraft::CHANNELS,
            'preselectedArticle' => $preselectedArticle,
        ]);
    }

    public function store(StoreSocialDraftRequest $request): RedirectResponse
    {
        $article = Article::findOrFail($request->integer('article_id'));
        $copy = filled($request->input('copy'))
            ? $request->string('copy')->toString()
            : app(SocialDraftCopyBuilder::class)->initial($article);

        try {
            $draft = $this->workspace->create($article, $request->string('channel')->toString(), $copy, $request->user());
        } catch (SocialDraftValidationException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.social-drafts.show', $draft)->with('success', 'Bozza Social creata.');
    }

    public function show(SocialDraft $socialDraft): View
    {
        $socialDraft->load(['article', 'createdBy', 'reviewedBy', 'approvedBy']);

        $history = ActivityLog::query()
            ->where('subject_type', 'social_draft')
            ->where('subject_id', $socialDraft->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.social-drafts.show', [
            'draft' => $socialDraft,
            'allowedTargets' => $this->stateMachine->allowedTargets($socialDraft->status),
            'naturalUrl' => $this->safe(fn () => $this->workspace->naturalUrl($socialDraft)),
            'previewUrl' => $this->safe(fn () => $this->workspace->previewUrl($socialDraft)),
            'editorialScheduledAt' => $this->workspace->editorialScheduledAt($socialDraft),
            'history' => $history,
            'isEditableFully' => in_array($socialDraft->status, [SocialDraft::STATUS_DRAFT, SocialDraft::STATUS_REVIEWED], true),
            'isLocked' => $socialDraft->status === SocialDraft::STATUS_SCHEDULED,
        ]);
    }

    public function update(UpdateSocialDraftRequest $request, SocialDraft $socialDraft): RedirectResponse
    {
        try {
            $this->workspace->update($socialDraft, $request->only([
                'copy', 'destination_url', 'use_utm', 'utm_campaign', 'scheduled_date', 'scheduled_time',
            ]));
        } catch (SocialDraftValidationException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.social-drafts.show', $socialDraft)->with('success', 'Bozza aggiornata.');
    }

    public function transition(Request $request, SocialDraft $socialDraft): RedirectResponse
    {
        $to = $request->string('to')->toString();

        try {
            $this->workspace->transition($socialDraft, $to, $request->user());
        } catch (SocialDraftValidationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.social-drafts.show', $socialDraft)->with('success', 'Stato aggiornato.');
    }

    /**
     * Un URL personalizzato non valido salvato prima di un'eventuale
     * modifica del dominio applicativo non deve rompere la pagina di
     * dettaglio: mostra un avviso invece di un errore fatale.
     */
    private function safe(\Closure $resolver): ?string
    {
        try {
            return $resolver();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
