<?php

namespace App\Models;

use App\Services\ProjectTaskSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    use HasFactory;

    /**
     * Copre il caso non coperto da Article::saved: creare (o modificare)
     * un task di Pubblicazione collegato a un articolo GIA' esistente e
     * invariato non fa scattare alcun evento su Article, quindi il task
     * deve sincronizzarsi anche da questo lato.
     */
    protected static function booted(): void
    {
        static::saved(function (ProjectTask $task) {
            if ($task->wasRecentlyCreated || $task->wasChanged(['type', 'article_id', 'manual_override'])) {
                app(ProjectTaskSyncService::class)->syncTask($task);
            }
        });
    }

    public const TYPE_TASK = 'task';

    public const TYPE_REVIEW = 'review';

    public const TYPE_TECHNICAL_CHECK = 'technical_check';

    public const TYPE_PUBLICATION = 'publication';

    public const STATUS_TODO = 'todo';

    public const STATUS_TAKEN = 'taken';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    public const DERIVED_DRAFT = 'draft';

    public const DERIVED_IN_REVIEW = 'in_review';

    public const DERIVED_SCHEDULED = 'scheduled';

    public const DERIVED_PUBLISHED = 'published';

    public const DERIVED_INVALID_LINK = 'invalid_link';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_DERIVED = 'derived';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'project_id', 'title', 'description', 'type',
        'manual_status', 'derived_status', 'status_source', 'manual_override',
        'priority', 'responsible_id', 'due_date', 'due_time', 'completed_at',
        'article_id', 'depends_on_task_id', 'duplicated_from_id', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'due_time' => 'datetime:H:i',
        'completed_at' => 'datetime',
        'manual_override' => 'boolean',
    ];

    // ── Relazioni ─────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'depends_on_task_id');
    }

    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'duplicated_from_id');
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopePublicationType(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_PUBLICATION);
    }

    public function scopeDueSoon(Builder $q, int $days = 7): Builder
    {
        return $q->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->whereNotIn('manual_status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('manual_status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    // ── Stato effettivo ───────────────────────────────────────

    /**
     * Lo stato realmente mostrato in UI: quello derivato quando la sync è
     * attiva e non sovrascritta manualmente, altrimenti quello manuale.
     */
    public function effectiveStatus(): string
    {
        if ($this->status_source === self::SOURCE_DERIVED && ! $this->manual_override && $this->derived_status) {
            return $this->derived_status;
        }

        return $this->manual_status;
    }

    // ── Etichette ─────────────────────────────────────────────

    public static function typeOptions(): array
    {
        return [
            self::TYPE_TASK => 'Task',
            self::TYPE_REVIEW => 'Revisione',
            self::TYPE_TECHNICAL_CHECK => 'Verifica tecnica',
            self::TYPE_PUBLICATION => 'Pubblicazione',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_TODO => 'Da fare',
            self::STATUS_TAKEN => 'Presa in carico',
            self::STATUS_IN_PROGRESS => 'In lavorazione',
            self::STATUS_IN_REVIEW => 'In revisione',
            self::STATUS_COMPLETED => 'Completata',
            self::STATUS_BLOCKED => 'Bloccata',
            self::STATUS_SUSPENDED => 'Sospesa',
            self::STATUS_CANCELLED => 'Annullata',
        ];
    }

    public static function derivedStatusOptions(): array
    {
        return [
            self::DERIVED_DRAFT => 'Bozza',
            self::DERIVED_IN_REVIEW => 'In revisione',
            self::DERIVED_SCHEDULED => 'Programmato',
            self::DERIVED_PUBLISHED => 'Pubblicato',
            self::DERIVED_INVALID_LINK => 'Collegamento non valido',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Bassa',
            self::PRIORITY_MEDIUM => 'Media',
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_CRITICAL => 'Critica',
        ];
    }
}
