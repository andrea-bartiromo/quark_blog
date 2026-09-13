<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cantiere 1 (programma "Kairus Organic Discovery"). Una riga per
 * property/periodo/tipo di report, sempre la copertura *effettiva*
 * corrente (upsert da SearchConsoleImportCoverageService), mai uno storico
 * di ogni singolo import — vedi la migrazione per il vincolo di unicità.
 */
class SearchConsoleImportCoverage extends Model
{
    protected $table = 'search_console_import_coverage';

    public const REPORT_TYPE_QUERY_ONLY = 'query_only';

    public const REPORT_TYPE_QUERY_PAGE = 'query_page';

    public const ORIGIN_MANUAL_CSV = 'manual_csv';

    protected $fillable = [
        'property',
        'period_start',
        'period_end',
        'report_type',
        'row_count',
        'matched_count',
        'unmatched_count',
        'pages_observed_count',
        'origin',
        'import_batch',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'row_count' => 'integer',
            'matched_count' => 'integer',
            'unmatched_count' => 'integer',
            'pages_observed_count' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
