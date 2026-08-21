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
     * Quality Gate V2. Il motore a monte ordina e quota gia' i termini con
     * document-frequency (specifici prima dei generici) e salva nel motivo
     * solo quelli che hanno effettivamente contribuito al punteggio. Il test
     * reale sul corpus Kairus ha mostrato pero' che un termine raro non e'
     * necessariamente informativo: parole editoriali casualmente rare
     * possono ancora produrre terne da 45/100.
     *
     * Per una proposta PURAMENTE lessicale richiediamo quindi una densita'
     * minima di evidenza: tutti e tre gli slot lessicali disponibili devono
     * contenere termini informativi. Non alziamo la soglia globale e non
     * tocchiamo i segnali forti (titolo/concetto scientifico), cosi' un vero
     * collegamento cross-category con tre termini specifici continua a
     * passare mentre una categoria condivisa non puo' compensare evidenza
     * lessicale debole.
     */
    private const MIN_INFORMATIVE_LEXICAL_TERMS = 3;

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

        $informativeTerms = collect($terms)
            ->reject(fn (string $term) => in_array($term, self::LOW_INFORMATION_TERMS, true))
            ->values();

        if ($informativeTerms->count() >= self::MIN_INFORMATIVE_LEXICAL_TERMS) {
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
