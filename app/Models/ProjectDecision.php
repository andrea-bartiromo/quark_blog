<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDecision extends Model
{
    use HasFactory;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'project_id', 'title', 'context', 'decision', 'rationale',
        'status', 'decided_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PROPOSED => 'Proposta',
            self::STATUS_APPROVED => 'Approvata',
            self::STATUS_REJECTED => 'Respinta',
            self::STATUS_SUPERSEDED => 'Superata',
        ];
    }
}
