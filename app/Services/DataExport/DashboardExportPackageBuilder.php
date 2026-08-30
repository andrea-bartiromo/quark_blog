<?php

namespace App\Services\DataExport;

use Illuminate\Support\Str;
use ZipArchive;

final class DashboardExportPackageBuilder
{
    public function __construct(private readonly CsvSerializer $csv) {}

    /** @param array<string, mixed> $datasets @param list<string> $requested @return array{path:string,filename:string} */
    public function zip(array $datasets, ExportWindow $window, array $requested): array
    {
        $directory = storage_path('app/tmp/dashboard-exports');

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Impossibile creare la directory temporanea protetta.');
        }

        $this->pruneAbandoned($directory);

        $path = $directory.'/'.Str::random(40).'.zip';

        try {
            $files = $this->files($datasets);
            $manifest = $this->manifest($datasets, $files, $window, $requested);
            $files['manifest.json'] = $this->json($manifest);

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new \RuntimeException('Impossibile creare il pacchetto ZIP.');
            }

            foreach ($files as $name => $contents) {
                if (! $zip->addFromString($name, $contents)) {
                    $zip->close();
                    throw new \RuntimeException('Impossibile comporre il pacchetto ZIP.');
                }
            }
            $zip->close();
            chmod($path, 0600);

            return ['path' => $path, 'filename' => 'kairus-dashboard-export-'.now()->format('Ymd-His').'.zip'];
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }
            throw $exception;
        }
    }

    private function pruneAbandoned(string $directory): void
    {
        $threshold = now()->subMinutes(config('dashboard_data_export.temporary_file_ttl_minutes'))->getTimestamp();

        foreach (glob($directory.'/*.zip') ?: [] as $candidate) {
            if (is_file($candidate) && filemtime($candidate) < $threshold) {
                @unlink($candidate);
            }
        }
    }

    /** @param array<string, mixed> $datasets */
    public function jsonExport(array $datasets, ExportWindow $window, array $requested): string
    {
        $files = $this->files($datasets);

        return $this->json([
            'manifest' => $this->manifest($datasets, $files, $window, $requested),
            'datasets' => $datasets,
        ]);
    }

    /** @param array<string, mixed> $dataset */
    public function csvExport(array $dataset): string
    {
        return $this->csv->serialize($dataset['schema'], $dataset['rows']);
    }

    /** @param array<string, mixed> $datasets @return array<string, string> */
    private function files(array $datasets): array
    {
        $files = [];
        foreach ($datasets as $id => $dataset) {
            $extension = $id === 'dashboard-summary' ? 'json' : 'csv';
            $files[$id.'.'.$extension] = $extension === 'json'
                ? $this->json($dataset)
                : $this->csvExport($dataset);
        }

        $files['data-quality.json'] = $this->json([
            'missing_datasets' => ['continuation', 'search-opportunities', 'social-calendar'],
            'incomplete_intervals' => [],
            'insufficient_samples' => collect($datasets)->where('status', 'insufficient_data')->keys()->values()->all(),
            'legacy_records' => [],
            'unknown_values' => [],
            'unattributable_data' => [],
            'possible_duplicates' => [],
            'non_calculable_metrics' => ['newsletter delivery/open/bounce', 'social downstream engagement'],
            'privacy_controls' => ['explicit allowlists', 'no personal identifiers', 'small-sample threshold', 'CSV injection neutralization'],
        ]);

        return $files;
    }

    /** @param array<string, mixed> $datasets @param array<string, string> $files @param list<string> $requested @return array<string, mixed> */
    private function manifest(array $datasets, array $files, ExportWindow $window, array $requested): array
    {
        $included = array_keys($datasets);
        $unavailable = [
            'continuation' => 'Gli eventi correnti non espongono in modo affidabile tutti i tipi di transizione richiesti.',
            'search-opportunities' => 'Le query possono contenere dati personali e non esiste ancora una normalizzazione privacy certificata.',
            'social-calendar' => 'Il workspace disponibile non persiste un calendario aggregato completo e sicuro.',
        ];

        return [
            'schema_version' => config('dashboard_data_export.schema_version'),
            'generated_at' => now()->toIso8601String(),
            'timezone' => $window->timezone,
            'requested_interval' => $window->metadata(),
            'filters' => ['sections' => $requested],
            'application_revision' => $this->revision(),
            'requested_sections' => $requested,
            'included_sections' => $included,
            'unavailable_sections' => $unavailable,
            'denominators' => ['second_read_rate' => 'second_reads / impressions'],
            'units' => ['counts' => 'records', 'rates' => 'ratio 0..1'],
            'sources' => collect($datasets)->mapWithKeys(fn ($dataset, $id) => [$id => $dataset['id']])->all(),
            'dataset_versions' => collect($datasets)->mapWithKeys(fn ($dataset, $id) => [$id => $dataset['version']])->all(),
            'known_limitations' => collect($datasets)->pluck('limitations', 'id')->all(),
            'cwv_context' => 'not_applicable',
            'record_counts' => collect($datasets)->mapWithKeys(fn ($dataset, $id) => [$id => count($dataset['rows'])])->all(),
            'checksums_sha256' => collect($files)->map(fn (string $contents) => hash('sha256', $contents))->all(),
        ];
    }

    private function revision(): ?string
    {
        foreach ([base_path('REVISION'), base_path('DEPLOY_INFO')] as $path) {
            if (is_file($path)) {
                return trim((string) file_get_contents($path)) ?: null;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
