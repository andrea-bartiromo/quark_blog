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
        return view('admin.content-clusters.form', [
            'cluster' => null,
            'articles' => Article::query()->orderBy('title')->get(['id', 'title', 'status', 'published_at']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $memberships = $this->selectedMemberships($data['membership_ids'] ?? [], $data['memberships'] ?? []);
        $pillar = isset($data['pillar_article_id']) ? (int) $data['pillar_article_id'] : null;
        unset($data['membership_ids'], $data['memberships'], $data['pillar_article_id']);

        $cluster = DB::transaction(function () use ($data, $memberships, $pillar) {
            $cluster = ContentCluster::create($data);
            $this->memberships->sync($cluster, $memberships, $pillar);

            return $cluster;
        });

        return redirect()->route('admin.content-clusters.edit', $cluster)->with('success', 'Percorso creato.');
    }

    public function edit(ContentCluster $contentCluster)
    {
        $contentCluster->load(['articles', 'pillarArticle']);

        return view('admin.content-clusters.form', [
            'cluster' => $contentCluster,
            'articles' => Article::query()->orderBy('title')->get(['id', 'title', 'status', 'published_at']),
            'health' => $this->health->evaluate($contentCluster),
        ]);
    }

    public function update(Request $request, ContentCluster $contentCluster)
    {
        $data = $this->validated($request, $contentCluster);
        $memberships = $this->selectedMemberships($data['membership_ids'] ?? [], $data['memberships'] ?? []);
        $pillar = isset($data['pillar_article_id']) ? (int) $data['pillar_article_id'] : null;
        unset($data['membership_ids'], $data['memberships'], $data['pillar_article_id']);

        DB::transaction(function () use ($contentCluster, $data, $memberships, $pillar) {
            $contentCluster->update($data);
            $this->memberships->sync($contentCluster, $memberships, $pillar);
        });

        return redirect()->route('admin.content-clusters.edit', $contentCluster)->with('success', 'Percorso aggiornato.');
    }

    private function validated(Request $request, ?ContentCluster $cluster = null): array
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name'))),
        ]);

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
            'pillar_article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'membership_ids' => ['nullable', 'array'],
            'membership_ids.*' => ['integer', 'distinct', 'exists:articles,id'],
            'memberships' => ['nullable', 'array'],
            'memberships.*' => ['array'],
            'memberships.*.position' => ['nullable', 'integer', 'min:0'],
            'memberships.*.is_primary' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
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
