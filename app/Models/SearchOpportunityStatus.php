<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchOpportunityStatus extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'opportunity_key',
        'status',
        'updated_by',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Nuova',
            self::STATUS_REVIEWED => 'Vista',
            self::STATUS_ACTIONED => 'Gestita',
            self::STATUS_DISMISSED => 'Ignorata',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
