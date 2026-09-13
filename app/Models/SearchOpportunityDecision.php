<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decisione editoriale tracciabile per un'opportunità di ricerca
 * (Cantiere 4, "Kairus Organic Discovery") — mai un'azione automatica:
 * questa riga registra SOLO la decisione umana (aggiorna un articolo
 * esistente, crea un brief per un nuovo articolo, valuta una
 * sovrapposizione/fusione con un articolo esistente, ignora con
 * motivazione), mai una modifica di contenuto/pubblicazione.
 *
 * Una riga per opportunity_key (stessa identità stabile
 * type|query|page_url di SearchOpportunity::$key), aggiornata nel tempo;
 * ogni cambiamento produce anche una riga in
 * SearchOpportunityDecisionHistory (mai qui: questo modello riflette solo
 * lo stato CORRENTE).
 */
class SearchOpportunityDecision extends Model
{
    public const DECISION_UPDATE_ARTICLE = 'update_article';

    public const DECISION_CREATE_BRIEF = 'create_brief';

    public const DECISION_MERGE = 'merge';

    public const DECISION_IGNORE = 'ignore';

    protected $fillable = [
        'opportunity_key',
        'opportunity_type',
        'opportunity_query',
        'decision_type',
        'rationale',
        'article_id',
        'project_task_id',
        'created_by',
        'updated_by',
        'baseline_clicks',
        'baseline_impressions',
        'baseline_ctr',
        'baseline_position',
        'baseline_captured_at',
        'measured_28d_clicks',
        'measured_28d_impressions',
        'measured_28d_ctr',
        'measured_28d_position',
        'measured_28d_at',
        'measured_90d_clicks',
        'measured_90d_impressions',
        'measured_90d_ctr',
        'measured_90d_position',
        'measured_90d_at',
    ];

    protected $casts = [
        'baseline_ctr' => 'float',
        'baseline_position' => 'float',
        'baseline_captured_at' => 'datetime',
        'measured_28d_ctr' => 'float',
        'measured_28d_position' => 'float',
        'measured_28d_at' => 'datetime',
        'measured_90d_ctr' => 'float',
        'measured_90d_position' => 'float',
        'measured_90d_at' => 'datetime',
    ];

    public static function decisionTypeOptions(): array
    {
        return [
            self::DECISION_UPDATE_ARTICLE => 'Aggiorna articolo esistente',
            self::DECISION_CREATE_BRIEF => 'Crea brief per nuovo articolo',
            self::DECISION_MERGE => 'Sovrapposizione con articolo esistente',
            self::DECISION_IGNORE => 'Ignora',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function projectTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
