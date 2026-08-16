<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentCluster;
use App\Models\ContentClusterSuggestion;
use App\Services\ContentClusterSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentClusterSuggestionController extends Controller
{
    public function __construct(private readonly ContentClusterSuggestionService $suggestions) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:pending,accepted,rejected,stale'],
            'cluster' => ['nullable', 'integer', 'exists:content_clusters,id'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = ContentClusterSuggestion::query()
            ->with(['article:id,title,slug,status,published_at', 'contentCluster:id,name,slug,is_active'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'stale' THEN 1 WHEN 'accepted' THEN 2 ELSE 3 END")
            ->orderByDesc('confidence')
            ->orderBy('id');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['cluster'])) {
            $query->where('content_cluster_id', (int) $data['cluster']);
        }
        if (! empty($data['q'])) {
            $term = $data['q'];
            $query->whereHas('article', fn ($articleQuery) => $articleQuery->where('title', 'like', '%'.$term.'%'));
        }

        $suggestions = $query->paginate(30)->withQueryString();
        $articleIds = $suggestions->getCollection()->pluck('article_id')->unique()->values();
        $primaryByArticle = $articleIds->isEmpty()
            ? collect()
            : DB::table('article_content_cluster')
                ->join('content_clusters', 'content_clusters.id', '=', 'article_content_cluster.content_cluster_id')
                ->whereIn('article_content_cluster.article_id', $articleIds)
                ->where('article_content_cluster.is_primary', true)
                ->get(['article_content_cluster.article_id', 'content_clusters.id as cluster_id', 'content_clusters.name as cluster_name'])
                ->keyBy('article_id');

        $suggestions->getCollection()->each(function (ContentClusterSuggestion $suggestion) use ($primaryByArticle) {
            $primary = $primaryByArticle->get($suggestion->article_id);
            $suggestion->setAttribute('primary_conflict', $suggestion->suggested_primary && $primary && (int) $primary->cluster_id !== $suggestion->content_cluster_id ? $primary->cluster_name : null);
        });

        return view('admin.content-clusters.suggestions', [
            'suggestions' => $suggestions,
            'clusters' => ContentCluster::query()->ordered()->get(['id', 'name']),
        ]);
    }

    public function regenerate()
    {
        $result = $this->suggestions->regenerate();

        return back()->with('success', "Suggerimenti rigenerati: {$result['pending']} pending, {$result['stale']} stale, {$result['unchanged_rejected']} rifiutati invariati.");
    }

    public function accept(Request $request, ContentClusterSuggestion $suggestion)
    {
        $this->suggestions->accept($suggestion, $request->user());

        return back()->with('success', 'Suggerimento accettato. La membership è stata applicata senza modificare altre membership.');
    }

    public function reject(Request $request, ContentClusterSuggestion $suggestion)
    {
        $this->suggestions->reject($suggestion, $request->user());

        return back()->with('success', 'Suggerimento rifiutato.');
    }
}
