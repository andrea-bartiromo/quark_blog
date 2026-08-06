<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActivityLog extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'subject_type', 'subject_id', 'subject_title',
        'action', 'old_value', 'new_value', 'reason', 'source',
        'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * $userId è sempre esplicito, senza default "furbo": un default che
     * provasse a indovinare tra origine manuale e automatica nasconderebbe
     * proprio la distinzione che questa colonna esiste per registrare.
     */
    public static function record(
        Project $project,
        string $subjectType,
        int $subjectId,
        ?string $subjectTitle,
        string $action,
        ?int $userId,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $reason = null,
        string $source = self::SOURCE_MANUAL,
    ): self {
        return static::create([
            'project_id' => $project->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_title' => $subjectTitle,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'source' => $source,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
