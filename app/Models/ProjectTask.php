<?php

namespace App\Models;

use App\Exceptions\InvalidTaskDependencyException;
use App\Services\ProjectProgressService;
use App\Services\ProjectTaskGithubSyncService;
use App\Services\ProjectTaskSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        static::saving(function (ProjectTask $task) {
            if ($task->depends_on_task_id !== null && $task->isDirty('depends_on_task_id')) {
                static::guardAgainstInvalidDependency($task);
            }
        });

        static::saved(function (ProjectTask $task) {
            if ($task->wasRecentlyCreated || $task->wasChanged(['type', 'article_id', 'manual_override'])) {
                app(ProjectTaskSyncService::class)->syncTask($task);
            }

            // Stesso principio, per il collegamento a GitHub (Blocco B):
            // impostare o cambiare il branch avvia subito un tentativo di
            // sync, senza attendere il prossimo giro dello scheduler.
            if ($task->wasRecentlyCreated || $task->wasChanged(['type', 'github_branch', 'manual_override'])) {
                app(ProjectTaskGithubSyncService::class)->syncTask($task);
            }

            // L'avanzamento del progetto (Blocco D) dipende dal conteggio
            // delle task, quindi si ricalcola a ogni salvataggio — anche
            // quelli innescati ricorsivamente dai sync qui sopra, che non
            // cambiano il totale/completate ma il ricalcolo resta idempotente.
            app(ProjectProgressService::class)->recalculate($task->project);
        });

        static::deleted(function (ProjectTask $task) {
            app(ProjectProgressService::class)->recalculate($task->project);
        });
    }

    public const TYPE_TASK = 'task';

    public const TYPE_REVIEW = 'review';

    public const TYPE_TECHNICAL_CHECK = 'technical_check';

    public const TYPE_PUBLICATION = 'publication';

    public const TYPE_DEVELOPMENT = 'development';

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

    // ── Stati derivati — task di tipo Sviluppo (sync GitHub, Blocco B) ──

    public const DERIVED_GH_BRANCH = 'github_branch';

    public const DERIVED_GH_PR_OPEN = 'github_pr_open';

    public const DERIVED_GH_PR_MERGED = 'github_pr_merged';

    public const DERIVED_GH_PR_CLOSED_UNMERGED = 'github_pr_closed_unmerged';

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
        'github_branch', 'github_pr_number', 'github_pr_state',
        'github_checks_state', 'github_review_state', 'github_synced_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'due_time' => 'datetime:H:i',
        'completed_at' => 'datetime',
        'manual_override' => 'boolean',
        'github_pr_number' => 'integer',
        'github_synced_at' => 'datetime',
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

    public function prompts(): HasMany
    {
        return $this->hasMany(ProjectPrompt::class, 'task_id');
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopePublicationType(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_PUBLICATION);
    }

    public function scopeDevelopmentType(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_DEVELOPMENT);
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

    /**
     * Una task è eleggibile se non ha dipendenza (depends_on_task_id nullo)
     * o se la dipendenza è realmente completed — qualunque altro stato
     * della dipendenza (todo/taken/in_progress/in_review/blocked/suspended/
     * cancelled) la rende non eleggibile. Solo un livello di catena: non
     * naviga transitivamente le dipendenze (ProjectNextActionResolver v1).
     */
    public function scopeEligible(Builder $q): Builder
    {
        return $q->where(function (Builder $q2) {
            $q2->whereNull('depends_on_task_id')
                ->orWhereHas('dependsOn', fn (Builder $dep) => $dep->where('manual_status', self::STATUS_COMPLETED));
        });
    }

    /**
     * "Bloccata da dipendenza" è un fatto DERIVATO — questa task ha una
     * dipendenza il cui manual_status non è (ancora) completed — mai
     * confuso con lo stato editoriale esplicito STATUS_BLOCKED, che è
     * sempre una decisione umana registrata in manual_status. Le due cose
     * possono coesistere o essere del tutto indipendenti: una task può
     * essere "Da fare" e bloccata da dipendenza, oppure "Bloccata"
     * editorialmente senza avere alcuna dipendenza. Non scrive mai nulla:
     * un'informazione calcolata al bisogno, non un campo persistito.
     */
    public function isBlockedByDependency(): bool
    {
        if ($this->depends_on_task_id === null) {
            return false;
        }

        return $this->dependsOn?->manual_status !== self::STATUS_COMPLETED;
    }

    /**
     * Impedisce che depends_on_task_id assuma un valore che renderebbe il
     * grafo delle dipendenze non valido: mai un'auto-dipendenza, mai un
     * ciclo (diretto o transitivo attraverso qualunque numero di task), e
     * — per V1 — mai una dipendenza verso una task di un altro progetto
     * (nessuna decisione architetturale esplicita la ammette oggi).
     * Risale la catena a partire dal candidato: se la catena raggiunge di
     * nuovo $task, l'assegnazione creerebbe un ciclo.
     */
    private static function guardAgainstInvalidDependency(ProjectTask $task): void
    {
        if ($task->depends_on_task_id === $task->id) {
            throw new InvalidTaskDependencyException(
                "Una task non può dipendere da se stessa (#{$task->id})."
            );
        }

        $dependency = static::query()->find($task->depends_on_task_id);

        if ($dependency === null) {
            return;
        }

        if ($dependency->project_id !== $task->project_id) {
            throw new InvalidTaskDependencyException(
                "Una dipendenza deve appartenere allo stesso progetto (task #{$task->id} → #{$dependency->id})."
            );
        }

        $current = $dependency;
        $visited = [];

        // Limite di sicurezza puramente difensivo: con questa guardia attiva
        // fin dalla prima dipendenza mai impostata, un ciclo pre-esistente
        // non dovrebbe mai poter esistere — ma un limite esplicito evita
        // comunque un loop infinito nel caso (dati corrotti, bypass diretto
        // del modello) invece di un errore silenzioso o un timeout opaco.
        $guard = 0;
        $maxIterations = 1000;

        while ($current !== null) {
            if ($current->id === $task->id) {
                throw new InvalidTaskDependencyException(
                    "Questa dipendenza creerebbe un ciclo tra le attività (task #{$task->id} → #{$dependency->id})."
                );
            }

            if (in_array($current->id, $visited, true)) {
                // Ciclo pre-esistente indipendente da questa assegnazione:
                // non è la voce corrente a crearlo, quindi non è questo il
                // punto giusto per bloccarla — ma non proseguire all'infinito.
                break;
            }

            $visited[] = $current->id;
            $current = $current->depends_on_task_id !== null
                ? static::query()->find($current->depends_on_task_id)
                : null;

            if (++$guard > $maxIterations) {
                break;
            }
        }
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
            self::TYPE_DEVELOPMENT => 'Sviluppo',
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
            self::DERIVED_GH_BRANCH => 'Branch aperto',
            self::DERIVED_GH_PR_OPEN => 'Pull request aperta',
            self::DERIVED_GH_PR_MERGED => 'Pull request mergiata',
            self::DERIVED_GH_PR_CLOSED_UNMERGED => 'Pull request chiusa senza merge',
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
