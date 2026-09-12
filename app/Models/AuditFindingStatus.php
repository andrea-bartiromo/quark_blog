<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cantiere 31 (programma 100-cantieri Kairus). Workflow editoriale
 * leggero per i finding dei sei audit di PublicHealthDashboardService
 * (Cantiere 30) — stesso principio già stabilito da SearchOpportunityStatus
 * (Mission 6): solo uno stato assegnato a mano, mai un punteggio o una
 * correzione automatica. "Ignorato" esclude il finding dal conteggio
 * "aperti" della dashboard (rischio accettato/falso positivo/fuori
 * perimetro) — a differenza di "in_carico", che resta comunque un
 * finding aperto (qualcuno se ne sta occupando, non ancora risolto).
 */
class AuditFindingStatus extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_IN_CARICO = 'in_carico';

    public const STATUS_DISMISSED = 'ignorato';

    protected $fillable = [
        'finding_key',
        'domain',
        'status',
        'updated_by',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Nuovo',
            self::STATUS_IN_CARICO => 'Preso in carico',
            self::STATUS_DISMISSED => 'Ignorato',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
