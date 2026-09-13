<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportSearchConsoleCoverageCsvRequest;
use App\Models\SearchConsoleCoverageImport;
use App\Models\SearchConsoleCoverageIssue;
use App\Services\SearchConsole\SearchConsoleCoverageCsvImporter;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchConsoleCoverageController extends Controller
{
    public function index(Request $request): View
    {
        $imports = SearchConsoleCoverageImport::query()->latest('observed_at')->get();
        $selected = $request->filled('import') ? $imports->firstWhere('id', (int) $request->input('import')) : $imports->first();
        $classification = $request->input('classification');
        if (! array_key_exists((string) $classification, SearchConsoleCoverageIssue::classificationLabels())) $classification = null;
        $issues = $selected ? $selected->issues()->when($classification, fn ($q) => $q->where('classification', $classification))->orderBy('classification')->orderByDesc('page_count')->get() : collect();

        return view('admin.search-console-coverage.index', compact('imports', 'selected', 'issues', 'classification'));
    }

    public function importForm(): View
    {
        return view('admin.search-console-coverage.import');
    }

    public function import(ImportSearchConsoleCoverageCsvRequest $request, SearchConsoleCoverageCsvImporter $importer): RedirectResponse
    {
        $file = $request->file('csv');
        $result = $importer->import($file->getRealPath(), $request->string('property')->toString(), Carbon::parse($request->input('observed_at')), $file->getClientOriginalName());
        if ($result['imported'] === 0) return back()->withErrors(['csv' => implode(' ', $result['errors'])]);
        $message = "Import Coverage completato: {$result['imported']} righe.";
        if ($result['errors'] !== []) $message .= ' '.count($result['errors']).' righe scartate.';
        return redirect()->route('admin.search-console-coverage', ['import' => $result['import']->id])->with('status', $message);
    }
}
