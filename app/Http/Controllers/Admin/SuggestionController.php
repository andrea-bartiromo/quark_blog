<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\NewsSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class SuggestionController extends Controller
{
    /**
     * Lista dei suggerimenti generati automaticamente.
     */
    public function index()
    {
        return view('admin.suggestions', [
            'suggestions' => NewsSuggestion::latest()->paginate(20),
            'counts' => [
                'pending' => NewsSuggestion::where('status', 'pending')->count(),
                'approved' => NewsSuggestion::where('status', 'approved')->count(),
                'published' => NewsSuggestion::where('status', 'published')->count(),
                'rejected' => NewsSuggestion::where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * 🔄 Fetch manuale dei suggerimenti (bottone "Aggiorna ora")
     */
    public function fetch()
    {
        try {
            Artisan::call('news:fetch');

            return redirect()
                ->route('admin.suggestions')
                ->with('success', 'Suggerimenti aggiornati correttamente.');
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.suggestions')
                ->with('error', 'Errore durante il fetch: '.$e->getMessage());
        }
    }

    /**
     * Approva un suggerimento.
     */
    public function approve(NewsSuggestion $suggestion)
    {
        $suggestion->update(['status' => 'approved']);

        return back()->with('success', 'Suggerimento approvato. Ora puoi pubblicarlo.');
    }

    /**
     * Pubblica come articolo bozza.
     */
    public function publish(Request $request, NewsSuggestion $suggestion)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'excerpt' => 'nullable|max:300',
            'body' => 'required',
            'category' => 'required',
        ]);

        $article = Article::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'excerpt' => $validated['excerpt'],
            'body' => $validated['body'],
            'category' => $validated['category'],
            'status' => 'draft',
            'featured' => false,
            'read_minutes' => max(1, (int) (str_word_count($validated['body']) / 200)),
        ]);

        $suggestion->update([
            'status' => 'published',
            'article_id' => $article->id,
        ]);

        return redirect()
            ->route('admin.articles.edit', $article)
            ->with('success', 'Bozza creata. Revisiona e pubblica quando sei pronto.');
    }

    /**
     * Scarta suggerimento.
     */
    public function destroy(NewsSuggestion $suggestion)
    {
        $suggestion->update(['status' => 'rejected']);

        return back()->with('success', 'Suggerimento scartato.');
    }
}
