<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TuringChapterSource;
use App\Services\Turing\TuringNavigationMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cantiere 61 (programma "100 cantieri Kairus"): gestione admin (sola
 * scrittura da parte di un editor umano, mai automatica) del registro
 * fonti per capitolo dello Speciale Turing. Vedi il docblock di
 * App\Models\TuringChapterSource per il razionale.
 */
class TuringChapterSourceController extends Controller
{
    private function realChapters(): array
    {
        return array_values(array_filter(
            TuringNavigationMetricsService::CHAPTERS,
            fn (string $chapter) => $chapter !== 'hub'
        ));
    }

    public function index(): View
    {
        $chapters = $this->realChapters();

        $sourcesByChapter = TuringChapterSource::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('chapter');

        return view('admin.turing-chapter-sources', [
            'chapters' => $chapters,
            'sourcesByChapter' => $sourcesByChapter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chapter' => 'required|in:'.implode(',', $this->realChapters()),
            'label' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'year' => 'nullable|string|max:20',
        ]);

        $nextSortOrder = 1 + (int) TuringChapterSource::query()
            ->where('chapter', $data['chapter'])
            ->max('sort_order');

        TuringChapterSource::create([
            ...$data,
            'sort_order' => $nextSortOrder,
        ]);

        return redirect()->route('admin.turing.chapter-sources')->with('success', 'Fonte aggiunta.');
    }

    public function destroy(TuringChapterSource $turingChapterSource): RedirectResponse
    {
        $turingChapterSource->delete();

        return redirect()->route('admin.turing.chapter-sources')->with('success', 'Fonte rimossa.');
    }
}
