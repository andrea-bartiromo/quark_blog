<?php

namespace App\Services\Turing;

use App\Models\TuringChapterView;
use Illuminate\Support\Carbon;

/**
 * Cantiere 68 (programma "100 cantieri Kairus").
 *
 * Rehearsal privacy-first della "navigazione aggregata" per lo Speciale
 * Turing, stesso principio già usato per il pilot Trust
 * (TrustPilotPreviewMetricsService, Cantiere 43): registra e legge SOLO
 * conteggi aggregati per capitolo, nessun identificativo di
 * visitatore/sessione/utente mai esposto o persistito qui (si legga il
 * docblock del modello TuringChapterView).
 *
 * Oggi lo Speciale è dietro il gate `config('turing.chapters_public')`
 * (Cantieri 57/63) — finché resta false, `recordView()` non viene mai
 * chiamato (vedi TuringPublicController/TuringPageController) e questo
 * servizio legge correttamente zero eventi: "lo zero del campione resta
 * zero" (stesso principio di docs/DASHBOARD_DATA_EXPORT_V1.md), non un
 * errore da nascondere.
 */
class TuringNavigationMetricsService
{
    public const STATE_AVAILABLE = 'available';

    public const STATE_INSUFFICIENT_DATA = 'insufficient_data';

    public const CHAPTERS = ['hub', 'enigma', 'ai', 'legacy', 'computation', 'intelligence'];

    private const MIN_DAYS_COLLECTED = 7;

    /**
     * Data di attivazione di questa strumentazione: nessun evento può
     * fisicamente esistere prima di questa data — ancora per "giorni di
     * dati raccolti", stesso principio (e stessa correzione Codex, PR
     * #602) già applicato in TrustPilotPreviewMetricsService.
     */
    private const TRACKING_STARTED_AT = '2026-09-17 00:00:00';

    public function recordView(string $chapter): void
    {
        TuringChapterView::create(['chapter' => $chapter]);
    }

    /**
     * Una sola query per tutti i capitoli — mai una per riga, stesso
     * principio già in uso in TrustPilotPreviewMetricsService::aggregateViewsForMany().
     *
     * @return array<string, array{state: string, count: int, days_collected: int}> capitolo => metrica
     */
    public function aggregateViews(): array
    {
        $countsByChapter = TuringChapterView::query()
            ->selectRaw('chapter, count(*) as aggregate')
            ->groupBy('chapter')
            ->pluck('aggregate', 'chapter');

        $daysCollected = $this->daysCollected();
        $state = $daysCollected < self::MIN_DAYS_COLLECTED ? self::STATE_INSUFFICIENT_DATA : self::STATE_AVAILABLE;

        return collect(self::CHAPTERS)
            ->mapWithKeys(fn (string $chapter) => [$chapter => [
                'state' => $state,
                'count' => (int) ($countsByChapter[$chapter] ?? 0),
                'days_collected' => $daysCollected,
            ]])
            ->all();
    }

    /**
     * Giorni realmente trascorsi dall'attivazione della strumentazione:
     * mai negativo (es. orologio di sistema anomalo).
     */
    private function daysCollected(): int
    {
        $trackingStartedAt = Carbon::parse(self::TRACKING_STARTED_AT);
        $now = Carbon::now();

        return $now->greaterThan($trackingStartedAt) ? $trackingStartedAt->diffInDays($now) : 0;
    }
}
