<?php

namespace App\Services\SearchConsole;

use App\Models\SearchConsoleCoverageImport;
use App\Models\SearchConsoleCoverageIssue;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa l'export Coverage in CSV. Non contatta Google e non modifica
 * superfici pubbliche: il file è una fotografia dichiarata dalla redazione.
 */
class SearchConsoleCoverageCsvImporter
{
    private const MAX_ROWS = 10000;

    public function __construct(private readonly SearchConsoleCoverageUrlEligibility $eligibility) {}

    /** @return array{import: SearchConsoleCoverageImport|null, imported: int, errors: list<string>} */
    public function import(string $filePath, string $property, CarbonInterface $observedAt, ?string $filename = null): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ['import' => null, 'imported' => 0, 'errors' => ['Impossibile aprire il file.']];
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if ($header === false) {
                return ['import' => null, 'imported' => 0, 'errors' => ['File vuoto.']];
            }

            $columns = $this->columns($header);
            if (! isset($columns['reason'], $columns['pages'])) {
                return ['import' => null, 'imported' => 0, 'errors' => ['Intestazioni non riconosciute: servono almeno Ragione e Pagine.']];
            }

            $rows = [];
            $errors = [];
            $line = 1;
            while (($record = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if (count($rows) >= self::MAX_ROWS) {
                    $errors[] = "Limite di ".self::MAX_ROWS.' righe raggiunto.';
                    break;
                }
                $parsed = $this->parse($record, $columns);
                if ($parsed === null) {
                    $errors[] = "Riga {$line} non valida, scartata.";
                    continue;
                }
                $rows[] = $parsed;
            }

            if ($rows === []) {
                return ['import' => null, 'imported' => 0, 'errors' => [...$errors, 'Nessuna riga valida trovata.']];
            }

            $import = DB::transaction(function () use ($property, $observedAt, $filename, $rows) {
                $import = SearchConsoleCoverageImport::query()
                    ->where('property', $property)
                    ->whereDate('observed_at', $observedAt->toDateString())
                    ->first();

                if ($import === null) {
                    $import = new SearchConsoleCoverageImport([
                        'property' => $property,
                        'observed_at' => $observedAt->toDateString(),
                    ]);
                }

                $import->fill([
                    'source' => 'manual_csv',
                    'source_filename' => $filename,
                    'import_batch' => (string) Str::uuid(),
                    'imported_at' => now(),
                ])->save();
                $import->issues()->delete();
                foreach ($rows as $row) {
                    $audit = $this->eligibility->audit($row['page_url']);
                    $import->issues()->create([...$row, 'audit' => $audit, ...$this->classify($row, $audit)]);
                }
                return $import;
            });

            return ['import' => $import, 'imported' => count($rows), 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }

    /** @return array<string, int> */
    private function columns(array $header): array
    {
        $aliases = [
            'reason' => ['ragione', 'reason'], 'source' => ['sorgente', 'source'],
            'validation' => ['convalida', 'validation'], 'pages' => ['pagine', 'pages'],
            'url' => ['url', 'pagina', 'page'],
        ];
        $found = [];
        foreach ($header as $index => $name) {
            $name = mb_strtolower(trim(ltrim((string) $name, "\xEF\xBB\xBF")));
            foreach ($aliases as $key => $values) {
                if (in_array($name, $values, true)) $found[$key] = $index;
            }
        }
        return $found;
    }

    /** @return array{reason:string,source:?string,validation:?string,page_count:int,page_url:?string}|null */
    private function parse(array $record, array $columns): ?array
    {
        $reason = trim((string) ($record[$columns['reason']] ?? ''));
        $pages = filter_var($record[$columns['pages']] ?? null, FILTER_VALIDATE_INT);
        if ($reason === '' || $pages === false || $pages < 0) return null;
        $value = fn (string $key) => isset($columns[$key]) && trim((string) ($record[$columns[$key]] ?? '')) !== '' ? mb_substr(trim((string) $record[$columns[$key]]), 0, $key === 'url' ? 500 : 120) : null;
        return ['reason' => mb_substr($reason, 0, 500), 'source' => $value('source'), 'validation' => $value('validation'), 'page_count' => $pages, 'page_url' => $value('url')];
    }

    /** @return array{classification:string,recommendation:string} */
    private function classify(array $row, array $audit): array
    {
        $reason = mb_strtolower($row['reason']);
        $urlStatus = $audit['visibility'];
        $priorityReason = str_contains($reason, 'rilevata, ma attualmente non indicizzata')
            || str_contains($reason, 'scansionata, ma attualmente non indicizzata')
            || str_contains($reason, 'discovered - currently not indexed')
            || str_contains($reason, 'crawled - currently not indexed');
        if ($priorityReason) {
            if ($row['page_count'] !== 1) {
                return ['classification' => SearchConsoleCoverageIssue::CLASS_REVIEW, 'recommendation' => 'Riga aggregata: un singolo URL facoltativo non rappresenta tutte le pagine. Importare il dettaglio URL o verificare manualmente prima di assegnare una priorità.'];
            }
            if ($urlStatus === 'not_public') {
                return ['classification' => SearchConsoleCoverageIssue::CLASS_REVIEW, 'recommendation' => 'L’URL non è pubblicamente raggiungibile nello stato corrente: verificare prima la visibilità; non richiedere indicizzazione.'];
            }
            if ($urlStatus === 'unknown') {
                return ['classification' => SearchConsoleCoverageIssue::CLASS_REVIEW, 'recommendation' => 'Manca un URL pubblico confrontabile: verificare manualmente prima di valutare una revisione editoriale.'];
            }
            if ($audit['http_status'] !== 200 || rtrim((string) $audit['canonical'], '/') !== rtrim((string) $row['page_url'], '/') || str_contains((string) $audit['robots'], 'noindex')) {
                return ['classification' => SearchConsoleCoverageIssue::CLASS_REVIEW, 'recommendation' => 'L’URL non supera il confronto tecnico (HTTP, canonical o robots): verificare manualmente; nessuna correzione automatica.'];
            }
            return ['classification' => SearchConsoleCoverageIssue::CLASS_EDITORIAL, 'recommendation' => 'Verificare che ogni URL di dettaglio sia pubblico e canonico; poi valutare qualità, collegamenti interni e valore editoriale.'];
        }
        if (str_contains($reason, 'reindirizzamento') || str_contains($reason, 'page with redirect') || str_contains($reason, 'canonical appropriato') || str_contains($reason, 'alternate page with proper canonical tag')) {
            return ['classification' => SearchConsoleCoverageIssue::CLASS_EXPECTED, 'recommendation' => 'Esclusione potenzialmente attesa: verificare solo se l’URL dovrebbe restare canonico e pubblico.'];
        }
        if (str_contains($reason, 'noindex')) {
            return ['classification' => SearchConsoleCoverageIssue::CLASS_INTENTIONAL, 'recommendation' => 'Verificare che il noindex sia una scelta editoriale intenzionale.'];
        }
        if (str_contains($reason, '404') || str_contains($reason, 'non trovata')) {
            return ['classification' => SearchConsoleCoverageIssue::CLASS_REVIEW, 'recommendation' => 'Verificare l’URL: correggere il collegamento o valutare un redirect manuale se la pagina pubblica ha un successore.'];
        }
        if (str_contains($reason, 'canonica diversa') || str_contains($reason, 'duplicata') || str_contains($reason, 'google chose different canonical') || str_contains($reason, 'duplicate')) {
            return ['classification' => SearchConsoleCoverageIssue::CLASS_REVIEW, 'recommendation' => 'Confrontare URL, canonical e sitemap: non applicare correzioni automatiche.'];
        }
        return ['classification' => SearchConsoleCoverageIssue::CLASS_REVIEW, 'recommendation' => 'Dati insufficienti per una correzione automatica: classificare manualmente dopo la verifica.'];
    }
}
