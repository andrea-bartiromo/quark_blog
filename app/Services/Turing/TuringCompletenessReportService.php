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
 * non fatte qui), ma aggrega in un unico punto, SEMPRE dal vero stato
 * attuale del codice/database (mai un'istantanea statica che può
 * invecchiare in silenzio), le dimensioni di completezza editoriale già
 * costruite dai cantieri precedenti: fonti registrate per capitolo
 * (Cantiere 61), copertura della mappa concettuale (Cantiere 59),
 * metriche di navigazione reali (Cantiere 68) e stato di pubblicazione
 * (Cantiere 57/63). Nessun punteggio o giudizio sintetico viene
 * calcolato qui: il livello di approfondimento di ciascun concetto resta
 * il testo letterale già redatto a mano nella mappa concettuale, non una
 * categoria o un punteggio dedotto da questo codice.
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
