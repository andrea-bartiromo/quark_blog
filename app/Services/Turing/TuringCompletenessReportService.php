<?php

namespace App\Services\Turing;

use App\Models\TuringChapterSource;

/**
 * Cantiere 67 (programma "100 cantieri Kairus").
 *
 * Ispezione diretta prima di questo cantiere: i soli documenti di
 * "completezza" per lo Speciale Turing esistenti in repository erano
 * audit statici scritti a mano in `docs/02_Turing_Audit/` e
 * `docs/06_Turing_Release/Checklist_Release_Candidate_v1.0.md`, datati
 * 29 luglio 2026 — e già dimostrabilmente non aggiornati: il bug dei tre
 * link teaser errati documentato in `Audit_Legacy_v1.0.md` risulta oggi
 * già corretto nel codice (verificato leggendo `legacy.blade.php`), ma
 * il documento continua a segnalarlo come "Aperto". Questo servizio non
 * sostituisce quegli audit (accessibilità/performance restano fuori
 * scope: richiederebbero ri-misurazioni reali con axe-core/Lighthouse,
 * non fatte qui), ma aggrega in un unico punto fonti registrate per
 * capitolo (Cantiere 61), copertura della mappa concettuale (Cantiere
 * 59), metriche di navigazione reali (Cantiere 68) e stato di
 * pubblicazione (Cantiere 57/63).
 *
 * "Sempre dal vero stato attuale" vale per fonti/metriche/pubblicazione
 * (query dirette, mai una copia), ma NON per il livello di
 * approfondimento di ciascun concetto (Codex, PR #632, P2): quel campo è
 * la stessa fotografia editoriale statica del 29 luglio 2026 già
 * documentata nel docblock di TuringConceptMapService::concepts() e già
 * segnalata come tale dalla vista `admin.turing-concept-map` — questo
 * servizio la riusa letteralmente, senza ricalcolarla dal testo attuale
 * dei capitoli, e la vista che la mostra deve ripetere lo stesso avviso
 * (mai presentarla come dato corrente). Nessun punteggio o giudizio
 * sintetico viene comunque calcolato qui: il testo resta letterale, non
 * una categoria o un punteggio dedotto da questo codice.
 */
class TuringCompletenessReportService
{
    public function __construct(
        private readonly TuringNavigationMetricsService $navigationMetrics,
    ) {}

    /**
     * @return array{
     *     chapters_public: bool,
     *     hub_navigation: array{state: string, count: int, days_collected: int}|null,
     *     chapters: array<string, array{
     *         sources_count: int,
     *         concepts_as_main_chapter: list<array{argomento: string, livello_approfondimento: string}>,
     *         navigation: array{state: string, count: int, days_collected: int}|null,
     *     }>,
     * }
     */
    public function build(): array
    {
        $realChapters = $this->realChapters();

        $conceptsByChapter = TuringConceptMapService::conceptsByChapter();

        $sourceCounts = TuringChapterSource::query()
            ->selectRaw('chapter, count(*) as aggregate')
            ->groupBy('chapter')
            ->pluck('aggregate', 'chapter');

        $navigationViews = $this->navigationMetrics->aggregateViews();

        $chapters = collect($realChapters)
            ->mapWithKeys(fn (string $chapter) => [$chapter => [
                'sources_count' => (int) ($sourceCounts[$chapter] ?? 0),
                'concepts_as_main_chapter' => array_map(
                    fn (array $concept) => [
                        'argomento' => $concept['argomento'],
                        'livello_approfondimento' => $concept['livello_approfondimento'],
                    ],
                    $conceptsByChapter[$chapter] ?? []
                ),
                'navigation' => $navigationViews[$chapter] ?? null,
            ]])
            ->all();

        return [
            'chapters_public' => (bool) config('turing.chapters_public'),
            'hub_navigation' => $navigationViews['hub'] ?? null,
            'chapters' => $chapters,
        ];
    }

    /**
     * Stessa fonte di verità già usata da Admin\TuringController e
     * Admin\TuringChapterSourceController (mai una seconda lista
     * duplicata dei 5 capitoli reali).
     *
     * @return list<string>
     */
    private function realChapters(): array
    {
        return array_values(array_filter(
            TuringNavigationMetricsService::CHAPTERS,
            fn (string $chapter) => $chapter !== 'hub'
        ));
    }
}
