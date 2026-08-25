<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\Media;
use App\Services\ContentClusterHealth;
use App\Services\ContentClusterMembershipService;
use App\Services\ContentClusters\PercorsiActivationCalendarService;
use App\Services\ContentClusters\PercorsiAutomationObservability;
use App\Services\ContentClusters\PercorsoCoverageAuditService;
use App\Services\ContentClusters\PercorsoPrefixForecastService;
use App\Services\ContentClusters\PercorsoPublicationReadinessService;
use App\Services\ContentClusters\PercorsoSubscriberNotificationReadinessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentClusterController extends Controller
{
    public function __construct(
        private readonly ContentClusterMembershipService $memberships,
        private readonly ContentClusterHealth $health,
        private readonly PercorsiAutomationObservability $automation,
        private readonly PercorsoPublicationReadinessService $readiness,
        private readonly PercorsoCoverageAuditService $coverageAudit,
        private readonly PercorsiActivationCalendarService $activationCalendar,
        private readonly PercorsoPrefixForecastService $prefixForecast,
        private readonly PercorsoSubscriberNotificationReadinessService $subscriberReadiness,
    ) {}

    public function index()
    {
        $clusters = ContentCluster::query()
            ->ordered()
            ->with(['articles:id,title,status,published_at', 'pillarArticle:id,title,status,published_at'])
            ->paginate(25);

        $pageArticleIds = $clusters->getCollection()
            ->flatMap(fn (ContentCluster $cluster) => $cluster->articles->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $globallyPrimaryArticleIds = $this->health->primaryArticleIds($pageArticleIds);

        $clusters->getCollection()->each(function (ContentCluster $cluster) use ($globallyPrimaryArticleIds) {
            $cluster->setAttribute('health', $this->health->evaluate($cluster, $globallyPrimaryArticleIds));
        });

        return view('admin.content-clusters.index', [
            'clusters' => $clusters,
            'orphans' => $this->health->orphanCounts(),
            'automation' => $this->automation->summary(),
            'activationCalendar' => $this->activationCalendar->summary(),
        ]);
    }

    public function create()
    {
        return view('admin.content-clusters.form', ['cluster' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedCluster($request);
        $hasMembershipPayload = $request->hasAny(['membership_ids', 'memberships', 'pillar_article_id']);
        $membershipData = $hasMembershipPayload ? $this->validatedMemberships($request) : [];
        $memberships = $hasMembershipPayload ? $this->selectedMemberships($membershipData['membership_ids'] ?? [], $membershipData['memberships'] ?? []) : [];
        $pillar = $hasMembershipPayload && isset($membershipData['pillar_article_id']) ? (int) $membershipData['pillar_article_id'] : null;

        $cluster = DB::transaction(function () use ($data, $hasMembershipPayload, $memberships, $pillar) {
            $cluster = ContentCluster::create($data);
            if ($hasMembershipPayload) {
                $this->memberships->sync($cluster, $memberships, $pillar);
            }

            return $cluster;
        });

        return redirect()->route('admin.content-clusters.edit', $cluster)->with('success', 'Percorso creato. Ora puoi aggiungere gli articoli dal catalogo paginato.');
    }

    public function mediaPicker(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));

        $media = Media::query()
            ->images()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('filename', 'like', '%'.$search.'%')
                        ->orWhere('disk_name', 'like', '%'.$search.'%')
                        ->orWhere('alt_text', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(24);

        return response()->json([
            'data' => $media->getCollection()->map(fn (Media $item) => [
                'id' => $item->id,
                'filename' => $item->filename,
                'disk_name' => $item->disk_name,
                'url' => $item->url,
                'alt_text' => $item->alt_text,
            ])->values(),
            'current_page' => $media->currentPage(),
            'last_page' => $media->lastPage(),
            'total' => $media->total(),
        ]);
    }

    public function edit(Request $request, ContentCluster $contentCluster)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(Article::statusOptions()))],
            'category' => ['nullable', 'string', 'max:120'],
        ]);
        $contentCluster->load(['articles', 'pillarArticle']);
        $selectedIds = $contentCluster->articles->pluck('id');

        $catalog = Article::query()
            ->select(['id', 'title', 'status', 'published_at', 'category'])
            ->when($selectedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $selectedIds))
            ->when(! empty($filters['q']), fn ($query) => $query->where('title', 'like', '%'.$filters['q'].'%'))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->orderBy('title')
            ->paginate(30)
            ->withQueryString();

        $transitionTextGaps = collect($this->readiness->evaluate($contentCluster)['findings'])
            ->firstWhere('code', 'TRANSITION_TEXT_GAPS');
        $orderHealth = $this->coverageAudit->orderHealthForCluster($contentCluster);

        return view('admin.content-clusters.form', [
            'cluster' => $contentCluster,
            'catalog' => $catalog,
            'categories' => Article::query()->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'health' => $this->health->evaluate($contentCluster),
            'missingTransitionArticleIds' => collect($transitionTextGaps['detail'] ?? [])->pluck('id')->all(),
            'orderHealthFlagsByArticleId' => $this->orderHealthFlagsByArticleId($orderHealth, $contentCluster),
            'completeWithHiddenRemainder' => $orderHealth['complete_with_hidden_remainder'],
            'prefixForecast' => $this->prefixForecast->forecast($contentCluster),
            'subscriberReadiness' => $this->subscriberReadiness->summary($contentCluster),
        ]);
    }

    public function update(Request $request, ContentCluster $contentCluster)
    {
        $data = $this->validatedCluster($request, $contentCluster);
        $hasMembershipPayload = $request->hasAny(['membership_ids', 'memberships', 'pillar_article_id']);
        $duplicatePositionWarning = null;

        DB::transaction(function () use ($request, $contentCluster, $data, $hasMembershipPayload, &$duplicatePositionWarning) {
            $contentCluster->update($data);
            if ($hasMembershipPayload) {
                $membershipData = $this->validatedMemberships($request);
                $memberships = $this->selectedMemberships($membershipData['membership_ids'] ?? [], $membershipData['memberships'] ?? []);
                $pillar = isset($membershipData['pillar_article_id']) ? (int) $membershipData['pillar_article_id'] : null;
                $duplicatePositionWarning = $this->duplicatePositionWarning($memberships);
                $this->memberships->sync($contentCluster, $memberships, $pillar);
            }
        });

        $redirect = redirect()->route('admin.content-clusters.edit', $contentCluster)
            ->with('success', $hasMembershipPayload ? 'Percorso aggiornato.' : 'Metadati del Percorso aggiornati.');

        return $duplicatePositionWarning ? $redirect->with('warning', $duplicatePositionWarning) : $redirect;
    }

    public function updateMemberships(Request $request, ContentCluster $contentCluster)
    {
        $data = $this->validatedMemberships($request);
        $ids = collect($data['membership_ids'] ?? [])->map(fn ($id) => (int) $id);
        if (isset($data['remove_article_id'])) {
            $ids = $ids->reject(fn (int $id) => $id === (int) $data['remove_article_id']);
        }

        $memberships = $this->selectedMemberships($ids->values()->all(), $data['memberships'] ?? []);
        $pillar = isset($data['pillar_article_id']) ? (int) $data['pillar_article_id'] : null;
        $duplicatePositionWarning = $this->duplicatePositionWarning($memberships);
        $this->memberships->sync($contentCluster, $memberships, $pillar);

        $redirect = redirect()->route('admin.content-clusters.edit', $contentCluster)
            ->with('success', 'Membership del Percorso aggiornate.');

        return $duplicatePositionWarning ? $redirect->with('warning', $duplicatePositionWarning) : $redirect;
    }

    public function addMembership(ContentCluster $contentCluster, Article $article)
    {
        $this->memberships->addMembership($contentCluster, $article);

        return back()->with('success', 'Articolo aggiunto al Percorso come membership secondaria.');
    }

    private function validatedCluster(Request $request, ?ContentCluster $cluster = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('content_clusters', 'slug')->ignore($cluster?->id)],
            'short_description' => ['nullable', 'string', 'max:320'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'is_active' => ['nullable', 'boolean'],
            'publish_date' => ['nullable', 'date_format:Y-m-d', 'required_with:publish_time'],
            'publish_time' => ['nullable', 'date_format:H:i', 'required_with:publish_date'],
            'lifecycle_status' => ['nullable', Rule::in([ContentCluster::LIFECYCLE_UPDATING, ContentCluster::LIFECYCLE_COMPLETE])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'takeaways' => ['nullable', 'array', 'max:4'],
            'takeaways.*' => ['nullable', 'string', 'max:320'],
            'guiding_questions' => ['nullable', 'array', 'max:4'],
            'guiding_questions.*' => ['nullable', 'string', 'max:320'],
            'closing_title' => ['nullable', 'string', 'max:255'],
            'closing_text' => ['nullable', 'string', 'max:2000'],
            'curator_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['publish_at'] = ($data['publish_date'] ?? null) && ($data['publish_time'] ?? null)
            ? Carbon::createFromFormat('Y-m-d H:i', "{$data['publish_date']} {$data['publish_time']}", ContentCluster::EDITORIAL_TIMEZONE)->utc()
            : null;
        unset($data['publish_date'], $data['publish_time']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['takeaways'] = $this->normalizeEditorialList($data['takeaways'] ?? []);
        $data['guiding_questions'] = $this->normalizeEditorialList($data['guiding_questions'] ?? []);
        $data['closing_title'] = $this->nullableTrimmed($data['closing_title'] ?? null);
        $data['closing_text'] = $this->nullableTrimmed($data['closing_text'] ?? null);
        $data['curator_note'] = $this->nullableTrimmed($data['curator_note'] ?? null);

        return $data;
    }

    /**
     * Missione 16 (secondo batch autonomo KAIRUS, Fase C — Percorsi
     * Advanced Operations): "unique position validation". Non un rifiuto
     * hard: ContentClusterMembershipService::sync() risolve già in modo
     * deterministico posizioni duplicate/mancanti (vedi
     * test_ordering_is_deterministic_for_duplicate_missing_and_removed_positions),
     * un comportamento deliberato e già testato che questa missione non
     * deve rompere. Qui si rileva SOLO se l'input inviato dall'editor
     * conteneva duplicati, per avvisarlo che il riordino automatico ha
     * potuto non rispettare l'intento originale — mai per bloccare il
     * salvataggio.
     *
     * @param  array<int, array{article_id:int, position?:int|null}>  $memberships
     */
    private function duplicatePositionWarning(array $memberships): ?string
    {
        $positions = collect($memberships)->pluck('position')->filter(fn ($position) => $position !== null);
        $duplicateCount = $positions->count() - $positions->unique()->count();

        if ($duplicateCount === 0) {
            return null;
        }

        return 'Le posizioni inserite contenevano dei duplicati: sono state riordinate automaticamente. Verifica che la sequenza qui sotto corrisponda a quanto intendevi.';
    }

    private function validatedMemberships(Request $request): array
    {
        return $request->validate([
            'pillar_article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'membership_ids' => ['nullable', 'array'],
            'membership_ids.*' => ['integer', 'distinct', 'exists:articles,id'],
            'memberships' => ['nullable', 'array'],
            'memberships.*' => ['array'],
            'memberships.*.position' => ['nullable', 'integer', 'min:0'],
            'memberships.*.is_primary' => ['nullable', 'boolean'],
            'memberships.*.transition_text' => ['nullable', 'string', 'max:1000'],
            'remove_article_id' => ['nullable', 'integer', 'exists:articles,id'],
        ]);
    }

    /**
     * @param  array<int, int|string>  $membershipIds
     * @param  array<int|string, array<string, mixed>>  $membershipMetadata
     */
    private function selectedMemberships(array $membershipIds, array $membershipMetadata): array
    {
        return collect($membershipIds)
            ->map(function ($articleId) use ($membershipMetadata): array {
                $articleId = (int) $articleId;
                $metadata = $membershipMetadata[$articleId] ?? $membershipMetadata[(string) $articleId] ?? [];

                return [
                    'article_id' => $articleId,
                    'position' => isset($metadata['position']) && $metadata['position'] !== '' ? (int) $metadata['position'] : null,
                    'is_primary' => (bool) ($metadata['is_primary'] ?? false),
                    'transition_text' => $this->nullableTrimmed($metadata['transition_text'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function normalizeEditorialList(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $item) => $item !== '')
            ->values()
            ->all();
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Mission 13 — Publication Timeline View. Reduces the single-cluster
     * order-health row (PercorsoCoverageAuditService::orderHealthForCluster())
     * — never a recomputation of its rules — into a flat article_id => flag
     * codes map, so the edit page's timeline strip can flag each member row
     * with a simple lookup instead of re-walking every finding category.
     *
     * @param  array<string, mixed>  $orderHealth
     * @return array<int, list<string>>
     */
    private function orderHealthFlagsByArticleId(array $orderHealth, ContentCluster $cluster): array
    {
        $flags = [];
        $flag = function (array $articles, string $code) use (&$flags) {
            foreach ($articles as $article) {
                $flags[$article['id']][] = $code;
            }
        };

        $flag($orderHealth['missing_position'], 'missing_position');
        $flag($orderHealth['non_positive_position'], 'non_positive_position');
        $flag($orderHealth['published_beyond_gap'], 'published_beyond_gap');

        foreach ($orderHealth['chronological_inversions'] as $pair) {
            $flags[$pair['later_position']['id']][] = 'chronological_inversion';
        }
        foreach ($orderHealth['scheduled_out_of_order'] as $pair) {
            $flags[$pair['later_position']['id']][] = 'scheduled_out_of_order';
        }
        if ($orderHealth['dangling_transition'] !== null) {
            $flags[$orderHealth['dangling_transition']['id']][] = 'dangling_transition';
        }

        foreach ($cluster->articles as $article) {
            if (in_array($article->pivot?->position, $orderHealth['duplicate_position'], true)) {
                $flags[$article->id][] = 'duplicate_position';
            }
        }

        return $flags;
    }
}
