<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentCluster;
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
        $contentCluster->update($this->validatedCluster($request, $contentCluster));

        return redirect()->route('admin.content-clusters.edit', $contentCluster)->with('success', 'Metadati del Percorso aggiornati.');
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
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

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
                ];
            })
            ->values()
            ->all();
    }
}
