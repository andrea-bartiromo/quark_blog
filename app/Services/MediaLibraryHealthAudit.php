<?php

namespace App\Services;

use App\Models\Media;

/**
 * Cantiere 26 (programma 100-cantieri Kairus). Ispezione preliminare:
 * MediaWebpAuditService (missione WebP) verifica gia' — per i soli file
 * immagine sotto public/assets/img — format_breakdown (formati),
 * missing_media_files (record Media il cui file non risulta tra quelli
 * scansionati) e i candidati a conversione WebP (un file non ottimale
 * per formato). Nessuna di queste tre cose viene duplicata qui: questo
 * audit riusa MediaWebpAuditService per "formato non ottimale" e per
 * "file mancante", e aggiunge le due dimensioni editoriali che
 * NESSUN audit esistente copre — testo alternativo e credito/fonte —
 * piu' un controllo di peso assente ovunque nel progetto (verificato:
 * nessun servizio segnala un file "troppo pesante").
 *
 * Sola lettura: nessuna scrittura, nessuna modifica ai record Media o
 * ai file su disco.
 */
class MediaLibraryHealthAudit
{
    private const DEFAULT_MAX_RECOMMENDED_SIZE_BYTES = 1_000_000;

    public function __construct(private readonly MediaWebpAuditService $webpAudit) {}

    /**
     * @return array{analyzed: int, rows: list<array{id: int, filename: string, disk_name: string, findings: list<string>}>, missing_alt: int, missing_credit: int, missing_file: int, oversized: int, non_optimal_format: int}
     */
    public function audit(?int $maxRecommendedSizeBytes = null): array
    {
        $maxSize = $maxRecommendedSizeBytes ?? (int) config('media.audit_max_size_bytes', self::DEFAULT_MAX_RECOMMENDED_SIZE_BYTES);

        // measureActual: false — questo audit usa solo relative_path dai
        // candidati e da missing_media_files, mai le stime di dimensione
        // WebP: lasciarlo al default (true) convertirebbe realmente ogni
        // candidato JPEG/PNG in un WebP temporaneo solo per scartarne il
        // risultato, con un costo che cresce linearmente con ogni file
        // non ancora ottimizzato della libreria (Codex, PR #574).
        $webpReport = $this->webpAudit->audit(['measureActual' => false]);
        $nonOptimalDiskNames = array_column($webpReport['candidates']['files'], 'relative_path');
        $missingDiskNames = $webpReport['missing_media_files'];

        $rows = [];
        $counters = ['missing_alt' => 0, 'missing_credit' => 0, 'missing_file' => 0, 'oversized' => 0, 'non_optimal_format' => 0];

        foreach (Media::query()->images()->get() as $media) {
            $findings = [];

            if (blank($media->alt_text)) {
                $findings[] = 'Testo alternativo mancante.';
                $counters['missing_alt']++;
            }

            if (! $this->hasCompleteAttribution($media)) {
                $findings[] = 'Credito e fonte non completi (serve il credito e almeno una tra fonte/URL fonte).';
                $counters['missing_credit']++;
            }

            $isMissingFile = in_array($media->disk_name, $missingDiskNames, true);
            if ($isMissingFile) {
                $findings[] = 'File assente su disco (registrato in Libreria media, ma non trovato in public/assets/img).';
                $counters['missing_file']++;
            }

            if (! $isMissingFile && $media->size > $maxSize) {
                $findings[] = sprintf('Peso elevato (%s, oltre %s).', $media->human_size, Media::humanFileSize($maxSize));
                $counters['oversized']++;
            }

            if (in_array($media->disk_name, $nonOptimalDiskNames, true)) {
                $findings[] = 'Formato non ottimale: candidato a conversione WebP (vedi php artisan media:webp-audit).';
                $counters['non_optimal_format']++;
            }

            $rows[] = [
                'id' => $media->id,
                'filename' => $media->filename,
                'disk_name' => $media->disk_name,
                'findings' => $findings,
            ];
        }

        return [
            'analyzed' => count($rows),
            'rows' => $rows,
            ...$counters,
        ];
    }

    /**
     * Stesso criterio di ArticleContentHealthService::coverAttribution():
     * un credito da solo non basta senza una fonte verificabile
     * (attribuzione, non solo un nome).
     */
    private function hasCompleteAttribution(Media $media): bool
    {
        return filled($media->credit) && (filled($media->source) || filled($media->source_url));
    }
}
