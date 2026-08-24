<?php

namespace App\Services\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ConceptQuestion;

/**
 * Mission 21 — Question Status Workflow V2: audit read-only per singola
 * ConceptQuestion, itemizzando ESATTAMENTE le stesse condizioni già
 * applicate da ContentGraphService::answerableQuestionsForConcept() —
 * mai una seconda versione della regola, solo una spiegazione di quale
 * condizione manca. Stesso principio "mai bloccare il campo status
 * dell'editor" già stabilito da ConceptQuestionController (un editor può
 * salvare "approved" incompleto senza errore) e stesso pattern
 * "read-only, itemized findings" di PercorsoPublicationReadinessService.
 *
 * Questo servizio non sostituisce la colonna binaria "Raggiungibilità
 * pubblica" già esistente (continua a derivare da
 * answerableQuestionsForConcept()): la estende con IL PERCHÉ.
 */
class ConceptQuestionReadinessService
{
    public const ANSWER_MISSING = 'ANSWER_MISSING';

    public const TARGET_MISSING = 'TARGET_MISSING';

    public const TARGET_NOT_PUBLISHED = 'TARGET_NOT_PUBLISHED';

    public const CONCEPT_NOT_ACTIVE = 'CONCEPT_NOT_ACTIVE';

    public const STATUS_NOT_APPROVED = 'STATUS_NOT_APPROVED';

    /**
     * @return array{answerable: bool, findings: list<array{code: string, message: string}>}
     */
    public function evaluate(ConceptQuestion $question): array
    {
        $question->loadMissing(['concept']);

        $findings = [];

        if ($question->status !== ConceptQuestion::STATUS_APPROVED) {
            $findings[] = $this->finding(self::STATUS_NOT_APPROVED, 'Lo stato non è "Approvata".');
        }

        if ($question->concept === null || $question->concept->status !== Concept::STATUS_ACTIVE) {
            $findings[] = $this->finding(self::CONCEPT_NOT_ACTIVE, 'Il concetto non è attivo.');
        }

        if (trim((string) $question->answer_summary) === '') {
            $findings[] = $this->finding(self::ANSWER_MISSING, 'Manca una risposta (sintesi).');
        }

        if ($question->target_article_id === null) {
            $findings[] = $this->finding(self::TARGET_MISSING, 'Manca un articolo target.');
        } elseif (! Article::query()->published()->whereKey($question->target_article_id)->exists()) {
            // Stesso gate canonico di answerableQuestionsForConcept()
            // (whereHas('targetArticle', fn ($q) => $q->published())) — mai
            // Article::isPublished(), che verifica solo lo status e non
            // published_at <= now().
            $findings[] = $this->finding(self::TARGET_NOT_PUBLISHED, 'L\'articolo target non è pubblicato.');
        }

        return [
            'answerable' => $findings === [],
            'findings' => $findings,
        ];
    }

    private function finding(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
