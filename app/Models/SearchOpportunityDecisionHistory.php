<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Storico append-only (Cantiere 4, "Kairus Organic Discovery") di ogni
 * cambiamento di decisione editoriale su un'opportunità di ricerca —
 * stesso schema/idioma di ProjectActivityLog::record(): mai un
 * update/upsert su questo modello, solo create().
 */
class SearchOpportunityDecisionHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'opportunity_key', 'action', 'old_value', 'new_value', 'reason', 'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $opportunityKey,
        string $action,
        ?int $userId,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $reason = null,
    ): self {
        return static::create([
            'opportunity_key' => $opportunityKey,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
