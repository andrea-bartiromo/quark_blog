<?php

namespace App\Observers;

use App\Models\ArticleLinkSuggestion;

class ArticleLinkSuggestionQualityObserver
{
    /**
     * Termini editoriali molto generici osservati in produzione mentre
     * contribuivano da soli a suggerimenti da 45/100. Sono volutamente
     * separati dalle stopword linguistiche del tokenizer: qui valutiamo la
     * QUALITA' della proposta gia' calcolata, non modifichiamo il vocabolario
     * globale del motore.
     */
    private const LOW_INFORMATION_TERMS = [
        'aggiornata',
        'aggiornato',
        'attraversare',
        'considerate',
        'considerati',
        'fondamentale',
        'fondamentali',
        'immaginiamo',
        'profonde',
        'profondi',
        'riferimento',
        'riferimenti',
        'risolvere',
        'sembrare',
        'sembravano',
        'sorprendente',
        'sorprendenti',
        'straordinaria',
        'straordinarie',
        'straordinario',
        'straordinari',
        'trasformarsi',
        'utilizziamo',
    ];

    /**
     * Una proposta non viene mai scartata se dispone di un segnale forte
     * (titolo completo o concetto scientifico riconosciuto). Nel caso
     * puramente lessicale, invece, almeno uno dei termini che hanno
     * contribuito allo score deve contenere informazione tematica reale.
     *
     * L'observer gira dopo quello di affinità categoria: il bonus categoria
     * non salva un match lessicale composto esclusivamente da parole
     * generiche. La categoria resta un bonus, mai una prova di pertinenza.
     */
    public function saved(ArticleLinkSuggestion $suggestion): void
    {
        if ($suggestion->status !== ArticleLinkSuggestion::STATUS_PROPOSED) {
            return;
        }

        $reason = mb_strtolower((string) $suggestion->reason, 'UTF-8');

        if ($reason === ''
            || str_contains($reason, 'titolo dell\'articolo collegato compare nel testo')
            || str_contains($reason, 'concetto scientifico riconosciuto:')) {
            return;
        }

        $terms = $this->matchedTerms($reason);

        if ($terms === []) {
            return;
        }

        $hasInformativeTerm = collect($terms)
            ->contains(fn (string $term) => ! in_array($term, self::LOW_INFORMATION_TERMS, true));

        if ($hasInformativeTerm) {
            return;
        }

        $suggestion->updateQuietly([
            'status' => ArticleLinkSuggestion::STATUS_SUPERSEDED,
        ]);
    }

    /** @return array<int, string> */
    private function matchedTerms(string $reason): array
    {
        if (preg_match('/termini in comune:\s*([^;]+)/u', $reason, $matches) !== 1) {
            return [];
        }

        return collect(explode(',', $matches[1]))
            ->map(fn (string $term) => trim($term))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
