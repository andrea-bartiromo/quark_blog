<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusterMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentClusterController extends Controller
{
    public function __construct(private readonly ContentClusterMembershipService $memberships) {}

    public function index()
    {
        return view('admin.content-clusters.index', [
            'clusters' => ContentCluster::query()
                ->ordered()
                ->withCount('articles')
                ->with('pillarArticle:id,title')
                ->paginate(25),
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
        $memberships = $data['memberships'] ?? [];
        $pillar = isset($data['pillar_article_id']) ? (int) $data['pillar_article_id'] : null;
        unset($data['memberships'], $data['pillar_article_id']);

        $cluster = DB::transaction(function () use ($data, $memberships, $pillar) {
            $cluster = ContentCluster::create($data);
            $this->memberships->sync($cluster, $memberships, $pillar);

            return $cluster;
        });

        return redirect()->route('admin.content-clusters.edit', $cluster)->with('success', 'Percorso creato.');
    }

    public function edit(ContentCluster $contentCluster)
    {
        $contentCluster->load('articles');

        return view('admin.content-clusters.form', [
            'cluster' => $contentCluster,
            'articles' => Article::query()->orderBy('title')->get(['id', 'title', 'status', 'published_at']),
        ]);
    }

    public function update(Request $request, ContentCluster $contentCluster)
    {
        $data = $this->validated($request, $contentCluster);
        $memberships = $data['memberships'] ?? [];
        $pillar = isset($data['pillar_article_id']) ? (int) $data['pillar_article_id'] : null;
        unset($data['memberships'], $data['pillar_article_id']);

        DB::transaction(function () use ($contentCluster, $data, $memberships, $pillar) {
            $contentCluster->update($data);
            $this->memberships->sync($contentCluster, $memberships, $pillar);
        });

        return redirect()->route('admin.content-clusters.edit', $contentCluster)->with('success', 'Percorso aggiornato.');
    }

    private function validated(Request $request, ?ContentCluster $cluster = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('content_clusters', 'slug')->ignore($cluster?->id)],
            'short_description' => ['nullable', 'string', 'max:320'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'pillar_article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'memberships' => ['nullable', 'array'],
            'memberships.*.article_id' => ['required', 'integer', 'distinct', 'exists:articles,id'],
            'memberships.*.position' => ['nullable', 'integer', 'min:0'],
            'memberships.*.is_primary' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
