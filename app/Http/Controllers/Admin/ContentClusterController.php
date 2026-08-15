<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\Media;
use App\Services\ContentClusterHealth;
use App\Services\ContentClusterMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentClusterController extends Controller
{
    public function __construct(
        private readonly ContentClusterMembershipService $memberships,
        private readonly ContentClusterHealth $health,
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

        return view('admin.content-clusters.form', [
            'cluster' => $contentCluster,
            'catalog' => $catalog,
            'categories' => Article::query()->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'health' => $this->health->evaluate($contentCluster),
        ]);
    }

    public function update(Request $request, ContentCluster $contentCluster)
    {
        $data = $this->validatedCluster($request, $contentCluster);
        $hasMembershipPayload = $request->hasAny(['membership_ids', 'memberships', 'pillar_article_id']);

        DB::transaction(function () use ($request, $contentCluster, $data, $hasMembershipPayload) {
            $contentCluster->update($data);
            if ($hasMembershipPayload) {
                $membershipData = $this->validatedMemberships($request);
                $memberships = $this->selectedMemberships($membershipData['membership_ids'] ?? [], $membershipData['memberships'] ?? []);
                $pillar = isset($membershipData['pillar_article_id']) ? (int) $membershipData['pillar_article_id'] : null;
                $this->memberships->sync($contentCluster, $memberships, $pillar);
            }
        });

        return redirect()->route('admin.content-clusters.edit', $contentCluster)->with('success', $hasMembershipPayload ? 'Percorso aggiornato.' : 'Metadati del Percorso aggiornati.');
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
        $this->memberships->sync($contentCluster, $memberships, $pillar);

        return redirect()->route('admin.content-clusters.edit', $contentCluster)->with('success', 'Membership del Percorso aggiornate.');
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'takeaways' => ['nullable', 'array', 'max:4'],
            'takeaways.*' => ['nullable', 'string', 'max:320'],
            'guiding_questions' => ['nullable', 'array', 'max:4'],
            'guiding_questions.*' => ['nullable', 'string', 'max:320'],
            'closing_title' => ['nullable', 'string', 'max:255'],
            'closing_text' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['takeaways'] = $this->normalizeEditorialList($data['takeaways'] ?? []);
        $data['guiding_questions'] = $this->normalizeEditorialList($data['guiding_questions'] ?? []);
        $data['closing_title'] = $this->nullableTrimmed($data['closing_title'] ?? null);
        $data['closing_text'] = $this->nullableTrimmed($data['closing_text'] ?? null);

        return $data;
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
}
