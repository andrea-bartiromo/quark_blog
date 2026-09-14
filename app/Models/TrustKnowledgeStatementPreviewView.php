<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cantiere 43 (programma "100 cantieri Kairus", dipende dal Cantiere 40).
 *
 * Evento append-only: un'apertura della preview admin-only di una
 * TrustKnowledgeStatement (TrustKnowledgeStatementController::preview(),
 * Cantiere 40) — mai modificato dopo la creazione, mai un identificativo
 * di visitatore/sessione/utente/IP persistito qui. Rehearsal privacy-first
 * per la metrica "Visualizzazioni aggregate" del contratto di misurazione
 * B-44 (docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md), applicata qui
 * all'uso interno redazionale invece che a un pilot pubblico reale (che
 * non esiste ancora, gate B-45 in vigore) — stesso principio già in uso
 * per ArticleContinuationEvent.
 */
class TrustKnowledgeStatementPreviewView extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'trust_knowledge_preview_views';

    protected $fillable = [
        'trust_knowledge_statement_id',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TrustKnowledgeStatement::class, 'trust_knowledge_statement_id');
    }
}
