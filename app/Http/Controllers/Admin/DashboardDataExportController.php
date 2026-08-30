<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardDataExportRequest;
use App\Services\DataExport\DashboardDataExportService;
use App\Services\DataExport\DashboardExportPackageBuilder;
use App\Services\DataExport\ExportWindow;
use Illuminate\Support\Facades\Log;

final class DashboardDataExportController extends Controller
{
    public function __invoke(
        DashboardDataExportRequest $request,
        DashboardDataExportService $exporter,
        DashboardExportPackageBuilder $packages,
    ) {
        $validated = $request->validated();
        $window = ExportWindow::fromValidated($validated);
        $sections = array_values($validated['sections']);
        $format = $validated['format'];

        try {
            $datasets = $exporter->build($window, $sections);
            $recordCount = collect($datasets)->sum(fn (array $dataset) => count($dataset['rows']));

            Log::info('dashboard_data_export', [
                'admin_id' => $request->user()->id,
                'interval' => $window->metadata(),
                'sections' => $sections,
                'format' => $format,
                'outcome' => 'success',
                'record_count' => $recordCount,
            ]);

            if ($format === 'csv') {
                $section = $sections[0];

                return response($packages->csvExport($datasets[$section]), 200, $this->headers(
                    'text/csv; charset=UTF-8',
                    'kairus-'.$section.'-'.now()->format('Ymd-His').'.csv',
                ));
            }

            if ($format === 'json') {
                return response($packages->jsonExport($datasets, $window, $sections), 200, $this->headers(
                    'application/json; charset=UTF-8',
                    'kairus-dashboard-export-'.now()->format('Ymd-His').'.json',
                ));
            }

            $package = $packages->zip($datasets, $window, $sections);

            return response()->download($package['path'], $package['filename'], [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            Log::warning('dashboard_data_export', [
                'admin_id' => $request->user()->id,
                'interval' => $window->metadata(),
                'sections' => $sections,
                'format' => $format,
                'outcome' => 'failure',
                'exception_class' => $exception::class,
            ]);

            abort(500, 'Esportazione non disponibile. Nessun file è stato conservato.');
        }
    }

    /** @return array<string, string> */
    private function headers(string $type, string $filename): array
    {
        return [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
