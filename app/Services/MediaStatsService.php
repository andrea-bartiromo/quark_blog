<?php

namespace App\Services;

use App\Models\Media;

class MediaStatsService
{
    /**
     * Statistiche aggregate sull'intera libreria media (non filtrate per
     * cartella o ricerca corrente). Riusa gli scope images()/documents() del
     * modello Media per restare coerente con la categorizzazione usata dal
     * filtro "Tipo": tre query di sola aggregazione (COUNT/SUM), nessuna
     * riga caricata in memoria.
     *
     * @return array{total_files: int, total_size: int, total_size_human: string, image_count: int, document_count: int}
     */
    public function global(): array
    {
        $totals = Media::query()->selectRaw('COUNT(*) as total_files, COALESCE(SUM(size), 0) as total_size')->first();
        $totalFiles = (int) $totals->total_files;
        $totalSize = (int) $totals->total_size;

        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_human' => Media::humanFileSize($totalSize),
            'image_count' => (int) Media::images()->count(),
            'document_count' => (int) Media::documents()->count(),
        ];
    }
}
